<?php

declare(strict_types=1);

namespace Duyler\DI;

use Duyler\DI\Exception\ScanPathException;
use Error;
use FilesystemIterator;
use ParseError;
use PhpToken;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use UnexpectedValueException;

final class ClassScanner
{
    /**
     * @param array<string> $paths
     * @return array<class-string>
     * @throws ScanPathException
    */
    public function scan(array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            $files = array_merge($files, $this->collectPhpFiles($path));
        }

        $classMap = [];
        foreach ($files as $file) {
            foreach ($this->extractClassNames($file) as $fqcn) {
                if (!isset($classMap[$fqcn])) {
                    $classMap[$fqcn] = $file;
                }
            }
        }

        $classes = [];
        foreach ($classMap as $fqcn => $file) {
            if (is_file($file)) {
                $preloaded = $fqcn;

                if (class_exists($preloaded, false)) {
                    $classes[] = $preloaded;
                    continue;
                }

                try {
                    require_once $file;
                } catch (ParseError|Error $exception) {
                    throw new ScanPathException($file, $exception->getMessage());
                }

                if (class_exists($fqcn, false)) {
                    $classes[] = $fqcn;
                }
            }
        }
        sort($classes);

        return $classes;
    }

    /**
     * @return list<string>
    */
    private function collectPhpFiles(string $path): array
    {
        if (is_file($path)) {
            return [$path];
        }

        if (!is_dir($path)) {
            throw new ScanPathException($path, 'path does not exist');
        }

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            );
        } catch (UnexpectedValueException $exception) {
            throw new ScanPathException($path, 'directory is not readable');
        }

        $files = [];
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (pathinfo($file->getPathname(), PATHINFO_EXTENSION) === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * @return list<string>
    */
    private function extractClassNames(string $file): array
    {
        try {
            $content = file_get_contents($file);
        } catch (RuntimeException $exception) {
            throw new ScanPathException($file, 'file is not readable');
        }

        if ($content === false) {
            throw new ScanPathException($file, 'file is not readable');
        }

        $tokens = PhpToken::tokenize($content);
        $namespace = '';
        $fqcns = [];
        $tokenCount = count($tokens);

        for ($index = 0; $index < $tokenCount; $index++) {
            $token = $tokens[$index];

            if ($token->is(T_NAMESPACE)) {
                $namespace = $this->resolveNamespace($tokens, $index);
                continue;
            }

            if (!$token->is(T_CLASS)) {
                continue;
            }

            $nameIndex = $this->nextMeaningfulIndex($tokens, $index);

            if ($nameIndex === null || !$tokens[$nameIndex]->is(T_STRING)) {
                continue;
            }

            $fqcns[] = ($namespace !== '' ? $namespace . '\\' : '') . $tokens[$nameIndex]->text;
        }

        return $fqcns;
    }

    /**
     * @param list<PhpToken> $tokens
    */
    private function resolveNamespace(array $tokens, int $namespaceIndex): string
    {
        $nameIndex = $this->nextMeaningfulIndex($tokens, $namespaceIndex);

        if ($nameIndex === null) {
            return '';
        }

        $token = $tokens[$nameIndex];

        if ($token->is(T_NAME_QUALIFIED) || $token->is(T_STRING)) {
            return $token->text;
        }

        return '';
    }

    /**
     * @param list<PhpToken> $tokens
    */
    private function nextMeaningfulIndex(array $tokens, int $index): ?int
    {
        $count = count($tokens);

        for ($next = $index + 1; $next < $count; $next++) {
            if (!$tokens[$next]->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                return $next;
            }
        }

        return null;
    }
}
