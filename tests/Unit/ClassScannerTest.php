<?php

declare(strict_types=1);

namespace Duyler\DI\Test\Unit;

use Duyler\DI\ClassScanner;
use Duyler\DI\Exception\ScanPathException;
use Duyler\DI\Test\Scan\Attribute\Nested\ScanNestedService;
use Duyler\DI\Test\Scan\Attribute\ScanConsoleLogger;
use Duyler\DI\Test\Scan\Attribute\ScanFinalizableService;
use Duyler\DI\Test\Scan\Attribute\ScanTaggedService;
use Duyler\DI\Test\Scan\Attribute\ScanTransientService;
use Duyler\DI\Test\Scan\Edge\ScanEdgeFirst;
use Duyler\DI\Test\Scan\Edge\ScanEdgeSecond;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use ReflectionClass;

final class ClassScannerTest extends TestCase
{
    #[Test]
    public function single_file_path_returns_declared_class(): void
    {
        $result = (new ClassScanner())->scan([__DIR__ . '/../Scan/Attribute/ScanConsoleLogger.php']);

        $this->assertSame([ScanConsoleLogger::class], $result);
    }

    #[Test]
    public function recursive_scan_finds_nested_directory(): void
    {
        $result = (new ClassScanner())->scan([__DIR__ . '/../Scan/Attribute']);

        $this->assertContains(ScanNestedService::class, $result);
    }

    #[Test]
    public function attribute_directory_finds_all_fixture_files(): void
    {
        $result = (new ClassScanner())->scan([__DIR__ . '/../Scan/Attribute']);

        $this->assertSame([
            ScanNestedService::class,
            ScanConsoleLogger::class,
            ScanFinalizableService::class,
            ScanTaggedService::class,
            ScanTransientService::class,
        ], $result);
    }

    #[Test]
    public function intersecting_paths_deduplicate_fqcn(): void
    {
        $dir = __DIR__ . '/../Scan/Attribute';

        $result = (new ClassScanner())->scan([$dir, $dir]);

        $this->assertSame([
            ScanNestedService::class,
            ScanConsoleLogger::class,
            ScanFinalizableService::class,
            ScanTaggedService::class,
            ScanTransientService::class,
        ], $result);
    }

    #[Test]
    public function preloaded_fqcn_from_foreign_file_is_counted_without_redeclare(): void
    {
        $dir = sys_get_temp_dir() . '/di-scan-preload-' . bin2hex(random_bytes(4));
        mkdir($dir, 0700, true);
        $foreignFile = sys_get_temp_dir() . '/di-scan-preload-foreign-' . bin2hex(random_bytes(4)) . '.php';
        $scannedFile = $dir . '/PreloadedCandidate.php';
        $content = '<?php namespace DiScanPreloadNs; class PreloadedCandidate {}';
        file_put_contents($foreignFile, $content);
        file_put_contents($scannedFile, $content);

        try {
            require_once $foreignFile;

            $result = (new ClassScanner())->scan([$dir]);

            $this->assertSame(['DiScanPreloadNs\PreloadedCandidate'], $result);
        } finally {
            unlink($scannedFile);
            rmdir($dir);
            unlink($foreignFile);
        }
    }

    #[Test]
    public function duplicate_fqcn_in_two_files_loads_first_only(): void
    {
        $dir = sys_get_temp_dir() . '/di-scan-dup-' . bin2hex(random_bytes(4));
        mkdir($dir, 0700, true);
        $first = $dir . '/a.php';
        $second = $dir . '/b.php';
        $content = '<?php namespace DiScanDupNs; class DupCandidate {}';
        file_put_contents($first, $content);
        file_put_contents($second, $content);

        try {
            $result = (new ClassScanner())->scan([$first, $second]);

            $this->assertSame(['DiScanDupNs\DupCandidate'], $result);

            $loadedFile = (new ReflectionClass('DiScanDupNs\DupCandidate'))->getFileName();
            $this->assertNotFalse($loadedFile);
            $this->assertSame('a.php', basename($loadedFile));
        } finally {
            unlink($first);
            unlink($second);
            rmdir($dir);
        }
    }

    #[Test]
    public function result_is_sorted_by_fqcn(): void
    {
        $result = (new ClassScanner())->scan([__DIR__ . '/../Scan/Attribute']);

        $this->assertSame([
            ScanNestedService::class,
            ScanConsoleLogger::class,
            ScanFinalizableService::class,
            ScanTaggedService::class,
            ScanTransientService::class,
        ], $result);
    }

    #[Test]
    public function multi_class_file_returns_both_classes(): void
    {
        $result = (new ClassScanner())->scan([__DIR__ . '/../Scan/Edge/ScanEdgeMulti.php']);

        $this->assertSame([ScanEdgeFirst::class, ScanEdgeSecond::class], $result);
    }

    #[Test]
    public function declarations_only_file_yields_nothing(): void
    {
        $result = (new ClassScanner())->scan([__DIR__ . '/../Scan/Edge/ScanEdgeDeclarations.php']);

        $this->assertSame([], $result);
    }

    #[Test]
    public function conditional_declaration_is_skipped(): void
    {
        $result = (new ClassScanner())->scan([__DIR__ . '/../Scan/Edge/ScanEdgeConditional.php']);

        $this->assertSame([], $result);
    }

    #[Test]
    public function non_existent_path_throws_scan_path_exception(): void
    {
        $scanner = new ClassScanner();
        $path = __DIR__ . '/../Scan/DoesNotExist';

        $this->expectException(ScanPathException::class);
        $this->expectExceptionMessage($path);

        $scanner->scan([$path]);
    }

    #[Test]
    public function invalid_php_file_throws_scan_path_exception(): void
    {
        $file = sys_get_temp_dir() . '/di-scan-invalid-' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($file, '<?php class ScanBrokenFixture {');

        try {
            (new ClassScanner())->scan([$file]);
            self::fail('ScanPathException expected');
        } catch (ScanPathException $exception) {
            $this->assertInstanceOf(ContainerExceptionInterface::class, $exception);
            $this->assertNull($exception->getPrevious());
            $this->assertStringContainsString($file, $exception->getMessage());
        } finally {
            unlink($file);
        }
    }

    #[Test]
    public function unreadable_directory_throws_scan_path_exception(): void
    {
        $dir = sys_get_temp_dir() . '/di-scan-unreadable-' . bin2hex(random_bytes(4));
        mkdir($dir, 0700, true);
        chmod($dir, 0000);

        if (is_readable($dir)) {
            chmod($dir, 0700);
            rmdir($dir);
            self::markTestSkipped('running as root: chmod 0000 is not effective');
        }

        try {
            $scanner = new ClassScanner();

            $this->expectException(ScanPathException::class);
            $this->expectExceptionMessage($dir);

            $scanner->scan([$dir]);
        } finally {
            chmod($dir, 0700);
            rmdir($dir);
        }
    }
}
