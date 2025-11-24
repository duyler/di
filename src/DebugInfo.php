<?php

declare(strict_types=1);

namespace Duyler\DI;

final class DebugInfo
{
    /** @var array<string, array{count: int, total_time: float, memory: int, resolved_at: array<float>}> */
    private array $resolutions = [];

    /** @var array<array{service: string, time: float, memory: int, depth: int}> */
    private array $resolutionLog = [];

    private bool $enabled = false;

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function recordResolution(string $serviceId, float $time, int $memory, int $depth = 0): void
    {
        if (!$this->enabled) {
            return;
        }

        if (!isset($this->resolutions[$serviceId])) {
            $this->resolutions[$serviceId] = [
                'count' => 0,
                'total_time' => 0.0,
                'memory' => 0,
                'resolved_at' => [],
            ];
        }

        $this->resolutions[$serviceId]['count']++;
        $this->resolutions[$serviceId]['total_time'] += $time;
        $this->resolutions[$serviceId]['memory'] = max($this->resolutions[$serviceId]['memory'], $memory);
        $this->resolutions[$serviceId]['resolved_at'][] = microtime(true);

        $this->resolutionLog[] = [
            'service' => $serviceId,
            'time' => $time,
            'memory' => $memory,
            'depth' => $depth,
        ];
    }

    /**
     * @return array<string, array{count: int, total_time: float, avg_time: float, memory: int, resolved_at: array<float>}>
     */
    public function getResolutions(): array
    {
        $result = [];

        foreach ($this->resolutions as $serviceId => $data) {
            $result[$serviceId] = [
                'count' => $data['count'],
                'total_time' => $data['total_time'],
                'avg_time' => $data['count'] > 0 ? $data['total_time'] / (float) $data['count'] : 0.0,
                'memory' => $data['memory'],
                'resolved_at' => $data['resolved_at'],
            ];
        }

        return $result;
    }

    /**
     * @return array<array{service: string, time: float, memory: int, depth: int}>
     */
    public function getResolutionLog(): array
    {
        return $this->resolutionLog;
    }

    /**
     * @return array{total_resolutions: int, unique_services: int, total_time: float, peak_memory: int, avg_time: float}
     */
    public function getStatistics(): array
    {
        $totalResolutions = 0;
        $totalTime = 0.0;
        $peakMemory = 0;

        foreach ($this->resolutions as $data) {
            $totalResolutions += $data['count'];
            $totalTime += $data['total_time'];
            $peakMemory = max($peakMemory, $data['memory']);
        }

        return [
            'total_resolutions' => $totalResolutions,
            'unique_services' => count($this->resolutions),
            'total_time' => $totalTime,
            'peak_memory' => $peakMemory,
            'avg_time' => $totalResolutions > 0 ? $totalTime / (float) $totalResolutions : 0.0,
        ];
    }

    public function getSlowestServices(int $limit = 10): array
    {
        $services = $this->getResolutions();

        usort($services, fn(array $a, array $b): int => $b['total_time'] <=> $a['total_time']);

        return array_slice($services, 0, $limit);
    }

    public function getMostResolvedServices(int $limit = 10): array
    {
        $services = $this->getResolutions();

        usort($services, fn(array $a, array $b): int => $b['count'] <=> $a['count']);

        return array_slice($services, 0, $limit);
    }

    public function reset(): void
    {
        $this->resolutions = [];
        $this->resolutionLog = [];
    }
}
