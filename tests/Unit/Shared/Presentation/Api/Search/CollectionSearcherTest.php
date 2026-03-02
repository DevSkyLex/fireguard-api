<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Presentation\Api\Search;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Presentation\Api\Search\CollectionSearcher;

/**
 * Test CollectionSearcherTest.
 *
 * @category Search Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CollectionSearcher::class)]
final class CollectionSearcherTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testSearchReturnsAllItemsWhenSearchIsNull(): void
  {
    $items = [
      (object) ['name' => 'Alice'],
      (object) ['name' => 'Bob'],
    ];

    $result = CollectionSearcher::search($items, null, ['name']);

    self::assertCount(2, $result);
  }

  #[Test]
  public function testSearchReturnsEmptyArrayForEmptyInput(): void
  {
    $result = CollectionSearcher::search([], 'test', ['name']);

    self::assertSame([], $result);
  }

  #[Test]
  public function testSearchReturnsAllItemsWhenFieldsAreEmpty(): void
  {
    $items = [
      (object) ['name' => 'Alice'],
    ];

    $result = CollectionSearcher::search($items, 'Alice', []);

    self::assertCount(1, $result);
  }

  #[Test]
  public function testSearchFiltersByExactMatch(): void
  {
    $items = [
      (object) ['name' => 'Alice', 'email' => 'alice@example.com'],
      (object) ['name' => 'Bob', 'email' => 'bob@example.com'],
      (object) ['name' => 'Charlie', 'email' => 'charlie@example.com'],
    ];

    $result = CollectionSearcher::search($items, 'Bob', ['name', 'email']);

    self::assertCount(1, $result);
    self::assertSame('Bob', $result[0]->name);
  }

  #[Test]
  public function testSearchIsCaseInsensitive(): void
  {
    $items = [
      (object) ['name' => 'Alice'],
      (object) ['name' => 'Bob'],
    ];

    $result = CollectionSearcher::search($items, 'alice', ['name']);

    self::assertCount(1, $result);
    self::assertSame('Alice', $result[0]->name);
  }

  #[Test]
  public function testSearchMatchesPartialStrings(): void
  {
    $items = [
      (object) ['name' => 'Alice Johnson'],
      (object) ['name' => 'Bob Smith'],
      (object) ['name' => 'Charlie Jones'],
    ];

    $result = CollectionSearcher::search($items, 'jo', ['name']);

    self::assertCount(2, $result);
    self::assertSame('Alice Johnson', $result[0]->name);
    self::assertSame('Charlie Jones', $result[1]->name);
  }

  #[Test]
  public function testSearchMatchesAcrossMultipleFields(): void
  {
    $items = [
      (object) ['name' => 'Alice', 'email' => 'alice@example.com'],
      (object) ['name' => 'Bob', 'email' => 'bob@test.com'],
      (object) ['name' => 'Charlie', 'email' => 'charlie@example.com'],
    ];

    $result = CollectionSearcher::search($items, 'example', ['name', 'email']);

    self::assertCount(2, $result);
    self::assertSame('Alice', $result[0]->name);
    self::assertSame('Charlie', $result[1]->name);
  }

  #[Test]
  public function testSearchIgnoresNonStringFields(): void
  {
    $items = [
      (object) ['name' => 'Alice', 'count' => 42],
      (object) ['name' => 'Bob', 'count' => 10],
    ];

    $result = CollectionSearcher::search($items, '42', ['name', 'count']);

    self::assertCount(0, $result);
  }

  #[Test]
  public function testSearchReturnsReindexedList(): void
  {
    $items = [
      (object) ['name' => 'Alice'],
      (object) ['name' => 'Bob'],
      (object) ['name' => 'Charlie'],
    ];

    $result = CollectionSearcher::search($items, 'Charlie', ['name']);

    self::assertCount(1, $result);
    self::assertArrayHasKey(0, $result);
    self::assertSame('Charlie', $result[0]->name);
  }

  #[Test]
  public function testSearchReturnsNoMatchesWhenTermNotFound(): void
  {
    $items = [
      (object) ['name' => 'Alice'],
      (object) ['name' => 'Bob'],
    ];

    $result = CollectionSearcher::search($items, 'xyz', ['name']);

    self::assertCount(0, $result);
  }

  #[Test]
  public function testSearchIgnoresNullFieldValues(): void
  {
    $items = [
      (object) ['name' => 'Alice', 'description' => null],
      (object) ['name' => 'Bob', 'description' => 'A great user'],
    ];

    $result = CollectionSearcher::search($items, 'great', ['name', 'description']);

    self::assertCount(1, $result);
    self::assertSame('Bob', $result[0]->name);
  }

  #[Test]
  public function testSearchHandlesUndefinedProperties(): void
  {
    $items = [
      (object) ['name' => 'Alice'],
      (object) ['name' => 'Bob'],
    ];

    $result = CollectionSearcher::search($items, 'Alice', ['name', 'nonexistent']);

    self::assertCount(1, $result);
    self::assertSame('Alice', $result[0]->name);
  }
  // #endregion
}
