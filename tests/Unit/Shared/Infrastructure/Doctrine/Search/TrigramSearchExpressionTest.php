<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Doctrine\Search;

use Doctrine\ORM\QueryBuilder;
use Equipment\Infrastructure\Doctrine\Search\TrigramSearchExpression as EquipmentExpression;
use Facility\Infrastructure\Doctrine\Search\TrigramSearchExpression as FacilityExpression;
use Inspection\Infrastructure\Doctrine\Search\TrigramSearchExpression as InspectionExpression;
use Intervention\Infrastructure\Doctrine\Search\TrigramSearchExpression as InterventionExpression;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
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

  /**
   * Method ownedFacades
   *
   * Covers each owned Doctrine bridge without permitting a production dependency on foreign infrastructure.
   *
   * @access public
   *
   * @return array<string, array{class-string<TrigramSearchExpression|EquipmentExpression|FacilityExpression|InspectionExpression|InterventionExpression>}> the existing facade entry points
   */
  public static function ownedFacades(): array
  {
    return [
      'Shared' => [TrigramSearchExpression::class],
      'Equipment' => [EquipmentExpression::class],
      'Facility' => [FacilityExpression::class],
      'Inspection' => [InspectionExpression::class],
      'Intervention' => [InterventionExpression::class],
    ];
  }

  /**
   * Method ownedFacadesBindTheSameGroupedPredicateAndValue
   *
   * Protects the public facade signatures and exact expression and parameter binding across all five owners.
   *
   * @access public
   *
   * @param class-string<TrigramSearchExpression|EquipmentExpression|FacilityExpression|InspectionExpression|InterventionExpression> $facade the owned Doctrine facade
   *
   * @return void
   */
  #[Test]
  #[DataProvider('ownedFacades')]
  public function ownedFacadesBindTheSameGroupedPredicateAndValue(string $facade): void
  {
    $builder = $this->createMock(QueryBuilder::class);
    $builder->expects(self::once())->method('andWhere')->with("(LOWER(e.brand) LIKE :search ESCAPE '\\' OR LOWER(e.model) LIKE :search ESCAPE '\\')")->willReturnSelf();
    $builder->expects(self::once())->method('setParameter')->with('search', '%éclair 50\\%\\_%')->willReturnSelf();

    $facade::applyCriteria($builder, 'search', new SearchCriteria('  ÉCLAIR 50%_  '), 'e.brand', 'e.model');
    self::assertSame('%éclair 50\\%\\_%', $facade::likeValue('ÉCLAIR 50%_'));
  }

  /**
   * Method ownedFacadesKeepEmptyTermsAndColumnsAsNoOps
   *
   * Blank criteria and empty target columns neither append an expression nor bind an unused parameter.
   *
   * @access public
   *
   * @param class-string<TrigramSearchExpression|EquipmentExpression|FacilityExpression|InspectionExpression|InterventionExpression> $facade the owned Doctrine facade
   *
   * @return void
   */
  #[Test]
  #[DataProvider('ownedFacades')]
  public function ownedFacadesKeepEmptyTermsAndColumnsAsNoOps(string $facade): void
  {
    $builder = $this->createMock(QueryBuilder::class);
    $builder->expects(self::never())->method('andWhere');
    $builder->expects(self::never())->method('setParameter');

    $facade::apply($builder, 'search', null, 'e.brand');
    $facade::applyCriteria($builder, 'search', new SearchCriteria(" \t "), 'e.brand');
    $facade::apply($builder, 'search', 'Fire');
  }
  // #endregion
}
