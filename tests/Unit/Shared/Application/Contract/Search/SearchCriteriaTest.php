<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Application\Contract\Search;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Search\SearchCriteria;

/**
 * Test SearchCriteriaTest.
 *
 * @category Search Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SearchCriteria::class)]
final class SearchCriteriaTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testIsEmptyIsTrueForNullTerm(): void
  {
    $criteria = new SearchCriteria();

    self::assertTrue($criteria->isEmpty());
    self::assertNull($criteria->normalizedTerm());
  }

  #[Test]
  public function testIsEmptyIsTrueForBlankTerm(): void
  {
    $criteria = new SearchCriteria('   ');

    self::assertTrue($criteria->isEmpty());
    self::assertNull($criteria->normalizedTerm());
  }

  #[Test]
  public function testNormalizedTermTrimsWhitespace(): void
  {
    $criteria = new SearchCriteria('  serial-42  ');

    self::assertFalse($criteria->isEmpty());
    self::assertSame('serial-42', $criteria->normalizedTerm());
  }

  #[Test]
  public function testNormalizedTermPreservesInternalWhitespace(): void
  {
    $criteria = new SearchCriteria('fire extinguisher');

    self::assertSame('fire extinguisher', $criteria->normalizedTerm());
  }
  // #endregion
}
