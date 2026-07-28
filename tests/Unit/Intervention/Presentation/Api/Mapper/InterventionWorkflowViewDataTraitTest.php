<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Mapper;

use Intervention\Presentation\Api\Mapper\InterventionWorkflowViewDataTrait;
use LogicException;
use PHPUnit\Framework\Attributes\{CoversTrait, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Test InterventionWorkflowViewDataTraitTest.
 *
 * The workflow view is a loosely typed array coming back from the gateway;
 * this trait is the single place that narrows it before the output factories
 * touch it, so every reader must reject the wrong shape loudly.
 *
 * @category Trait Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversTrait(InterventionWorkflowViewDataTrait::class)]
final class InterventionWorkflowViewDataTraitTest extends TestCase
{
  // #region Methods
  /**
   * @return iterable<string, array{string, array<string, mixed>, mixed}>
   */
  public static function readerProvider(): iterable
  {
    yield 'string' => ['string', ['name' => 'Quarterly audit'], 'Quarterly audit'];

    yield 'nullable string with a value' => ['nullableString', ['name' => 'Quarterly audit'], 'Quarterly audit'];

    yield 'nullable string when absent' => ['nullableString', [], null];

    yield 'integer' => ['integer', ['name' => 7], 7];

    yield 'boolean' => ['boolean', ['name' => false], false];

    yield 'string list' => ['stringList', ['name' => [2 => 'a', 5 => 'b']], ['a', 'b']];

    yield 'object' => ['object', ['name' => ['from' => 'draft']], ['from' => 'draft']];

    yield 'nullable object with a value' => ['nullableObject', ['name' => ['from' => 'draft']], ['from' => 'draft']];

    yield 'nullable object when absent' => ['nullableObject', [], null];

    yield 'object list' => ['objectList', ['name' => [3 => ['id' => 'a']]], [['id' => 'a']]];
  }

  /**
   * @return iterable<string, array{string, array<string, mixed>, string}>
   */
  public static function rejectionProvider(): iterable
  {
    yield 'string rejects an integer' => ['string', ['name' => 7], 'name must be a string.'];

    yield 'nullable string rejects an integer' => ['nullableString', ['name' => 7], 'name must be a string or null.'];

    yield 'integer rejects a string' => ['integer', ['name' => '7'], 'name must be an integer.'];

    yield 'boolean rejects an integer' => ['boolean', ['name' => 1], 'name must be a boolean.'];

    yield 'string list rejects a scalar' => ['stringList', ['name' => 'a'], 'name must be a list of strings.'];

    yield 'string list rejects a non-string item' => ['stringList', ['name' => ['a', 2]], 'name must be a list of strings.'];

    yield 'object rejects a scalar' => ['object', ['name' => 'a'], 'name must be an object.'];

    yield 'object rejects integer keys' => ['object', ['name' => ['a', 'b']], 'name must be an object.'];

    yield 'nullable object rejects a scalar' => ['nullableObject', ['name' => 'a'], 'name must be an object.'];

    yield 'object list rejects a scalar' => ['objectList', ['name' => 'a'], 'name must be a list of objects.'];

    yield 'object list rejects a non-array item' => ['objectList', ['name' => ['a']], 'name must be a list of objects.'];

    yield 'object list rejects integer keys inside an item' => ['objectList', ['name' => [['a']]], 'name must be a list of objects.'];
  }

  /**
   * @param array<string, mixed> $data
   */
  #[Test]
  #[DataProvider('readerProvider')]
  public function testReaderNarrowsAWellShapedValue(string $reader, array $data, mixed $expected): void
  {
    self::assertSame($expected, $this->invoke($reader, $data));
  }

  /**
   * @param array<string, mixed> $data
   */
  #[Test]
  #[DataProvider('rejectionProvider')]
  public function testReaderRejectsTheWrongShape(string $reader, array $data, string $message): void
  {
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage($message);

    $this->invoke($reader, $data);
  }

  /**
   * @param array<string, mixed> $data
   */
  private function invoke(string $reader, array $data): mixed
  {
    $host = new class () {
      use InterventionWorkflowViewDataTrait;
    };

    return new ReflectionMethod($host, $reader)->invoke($host, $data, 'name');
  }
  // #endregion
}
