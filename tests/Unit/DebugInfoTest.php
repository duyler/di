<?php

declare(strict_types=1);

namespace Duyler\DI\Tests\Unit;

use Duyler\DI\DebugInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DebugInfoTest extends TestCase
{
    #[Test]
    public function starts_disabled_by_default(): void
    {
        $debugInfo = new DebugInfo();

        $this->assertFalse($debugInfo->isEnabled());
    }

    #[Test]
    public function can_be_enabled(): void
    {
        $debugInfo = new DebugInfo();
        $debugInfo->enable();

        $this->assertTrue($debugInfo->isEnabled());
    }

    #[Test]
    public function can_be_disabled(): void
    {
        $debugInfo = new DebugInfo();
        $debugInfo->enable();
        $debugInfo->disable();

        $this->assertFalse($debugInfo->isEnabled());
    }

    #[Test]
    public function does_not_record_when_disabled(): void
    {
        $debugInfo = new DebugInfo();
        $debugInfo->recordResolution('ServiceA', 0.001, 1024);

        $resolutions = $debugInfo->getResolutions();

        $this->assertEmpty($resolutions);
    }

    #[Test]
    public function records_resolution_when_enabled(): void
    {
        $debugInfo = new DebugInfo();
        $debugInfo->enable();
        $debugInfo->recordResolution('ServiceA', 0.001, 1024);

        $resolutions = $debugInfo->getResolutions();

        $this->assertCount(1, $resolutions);
        $this->assertArrayHasKey('ServiceA', $resolutions);
        $this->assertEquals(1, $resolutions['ServiceA']['count']);
        $this->assertEquals(0.001, $resolutions['ServiceA']['total_time']);
        $this->assertEquals(1024, $resolutions['ServiceA']['memory']);
    }

    #[Test]
    public function records_multiple_resolutions_for_same_service(): void
    {
        $debugInfo = new DebugInfo();
        $debugInfo->enable();
        $debugInfo->recordResolution('ServiceA', 0.001, 1024);
        $debugInfo->recordResolution('ServiceA', 0.002, 2048);
        $debugInfo->recordResolution('ServiceA', 0.003, 1536);

        $resolutions = $debugInfo->getResolutions();

        $this->assertEquals(3, $resolutions['ServiceA']['count']);
        $this->assertEquals(0.006, $resolutions['ServiceA']['total_time']);
        $this->assertEquals(0.002, $resolutions['ServiceA']['avg_time']);
        $this->assertEquals(2048, $resolutions['ServiceA']['memory']);
    }

    #[Test]
    public function records_multiple_different_services(): void
    {
        $debugInfo = new DebugInfo();
        $debugInfo->enable();
        $debugInfo->recordResolution('ServiceA', 0.001, 1024);
        $debugInfo->recordResolution('ServiceB', 0.002, 2048);
        $debugInfo->recordResolution('ServiceC', 0.003, 3072);

        $resolutions = $debugInfo->getResolutions();

        $this->assertCount(3, $resolutions);
        $this->assertArrayHasKey('ServiceA', $resolutions);
        $this->assertArrayHasKey('ServiceB', $resolutions);
        $this->assertArrayHasKey('ServiceC', $resolutions);
    }

    #[Test]
    public function calculates_average_time_correctly(): void
    {
        $debugInfo = new DebugInfo();
        $debugInfo->enable();
        $debugInfo->recordResolution('ServiceA', 0.001, 1024);
        $debugInfo->recordResolution('ServiceA', 0.002, 1024);
        $debugInfo->recordResolution('ServiceA', 0.003, 1024);

        $resolutions = $debugInfo->getResolutions();

        $this->assertEquals(0.002, $resolutions['ServiceA']['avg_time']);
    }

    #[Test]
    public function tracks_peak_memory_usage(): void
    {
        $debugInfo = new DebugInfo();
        $debugInfo->enable();
        $debugInfo->recordResolution('ServiceA', 0.001, 1024);
        $debugInfo->recordResolution('ServiceA', 0.001, 4096);
        $debugInfo->recordResolution('ServiceA', 0.001, 2048);

        $resolutions = $debugInfo->getResolutions();

        $this->assertEquals(4096, $resolutions['ServiceA']['memory']);
    }

    #[Test]
    public function maintains_resolution_log(): void
    {
        $debugInfo = new DebugInfo();
        $debugInfo->enable();
        $debugInfo->recordResolution('ServiceA', 0.001, 1024, 0);
        $debugInfo->recordResolution('ServiceB', 0.002, 2048, 1);
        $debugInfo->recordResolution('ServiceC', 0.003, 3072, 2);

        $log = $debugInfo->getResolutionLog();

        $this->assertCount(3, $log);
        $this->assertEquals('ServiceA', $log[0]['service']);
        $this->assertEquals('ServiceB', $log[1]['service']);
        $this->assertEquals('ServiceC', $log[2]['service']);
        $this->assertEquals(0, $log[0]['depth']);
        $this->assertEquals(1, $log[1]['depth']);
        $this->assertEquals(2, $log[2]['depth']);
    }

    #[Test]
    public function calculates_statistics_correctly(): void
    {
        $debugInfo = new DebugInfo();
        $debugInfo->enable();
        $debugInfo->recordResolution('ServiceA', 0.001, 1024);
        $debugInfo->recordResolution('ServiceA', 0.002, 2048);
        $debugInfo->recordResolution('ServiceB', 0.003, 3072);

        $stats = $debugInfo->getStatistics();

        $this->assertEquals(3, $stats['total_resolutions']);
        $this->assertEquals(2, $stats['unique_services']);
        $this->assertEquals(0.006, $stats['total_time']);
        $this->assertEquals(3072, $stats['peak_memory']);
        $this->assertEquals(0.002, $stats['avg_time']);
    }

    #[Test]
    public function returns_slowest_services(): void
    {
        $debugInfo = new DebugInfo();
        $debugInfo->enable();
        $debugInfo->recordResolution('FastService', 0.001, 1024);
        $debugInfo->recordResolution('SlowService', 0.010, 1024);
        $debugInfo->recordResolution('MediumService', 0.005, 1024);

        $slowest = $debugInfo->getSlowestServices(2);

        $this->assertCount(2, $slowest);
        $this->assertEquals(0.010, $slowest[0]['total_time']);
        $this->assertEquals(0.005, $slowest[1]['total_time']);
    }

    #[Test]
    public function returns_most_resolved_services(): void
    {
        $debugInfo = new DebugInfo();
        $debugInfo->enable();
        $debugInfo->recordResolution('ServiceA', 0.001, 1024);
        $debugInfo->recordResolution('ServiceB', 0.001, 1024);
        $debugInfo->recordResolution('ServiceB', 0.001, 1024);
        $debugInfo->recordResolution('ServiceC', 0.001, 1024);
        $debugInfo->recordResolution('ServiceC', 0.001, 1024);
        $debugInfo->recordResolution('ServiceC', 0.001, 1024);

        $most = $debugInfo->getMostResolvedServices(2);

        $this->assertCount(2, $most);
        $this->assertEquals(3, $most[0]['count']);
        $this->assertEquals(2, $most[1]['count']);
    }

    #[Test]
    public function reset_clears_all_data(): void
    {
        $debugInfo = new DebugInfo();
        $debugInfo->enable();
        $debugInfo->recordResolution('ServiceA', 0.001, 1024);
        $debugInfo->recordResolution('ServiceB', 0.002, 2048);

        $debugInfo->reset();

        $this->assertEmpty($debugInfo->getResolutions());
        $this->assertEmpty($debugInfo->getResolutionLog());
        $stats = $debugInfo->getStatistics();
        $this->assertEquals(0, $stats['total_resolutions']);
    }

    #[Test]
    public function reset_does_not_disable_debug_mode(): void
    {
        $debugInfo = new DebugInfo();
        $debugInfo->enable();
        $debugInfo->reset();

        $this->assertTrue($debugInfo->isEnabled());
    }

    #[Test]
    public function records_resolved_at_timestamps(): void
    {
        $debugInfo = new DebugInfo();
        $debugInfo->enable();

        $before = microtime(true);
        $debugInfo->recordResolution('ServiceA', 0.001, 1024);
        $after = microtime(true);

        $resolutions = $debugInfo->getResolutions();
        $timestamps = $resolutions['ServiceA']['resolved_at'];

        $this->assertCount(1, $timestamps);
        $this->assertGreaterThanOrEqual($before, $timestamps[0]);
        $this->assertLessThanOrEqual($after, $timestamps[0]);
    }
}
