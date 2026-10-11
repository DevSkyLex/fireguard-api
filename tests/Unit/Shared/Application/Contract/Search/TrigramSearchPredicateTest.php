<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Application\Contract\Search;

use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Search\TrigramSearchPredicate;

/**
 * Class TrigramSearchPredicateTest
 *
 * Protects expression grouping, parameter separation and the historical wildcard escaping contract.
 *
 * @category Unit Test
 */
#[CoversClass(TrigramSearchPredicate::class)]
final class TrigramSearchPredicateTest extends TestCase
{
  // #region Methods
  #[Test]
  public function groupedPredicateKeepsTheRawTermOutOfTheExpression(): void
  {
    $predicate = TrigramSearchPredicate::build('search', "  ÉCLAIR 50%_ O'Reilly  ", 'e.brand', 'e.model');

    self::assertNotNull($predicate);
    self::assertSame("(LOWER(e.brand) LIKE :search ESCAPE '\\' OR LOWER(e.model) LIKE :search ESCAPE '\\')", $predicate->expression);
    self::assertSame("%éclair 50\\%\\_ o'reilly%", $predicate->value);
  }

  #[Test]
  public function aSingleColumnRetainsParenthesesAndTheExplicitEscape(): void
  {
    $predicate = TrigramSearchPredicate::build('labelTerm', ' Fire ', 'f.name');

    self::assertNotNull($predicate);
    self::assertSame("(LOWER(f.name) LIKE :labelTerm ESCAPE '\\')", $predicate->expression);
    self::assertSame('%fire%', $predicate->value);
  }

  /**
   * Method absentPredicates
   *
   * Provides empty terms and empty column sets that must leave query builders untouched.
   *
   * @access public
   *
   * @return array<string, array{string|null, list<string>}> the no-predicate cases
   */
  public static function absentPredicates(): array
  {
    return [
      'null term' => [null, ['e.name']],
      'empty term' => ['', ['e.name']],
      'blank term' => [" \t\n ", ['e.name']],
      'no columns' => ['Alarm', []],
    ];
  }

  /**
   * Method emptyTermsOrColumnsProduceNoPredicate
   *
   * Preserves the no-op contract for a query without a usable search term or target column.
   *
   * @access public
   *
   * @param string|null $term the raw term
   * @param list<string> $columns the owned columns
   *
   * @return void
   */
  #[Test]
  #[DataProvider('absentPredicates')]
  public function emptyTermsOrColumnsProduceNoPredicate(?string $term, array $columns): void
  {
    self::assertNull(TrigramSearchPredicate::build('search', $term, ...$columns));
  }

  /**
   * Method boundValues
   *
   * Records the existing LIKE value semantics, including Unicode and literal escape characters.
   *
   * @access public
   *
   * @return array<string, array{string, string}> normalized terms and exact expected values
   */
  public static function boundValues(): array
  {
    return [
      'percent and underscore' => ['50%_TERM', '%50\\%\\_term%'],
      'Unicode lowercase' => ['ÉCLAIR', '%éclair%'],
      'existing backslash semantics' => ['A\\B_50%', '%a\\b\\_50\\%%'],
      'direct value retains spaces' => ['  FIRE  ', '%  fire  %'],
    ];
  }

  /**
   * Method boundValuesRetainTheHistoricalContract
   *
   * Keeps direct value generation separate from criteria normalization.
   *
   * @access public
   *
   * @param string $term the term already prepared by its caller
   * @param string $expected the original escaped value
   *
   * @return void
   */
  #[Test]
  #[DataProvider('boundValues')]
  public function boundValuesRetainTheHistoricalContract(string $term, string $expected): void
  {
    self::assertSame($expected, TrigramSearchPredicate::likeValue($term));
  }
  // #endregion
}
