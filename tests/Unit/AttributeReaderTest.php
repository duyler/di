<?php

declare(strict_types=1);

namespace Duyler\DI\Tests\Unit;

use Duyler\DI\Attribute\Bind;
use Duyler\DI\Attribute\Singleton;
use Duyler\DI\Attribute\Tag;
use Duyler\DI\Attribute\Transient;
use Duyler\DI\AttributeReader;
use Duyler\DI\Scope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AttributeReaderTest extends TestCase
{
    private AttributeReader $reader;

    protected function setUp(): void
    {
        $this->reader = new AttributeReader();
    }

    #[Test]
    public function reads_transient_scope(): void
    {
        $scope = $this->reader->getScope(AttributeTestTransientService::class);

        $this->assertEquals(Scope::Transient, $scope);
    }

    #[Test]
    public function reads_singleton_scope(): void
    {
        $scope = $this->reader->getScope(AttributeTestSingletonService::class);

        $this->assertEquals(Scope::Singleton, $scope);
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
}

#[Transient]
class AttributeTestTransientService {}

#[Singleton]
class AttributeTestSingletonService {}

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
