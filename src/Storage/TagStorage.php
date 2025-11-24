<?php

declare(strict_types=1);

namespace Duyler\DI\Storage;

final class TagStorage
{
    /** @var array<string, array<string>> */
    private array $tags = [];

    /**
     * @param string|array<string> $tags
     */
    public function tag(string $serviceId, string|array $tags): void
    {
        $tags = is_array($tags) ? $tags : [$tags];

        foreach ($tags as $tag) {
            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = [];
            }

            if (!in_array($serviceId, $this->tags[$tag], true)) {
                $this->tags[$tag][] = $serviceId;
            }
        }
    }

    /**
     * @return array<string>
     */
    public function tagged(string $tag): array
    {
        return $this->tags[$tag] ?? [];
    }

    /**
     * @return array<string>
     */
    public function getServiceTags(string $serviceId): array
    {
        $serviceTags = [];

        foreach ($this->tags as $tag => $services) {
            if (in_array($serviceId, $services, true)) {
                $serviceTags[] = $tag;
            }
        }

        return $serviceTags;
    }

    public function hasTag(string $tag): bool
    {
        return isset($this->tags[$tag]);
    }

    public function reset(): void
    {
        $this->tags = [];
    }

    /**
     * @return array<string, array<string>>
     */
    public function getAllTags(): array
    {
        return $this->tags;
    }
}
