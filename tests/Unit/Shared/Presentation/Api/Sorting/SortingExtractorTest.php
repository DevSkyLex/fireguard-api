<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Presentation\Api\Sorting;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Presentation\Api\Sorting\SortingExtractor;

/**
 * Test SortingExtractorTest.
 *
 * @category Sorting Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SortingExtractor::class)]
final class SortingExtractorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testFromContextReturnsDefaultWhenNoFilters(): void
  {
    $sorting = SortingExtractor::fromContext([], ['name', 'createdAt'], 'name');

    self::assertSame('name', $sorting->field);
    self::assertSame(SortDirection::ASC, $sorting->direction);
  }

  #[Test]
  public function testFromContextReturnsDefaultWhenNoOrderFilter(): void
  {
    $context = ['filters' => ['page' => 1]];
    $sorting = SortingExtractor::fromContext($context, ['name', 'createdAt'], 'name');

    self::assertSame('name', $sorting->field);
    self::assertSame(SortDirection::ASC, $sorting->direction);
  }

  #[Test]
  public function testFromContextExtractsValidAscOrder(): void
  {
    $context = ['filters' => ['order' => ['name' => 'asc']]];
    $sorting = SortingExtractor::fromContext($context, ['name', 'createdAt'], 'createdAt');

    self::assertSame('name', $sorting->field);
    self::assertSame(SortDirection::ASC, $sorting->direction);
  }

  #[Test]
  public function testFromContextExtractsValidDescOrder(): void
  {
    $context = ['filters' => ['order' => ['createdAt' => 'desc']]];
    $sorting = SortingExtractor::fromContext($context, ['name', 'createdAt'], 'name');

    self::assertSame('createdAt', $sorting->field);
    self::assertSame(SortDirection::DESC, $sorting->direction);
  }

  #[Test]
  public function testFromContextIgnoresDisallowedField(): void
  {
    $context = ['filters' => ['order' => ['hackedField' => 'asc']]];
    $sorting = SortingExtractor::fromContext($context, ['name', 'createdAt'], 'name');

    self::assertSame('name', $sorting->field);
    self::assertSame(SortDirection::ASC, $sorting->direction);
  }

  #[Test]
  public function testFromContextUsesFirstValidField(): void
  {
    $context = ['filters' => ['order' => ['unknown' => 'asc', 'createdAt' => 'desc']]];
    $sorting = SortingExtractor::fromContext($context, ['name', 'createdAt'], 'name');

    self::assertSame('createdAt', $sorting->field);
    self::assertSame(SortDirection::DESC, $sorting->direction);
  }

  #[Test]
  public function testFromContextHandlesCaseInsensitiveDirection(): void
  {
    $context = ['filters' => ['order' => ['name' => 'DESC']]];
    $sorting = SortingExtractor::fromContext($context, ['name'], 'name');

    self::assertSame('name', $sorting->field);
    self::assertSame(SortDirection::DESC, $sorting->direction);
  }

  #[Test]
  public function testFromContextFallsBackToDefaultDirectionOnInvalidValue(): void
  {
    $context = ['filters' => ['order' => ['name' => 'invalid']]];
    $sorting = SortingExtractor::fromContext($context, ['name'], 'name', SortDirection::DESC);

    self::assertSame('name', $sorting->field);
    self::assertSame(SortDirection::DESC, $sorting->direction);
  }

  #[Test]
  public function testFromContextUsesCustomDefaultDirection(): void
  {
    $sorting = SortingExtractor::fromContext([], ['name'], 'name', SortDirection::DESC);

    self::assertSame('name', $sorting->field);
    self::assertSame(SortDirection::DESC, $sorting->direction);
  }

  #[Test]
  public function testFromContextHandlesNonArrayFilters(): void
  {
    $context = ['filters' => 'invalid'];
    $sorting = SortingExtractor::fromContext($context, ['name'], 'name');

    self::assertSame('name', $sorting->field);
    self::assertSame(SortDirection::ASC, $sorting->direction);
  }

  #[Test]
  public function testFromContextHandlesNonArrayOrder(): void
  {
    $context = ['filters' => ['order' => 'invalid']];
    $sorting = SortingExtractor::fromContext($context, ['name'], 'name');

    self::assertSame('name', $sorting->field);
    self::assertSame(SortDirection::ASC, $sorting->direction);
  }

  #[Test]
  public function testFromContextHandlesNonStringDirectionValue(): void
  {
    $context = ['filters' => ['order' => ['name' => 123]]];
    $sorting = SortingExtractor::fromContext($context, ['name'], 'name');

    self::assertSame('name', $sorting->field);
    self::assertSame(SortDirection::ASC, $sorting->direction);
  }
  // #endregion
}
