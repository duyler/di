<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection\Cache;

class FileCacheHandler implements CacheHandlerInterface
{
    private const SUFFIX      = '_Map_Cache.php';
    private const PERMISSIONS = 0o755;

    public function __construct(private readonly string $cacheDirPath)
    {
        is_dir($cacheDirPath) || mkdir($cacheDirPath, self::PERMISSIONS, true);
    }

    public function isExists(string $id): bool
    {
        return is_file((string)realpath($this->createFileName($id)));
    }

    public function get(string $id): array
    {
        return include realpath($this->createFileName($id));
    }

    public function record(string $id, array $dependencyTree): void
    {
        if (empty($dependencyTree)) {
            return;
        }

        $tree        = var_export($dependencyTree, true);
        $fileContent = <<<EOF
            <?php

            return {$tree};

            EOF;
        file_put_contents($this->createFileName($id), $fileContent);
    }

    public function invalidate(string $id): void
    {
        unlink(realpath($this->createFileName($id)));
    }

    private function createFileName(string $id): string
    {
        return $this->cacheDirPath . DIRECTORY_SEPARATOR . str_replace('\\', '_', $id) . self::SUFFIX;
    }
}
