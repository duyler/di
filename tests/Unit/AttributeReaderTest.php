<?php

declare(strict_types=1);

namespace Duyler\DI\Tests\Unit;

use Duyler\DI\Attribute\Bind;
use Duyler\DI\Attribute\Finalize;
use Duyler\DI\Attribute\Tag;
use Duyler\DI\Attribute\Transient;
use Duyler\DI\AttributeReader;
use Duyler\DI\Scope;
use Duyler\DI\Storage\ReflectionStorage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AttributeReaderTest extends TestCase
{
    private AttributeReader $reader;

    protected function setUp(): void
    {
        $this->reader = new AttributeReader(new ReflectionStorage());
    }

    #[Test]
    public function reads_transient_scope(): void
    {
        $scope = $this->reader->getScope(AttributeTestTransientService::class);

        $this->assertEquals(Scope::Transient, $scope);
    }

    #[Test]
    public function returns_null_when_no_scope_attribute(): void
    {
        $scope = $this->reader->getScope(AttributeTestNoScopeService::class);

        $this->assertNull($scope);
    }

    #[Test]
    public function reads_single_tag(): void
    {
        $tags = $this->reader->getTags(AttributeTestSingleTagService::class);

        $this->assertEquals(['tag1'], $tags);
    }

    #[Test]
    public function reads_multiple_tags_from_array(): void
    {
        $tags = $this->reader->getTags(AttributeTestMultipleTagsService::class);

        $this->assertEquals(['tag1', 'tag2', 'tag3'], $tags);
    }

    #[Test]
    public function reads_multiple_tag_attributes(): void
    {
        $tags = $this->reader->getTags(AttributeTestRepeatedTagService::class);

        $this->assertEquals(['tag1', 'tag2'], $tags);
    }

    #[Test]
    public function returns_empty_array_when_no_tags(): void
    {
        $tags = $this->reader->getTags(AttributeTestNoTagService::class);

        $this->assertEmpty($tags);
    }

    #[Test]
    public function reads_binding(): void
    {
        $binding = $this->reader->getBinding(AttributeTestImplementation::class);

        $this->assertEquals(AttributeTestInterface::class, $binding);
    }

    #[Test]
    public function returns_null_when_no_binding(): void
    {
        $binding = $this->reader->getBinding(AttributeTestNoBindService::class);

        $this->assertNull($binding);
    }

    #[Test]
    public function detects_attributes_presence(): void
    {
        $this->assertTrue($this->reader->hasAttributes(AttributeTestTransientService::class));
        $this->assertTrue($this->reader->hasAttributes(AttributeTestSingleTagService::class));
        $this->assertTrue($this->reader->hasAttributes(AttributeTestImplementation::class));
        $this->assertFalse($this->reader->hasAttributes(AttributeTestNoAttributesService::class));
    }

    #[Test]
    public function reads_combined_attributes(): void
    {
        $scope = $this->reader->getScope(AttributeTestCombinedService::class);
        $tags = $this->reader->getTags(AttributeTestCombinedService::class);

        $this->assertEquals(Scope::Transient, $scope);
        $this->assertEquals(['tag1', 'tag2'], $tags);
    }

    #[Test]
    public function reflection_is_shared_across_reader_calls(): void
    {
        $storage = new ReflectionStorage();
        $reader = new AttributeReader($storage);

        $reader->getScope(AttributeTestSingleTagService::class);
        $reader->getTags(AttributeTestSingleTagService::class);
        $reader->getBinding(AttributeTestSingleTagService::class);
        $reader->hasAttributes(AttributeTestSingleTagService::class);
        $reader->getInterfaces(AttributeTestSingleTagService::class);

        $this->assertTrue($storage->has(AttributeTestSingleTagService::class));
        $this->assertSame(
            AttributeTestSingleTagService::class,
            $storage->get(AttributeTestSingleTagService::class)->getName(),
        );
    }

    #[Test]
    public function has_attributes_result_is_cached_per_class(): void
    {
        $reader = new AttributeReader(new ReflectionStorage());

        $this->assertTrue($reader->hasAttributes(AttributeTestSingleTagService::class));
        $this->assertFalse($reader->hasAttributes(AttributeTestNoScopeService::class));
        $this->assertTrue($reader->hasAttributes(AttributeTestSingleTagService::class));
        $this->assertFalse($reader->hasAttributes(AttributeTestNoScopeService::class));
    }

    #[Test]
    public function has_attributes_sees_finalize_only_class(): void
    {
        $this->assertTrue($this->reader->hasAttributes(AttributeTestFinalizeOnlyService::class));
    }
}

#[Transient]
class AttributeTestTransientService {}

#[Finalize]
class AttributeTestFinalizeOnlyService {}

class AttributeTestNoScopeService {}

#[Tag('tag1')]
class AttributeTestSingleTagService {}

#[Tag(['tag1', 'tag2', 'tag3'])]
class AttributeTestMultipleTagsService {}

#[Tag('tag1')]
#[Tag('tag2')]
class AttributeTestRepeatedTagService {}

class AttributeTestNoTagService {}

interface AttributeTestInterface {}

#[Bind(AttributeTestInterface::class)]
class AttributeTestImplementation implements AttributeTestInterface {}

class AttributeTestNoBindService {}

class AttributeTestNoAttributesService {}

#[Transient]
#[Tag(['tag1', 'tag2'])]
class AttributeTestCombinedService {}
