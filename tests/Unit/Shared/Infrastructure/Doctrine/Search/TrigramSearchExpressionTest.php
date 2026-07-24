<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Doctrine\Search;

use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Search\SearchCriteria;
use Shared\Infrastructure\Doctrine\Search\TrigramSearchExpression;

/**
 * Test TrigramSearchExpressionTest.
 *
 * @category Search Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TrigramSearchExpression::class)]
final class TrigramSearchExpressionTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testApplyBuildsOrOfLowerLikeAcrossColumns(): void
  {
    $capturedWhere = null;
    $capturedParameterName = null;
    $capturedParameterValue = null;

    $queryBuilder = $this->createMock(QueryBuilder::class);
    $queryBuilder->expects(self::once())
      ->method('andWhere')
      ->with(self::callback(function (string $expression) use (&$capturedWhere): bool {
        $capturedWhere = $expression;

        return true;
      }))
      ->willReturnSelf();
    $queryBuilder->expects(self::once())
      ->method('setParameter')
      ->with(
        self::callback(function (string $name) use (&$capturedParameterName): bool {
          $capturedParameterName = $name;

          return true;
        }),
        self::callback(function (string $value) use (&$capturedParameterValue): bool {
          $capturedParameterValue = $value;

          return true;
        }),
      )
      ->willReturnSelf();

    TrigramSearchExpression::apply($queryBuilder, 'search', 'Alice', 'e.brand', 'e.model', 'e.serialNumber');

    self::assertSame(
      "(LOWER(e.brand) LIKE :search ESCAPE '\\' OR LOWER(e.model) LIKE :search ESCAPE '\\' OR LOWER(e.serialNumber) LIKE :search ESCAPE '\\')",
      $capturedWhere,
    );
    self::assertSame('search', $capturedParameterName);
    self::assertSame('%alice%', $capturedParameterValue);
  }

  #[Test]
  public function testApplyEscapesPercentWildcardLiterally(): void
  {
    $queryBuilder = $this->createMock(QueryBuilder::class);
    $queryBuilder->method('andWhere')->willReturnSelf();

    $capturedValue = null;
    $queryBuilder->expects(self::once())
      ->method('setParameter')
      ->with('search', self::callback(function (string $value) use (&$capturedValue): bool {
        $capturedValue = $value;

        return true;
      }))
      ->willReturnSelf();

    TrigramSearchExpression::apply($queryBuilder, 'search', '50% off_all', 'm.name');

    self::assertSame('%50\\% off\\_all%', $capturedValue);
  }

  #[Test]
  public function testApplyEscapesUnderscoreWildcardLiterally(): void
  {
    $queryBuilder = $this->createMock(QueryBuilder::class);
    $queryBuilder->method('andWhere')->willReturnSelf();

    $capturedValue = null;
    $queryBuilder->expects(self::once())
      ->method('setParameter')
      ->with('name', self::callback(function (string $value) use (&$capturedValue): bool {
        $capturedValue = $value;

        return true;
      }))
      ->willReturnSelf();

    TrigramSearchExpression::apply($queryBuilder, 'name', 'a_b', 'm.name');

    self::assertSame('%a\\_b%', $capturedValue);
  }

  #[Test]
  public function testApplyIsCaseInsensitive(): void
  {
    $queryBuilder = $this->createMock(QueryBuilder::class);
    $queryBuilder->method('andWhere')->willReturnSelf();

    $capturedValue = null;
    $queryBuilder->expects(self::once())
      ->method('setParameter')
      ->with('search', self::callback(function (string $value) use (&$capturedValue): bool {
        $capturedValue = $value;

        return true;
      }))
      ->willReturnSelf();

    TrigramSearchExpression::apply($queryBuilder, 'search', 'ÉCLAIR', 'e.model');

    self::assertSame('%éclair%', $capturedValue);
  }

  #[Test]
  public function testApplyDoesNothingForNullTerm(): void
  {
    $queryBuilder = $this->createMock(QueryBuilder::class);
    $queryBuilder->expects(self::never())->method('andWhere');
    $queryBuilder->expects(self::never())->method('setParameter');

    TrigramSearchExpression::apply($queryBuilder, 'search', null, 'e.model');
  }

  #[Test]
  public function testApplyDoesNothingForBlankTerm(): void
  {
    $queryBuilder = $this->createMock(QueryBuilder::class);
    $queryBuilder->expects(self::never())->method('andWhere');
    $queryBuilder->expects(self::never())->method('setParameter');

    TrigramSearchExpression::apply($queryBuilder, 'search', '   ', 'e.model');
  }

  #[Test]
  public function testApplyDoesNothingWhenNoColumnsProvided(): void
  {
    $queryBuilder = $this->createMock(QueryBuilder::class);
    $queryBuilder->expects(self::never())->method('andWhere');
    $queryBuilder->expects(self::never())->method('setParameter');

    TrigramSearchExpression::apply($queryBuilder, 'search', 'anything');
  }

  #[Test]
  public function testApplyCriteriaDelegatesToApplyUsingNormalizedTerm(): void
  {
    $queryBuilder = $this->createMock(QueryBuilder::class);
    $queryBuilder->expects(self::once())->method('andWhere')->willReturnSelf();

    $capturedValue = null;
    $queryBuilder->expects(self::once())
      ->method('setParameter')
      ->with('search', self::callback(function (string $value) use (&$capturedValue): bool {
        $capturedValue = $value;

        return true;
      }))
      ->willReturnSelf();

    TrigramSearchExpression::applyCriteria($queryBuilder, 'search', new SearchCriteria('  Bob  '), 'm.name');

    self::assertSame('%bob%', $capturedValue);
  }

  #[Test]
  public function testApplyCriteriaDoesNothingForEmptyCriteria(): void
  {
    $queryBuilder = $this->createMock(QueryBuilder::class);
    $queryBuilder->expects(self::never())->method('andWhere');
    $queryBuilder->expects(self::never())->method('setParameter');

    TrigramSearchExpression::applyCriteria($queryBuilder, 'search', new SearchCriteria(), 'm.name');
  }

  #[Test]
  public function testLikeValueWrapsEscapedLowercasedTermWithWildcards(): void
  {
    self::assertSame('%50\\%\\_term%', TrigramSearchExpression::likeValue('50%_TERM'));
  }
  // #endregion
}
