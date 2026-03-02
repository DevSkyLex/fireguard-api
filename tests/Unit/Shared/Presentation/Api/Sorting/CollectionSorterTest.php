<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Presentation\Api\Sorting;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Presentation\Api\Sorting\CollectionSorter;

/**
 * Test CollectionSorterTest.
 *
 * @category Sorting Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CollectionSorter::class)]
final class CollectionSorterTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testSortReturnsEmptyArrayForEmptyInput(): void
  {
    $result = CollectionSorter::sort([], new Sorting('name'));

    self::assertSame([], $result);
  }

  #[Test]
  public function testSortByStringFieldAscending(): void
  {
    $items = [
      (object) ['name' => 'Charlie', 'id' => '3'],
      (object) ['name' => 'Alice', 'id' => '1'],
      (object) ['name' => 'Bob', 'id' => '2'],
    ];

    $result = CollectionSorter::sort($items, new Sorting('name', SortDirection::ASC));

    self::assertSame('Alice', $result[0]->name);
    self::assertSame('Bob', $result[1]->name);
    self::assertSame('Charlie', $result[2]->name);
  }

  #[Test]
  public function testSortByStringFieldDescending(): void
  {
    $items = [
      (object) ['name' => 'Alice', 'id' => '1'],
      (object) ['name' => 'Charlie', 'id' => '3'],
      (object) ['name' => 'Bob', 'id' => '2'],
    ];

    $result = CollectionSorter::sort($items, new Sorting('name', SortDirection::DESC));

    self::assertSame('Charlie', $result[0]->name);
    self::assertSame('Bob', $result[1]->name);
    self::assertSame('Alice', $result[2]->name);
  }

  #[Test]
  public function testSortByIntegerField(): void
  {
    $items = [
      (object) ['name' => 'B', 'count' => 10],
      (object) ['name' => 'A', 'count' => 5],
      (object) ['name' => 'C', 'count' => 20],
    ];

    $result = CollectionSorter::sort($items, new Sorting('count', SortDirection::ASC));

    self::assertSame(5, $result[0]->count);
    self::assertSame(10, $result[1]->count);
    self::assertSame(20, $result[2]->count);
  }

  #[Test]
  public function testSortByBooleanField(): void
  {
    $items = [
      (object) ['name' => 'A', 'isActive' => true],
      (object) ['name' => 'B', 'isActive' => false],
      (object) ['name' => 'C', 'isActive' => true],
    ];

    $result = CollectionSorter::sort($items, new Sorting('isActive', SortDirection::ASC));

    self::assertFalse($result[0]->isActive);
    self::assertTrue($result[1]->isActive);
  }

  #[Test]
  public function testSortPreservesOrderForEqualValues(): void
  {
    $items = [
      (object) ['name' => 'Same', 'id' => '1'],
      (object) ['name' => 'Same', 'id' => '2'],
      (object) ['name' => 'Same', 'id' => '3'],
    ];

    $result = CollectionSorter::sort($items, new Sorting('name', SortDirection::ASC));

    self::assertCount(3, $result);
  }

  #[Test]
  public function testSortSingleItem(): void
  {
    $items = [
      (object) ['name' => 'Only'],
    ];

    $result = CollectionSorter::sort($items, new Sorting('name', SortDirection::DESC));

    self::assertCount(1, $result);
    self::assertSame('Only', $result[0]->name);
  }

  #[Test]
  public function testSortByDateStringField(): void
  {
    $items = [
      (object) ['name' => 'B', 'createdAt' => '2026-02-15T08:00:00+00:00'],
      (object) ['name' => 'A', 'createdAt' => '2026-01-01T08:00:00+00:00'],
      (object) ['name' => 'C', 'createdAt' => '2026-03-01T08:00:00+00:00'],
    ];

    $result = CollectionSorter::sort($items, new Sorting('createdAt', SortDirection::DESC));

    self::assertSame('C', $result[0]->name);
    self::assertSame('B', $result[1]->name);
    self::assertSame('A', $result[2]->name);
  }
  // #endregion
}
