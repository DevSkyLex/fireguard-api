<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Presentation\Api\Search;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Presentation\Api\Search\SearchExtractor;

/**
 * Test SearchExtractorTest.
 *
 * @category Search Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SearchExtractor::class)]
final class SearchExtractorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testFromContextReturnsNullWhenNoFilters(): void
  {
    self::assertNull(SearchExtractor::fromContext([]));
  }

  #[Test]
  public function testFromContextReturnsNullWhenNoSearchFilter(): void
  {
    $context = ['filters' => ['page' => 1]];

    self::assertNull(SearchExtractor::fromContext($context));
  }

  #[Test]
  public function testFromContextReturnsNullWhenSearchIsEmpty(): void
  {
    $context = ['filters' => ['search' => '']];

    self::assertNull(SearchExtractor::fromContext($context));
  }

  #[Test]
  public function testFromContextReturnsNullWhenSearchIsWhitespace(): void
  {
    $context = ['filters' => ['search' => '   ']];

    self::assertNull(SearchExtractor::fromContext($context));
  }

  #[Test]
  public function testFromContextReturnsNullWhenSearchIsNotString(): void
  {
    $context = ['filters' => ['search' => 123]];

    self::assertNull(SearchExtractor::fromContext($context));
  }

  #[Test]
  public function testFromContextReturnsNullWhenFiltersIsNotArray(): void
  {
    $context = ['filters' => 'invalid'];

    self::assertNull(SearchExtractor::fromContext($context));
  }

  #[Test]
  public function testFromContextReturnsTrimmedSearchTerm(): void
  {
    $context = ['filters' => ['search' => '  hello  ']];

    self::assertSame('hello', SearchExtractor::fromContext($context));
  }

  #[Test]
  public function testFromContextReturnsSearchTermAsIs(): void
  {
    $context = ['filters' => ['search' => 'alice']];

    self::assertSame('alice', SearchExtractor::fromContext($context));
  }

  #[Test]
  public function testFromContextPreservesSearchTermCase(): void
  {
    $context = ['filters' => ['search' => 'Alice']];

    self::assertSame('Alice', SearchExtractor::fromContext($context));
  }

  #[Test]
  public function testFromContextReturnsNullWhenSearchIsNull(): void
  {
    $context = ['filters' => ['search' => null]];

    self::assertNull(SearchExtractor::fromContext($context));
  }
  // #endregion
}
