<?php

declare(strict_types=1);

namespace Duyler\DI\Test\Functional;

use Duyler\DI\Container;
use Duyler\DI\ContainerConfig;
use Duyler\DI\Exception\DuplicateBindingException;
use Duyler\DI\Exception\InterfaceBindNotFoundException;
use Duyler\DI\Scope;
use Duyler\DI\Test\Scan\Attribute\Nested\ScanNestedService;
use Duyler\DI\Test\Scan\Attribute\ScanConsoleLogger;
use Duyler\DI\Test\Scan\Attribute\ScanFinalizableService;
use Duyler\DI\Test\Scan\Attribute\ScanLoggerInterface;
use Duyler\DI\Test\Scan\Attribute\ScanTaggedService;
use Duyler\DI\Test\Scan\Attribute\ScanTransientService;
use Duyler\DI\Test\Scan\Conflict\ScanConflictImplementationA;
use Duyler\DI\Test\Scan\Conflict\ScanConflictImplementationB;
use Duyler\DI\Test\Scan\Conflict\ScanConflictInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AttributeScanTest extends TestCase
{
    #[Test]
    public function scan_binds_interface_from_attribute_without_manual_get(): void
    {
        $config = new ContainerConfig();
        $config->withScanPaths(__DIR__ . '/../Scan/Attribute');
        $container = new Container($config);

        $service = $container->get(ScanLoggerInterface::class);

        $this->assertInstanceOf(ScanConsoleLogger::class, $service);
    }

    #[Test]
    public function scanned_transient_attribute_creates_new_instances(): void
    {
        $config = new ContainerConfig();
        $config->withScanPaths(__DIR__ . '/../Scan/Attribute');
        $container = new Container($config);

        $first = $container->get(ScanTransientService::class);
        $second = $container->get(ScanTransientService::class);

        $this->assertNotSame($first, $second);
    }

    #[Test]
    public function scanned_tags_visible_before_first_get(): void
    {
        $config = new ContainerConfig();
        $config->withScanPaths(__DIR__ . '/../Scan/Attribute');
        $container = new Container($config);

        $tagged = $container->tagged('scan.tag');

        $this->assertCount(1, $tagged);
        $this->assertSame(ScanTaggedService::class, $tagged[0]::class);
    }

    #[Test]
    public function finalized_attribute_method_invoked_on_finalize(): void
    {
        $probeDir = sys_get_temp_dir() . '/di-scan-finalize-' . bin2hex(random_bytes(4));
        mkdir($probeDir, 0700, true);
        $probeFile = $probeDir . '/FinalizeProbe.php';
        $probeClass = 'DiScanProbeNs\FinalizeProbe';
        file_put_contents(
            $probeFile,
            '<?php namespace DiScanProbeNs; use Duyler\DI\Attribute\Finalize;'
            . ' #[Finalize] class FinalizeProbe { public static bool $called = false;'
            . ' public function finalize(): void { self::$called = true; } }',
        );

        try {
            $config = new ContainerConfig();
            $config->withScanPaths(__DIR__ . '/../Scan/Attribute', $probeDir);
            $container = new Container($config);

            $container->get(ScanFinalizableService::class);
            $container->get($probeClass);
            $container->finalize();

            $this->assertTrue($probeClass::$called);
        } finally {
            unlink($probeFile);
            rmdir($probeDir);
        }
    }

    #[Test]
    public function scanned_nested_directory_tags_resolved(): void
    {
        $config = new ContainerConfig();
        $config->withScanPaths(__DIR__ . '/../Scan/Attribute');
        $container = new Container($config);

        $tagged = $container->tagged('scan.nested');

        $this->assertCount(1, $tagged);
        $this->assertSame(ScanNestedService::class, $tagged[0]::class);
    }

    #[Test]
    public function explicit_config_bind_wins_over_scan(): void
    {
        $config = new ContainerConfig();
        $config->withBind([ScanLoggerInterface::class => ManualLogger::class]);
        $config->withScanPaths(__DIR__ . '/../Scan/Attribute');
        $container = new Container($config);

        $service = $container->get(ScanLoggerInterface::class);

        $this->assertInstanceOf(ManualLogger::class, $service);
    }

    #[Test]
    public function runtime_bind_wins_over_scan_and_stays(): void
    {
        $config = new ContainerConfig();
        $config->withScanPaths(__DIR__ . '/../Scan/Attribute');
        $container = new Container($config);
        $container->bind([ScanLoggerInterface::class => ManualLogger::class]);

        $service = $container->get(ScanLoggerInterface::class);

        $this->assertInstanceOf(ManualLogger::class, $service);

        $container->get(ScanConsoleLogger::class);
        $again = $container->get(ScanLoggerInterface::class);

        $this->assertInstanceOf(ManualLogger::class, $again);
    }

    #[Test]
    public function config_scope_wins_over_transient_attribute(): void
    {
        $config = new ContainerConfig();
        $config->withScope(ScanTransientService::class, Scope::Singleton);
        $config->withScanPaths(__DIR__ . '/../Scan/Attribute');
        $container = new Container($config);

        $first = $container->get(ScanTransientService::class);
        $second = $container->get(ScanTransientService::class);

        $this->assertSame($first, $second);
    }

    #[Test]
    public function duplicate_scan_bindings_throw_duplicate_binding_exception(): void
    {
        $config = new ContainerConfig();
        $config->withScanPaths(__DIR__ . '/../Scan/Conflict');

        try {
            new Container($config);
            self::fail('DuplicateBindingException expected');
        } catch (DuplicateBindingException $exception) {
            $this->assertNull($exception->getPrevious());
            $this->assertStringContainsString(ScanConflictInterface::class, $exception->getMessage());
            $this->assertStringContainsString(ScanConflictImplementationA::class, $exception->getMessage());
            $this->assertStringContainsString(ScanConflictImplementationB::class, $exception->getMessage());
        }
    }

    #[Test]
    public function scan_of_empty_directory_keeps_container_working(): void
    {
        $emptyDir = sys_get_temp_dir() . '/di-scan-empty-' . bin2hex(random_bytes(4));
        mkdir($emptyDir, 0700, true);

        try {
            $config = new ContainerConfig();
            $config->withScanPaths($emptyDir);
            $container = new Container($config);

            try {
                $container->get(ScanProbeUnboundContract::class);
                self::fail('InterfaceBindNotFoundException expected');
            } catch (InterfaceBindNotFoundException $exception) {
                $this->assertNull($exception->getPrevious());
            }
        } finally {
            rmdir($emptyDir);
        }
    }

    #[Test]
    public function without_scan_paths_attribute_binding_is_not_registered(): void
    {
        $container = new Container();

        try {
            $container->get(ScanLoggerInterface::class);
            self::fail('InterfaceBindNotFoundException expected');
        } catch (InterfaceBindNotFoundException $exception) {
            $this->assertNull($exception->getPrevious());
        }
    }

    #[Test]
    public function reset_clears_scan_tags_but_keeps_scan_bindings(): void
    {
        $config = new ContainerConfig();
        $config->withScanPaths(__DIR__ . '/../Scan/Attribute');
        $container = new Container($config);

        $container->get(ScanTaggedService::class);
        $container->reset();

        $this->assertSame([], $container->tagged('scan.tag'));

        $service = $container->get(ScanLoggerInterface::class);

        $this->assertInstanceOf(ScanConsoleLogger::class, $service);

        $container->get(ScanTaggedService::class);
        $tagged = $container->tagged('scan.tag');

        $this->assertCount(1, $tagged);
        $this->assertSame(ScanTaggedService::class, $tagged[0]::class);
    }

    #[Test]
    public function strict_resolution_allows_scanned_bindings(): void
    {
        $config = new ContainerConfig();
        $config->withStrictResolution(true);
        $config->withScanPaths(__DIR__ . '/../Scan/Attribute');
        $container = new Container($config);

        $service = $container->get(ScanLoggerInterface::class);

        $this->assertInstanceOf(ScanConsoleLogger::class, $service);
    }

    #[Test]
    public function auto_tagging_exposes_scanned_interfaces_before_get(): void
    {
        $config = new ContainerConfig();
        $config->withAutoTagging();
        $config->withScanPaths(__DIR__ . '/../Scan/Attribute');
        $container = new Container($config);

        $tagged = $container->tagged(ScanLoggerInterface::class);

        $this->assertCount(1, $tagged);
        $this->assertSame(ScanConsoleLogger::class, $tagged[0]::class);
    }

    #[Test]
    public function repeated_get_returns_same_instance(): void
    {
        $config = new ContainerConfig();
        $config->withScanPaths(__DIR__ . '/../Scan/Attribute');
        $container = new Container($config);

        $first = $container->get(ScanLoggerInterface::class);
        $second = $container->get(ScanLoggerInterface::class);

        $this->assertSame($first, $second);
    }

    #[Test]
    public function duplicate_path_entries_are_harmless(): void
    {
        $dir = __DIR__ . '/../Scan/Attribute';
        $config = new ContainerConfig();
        $config->withScanPaths($dir, $dir);
        $container = new Container($config);

        $service = $container->get(ScanLoggerInterface::class);

        $this->assertInstanceOf(ScanConsoleLogger::class, $service);
    }
}

class ManualLogger implements ScanLoggerInterface {}

interface ScanProbeUnboundContract {}
