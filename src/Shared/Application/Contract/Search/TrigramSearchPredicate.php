<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Search;

use function addcslashes;
use function array_map;
use function implode;
use function mb_strtolower;
use function sprintf;

/**
 * Class TrigramSearchPredicate
 *
 * Builds one wildcard-safe, index-aligned expression and its bound value without exposing a query builder.
 * Repositories retain ownership of column expressions and parameter names.
 *
 * @category Contract
 */
final readonly class TrigramSearchPredicate
{
  // #region Properties
  /**
   * Constant ESCAPE_CHARACTER
   *
   * States the backslash escape in the predicate explicitly, matching the wildcard escaping in its value.
   */
  private const string ESCAPE_CHARACTER = '\\';
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Keeps a predicate and its bound value together after normalization.
   *
   * @access private
   *
   * @param string $expression the parenthesized LOWER/LIKE expression
   * @param string $value the escaped, lowercased LIKE parameter
   *
   * @return void
   */
  private function __construct(public string $expression, public string $value)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method build
   *
   * Produces a grouped OR predicate, or no predicate when the term or column set is empty.
   * LOWER expressions preserve alignment with the existing trigram expression indexes.
   *
   * @access public
   *
   * @param string $parameterName the owning repository's bound parameter name
   * @param string|null $term the raw search term
   * @param string ...$columnExpressions the owning repository's trusted column expressions
   *
   * @return self|null the immutable expression and bound value, when applicable
   */
  public static function build(string $parameterName, ?string $term, string ...$columnExpressions): ?self
  {
    $normalizedTerm = new SearchCriteria($term)->normalizedTerm();
    if ([] === $columnExpressions || null === $normalizedTerm) {
      return null;
    }

    $predicates = array_map(
      static fn (string $columnExpression): string => sprintf(
        'LOWER(%s) LIKE :%s ESCAPE \'%s\'',
        $columnExpression,
        $parameterName,
        self::ESCAPE_CHARACTER,
      ),
      $columnExpressions,
    );

    return new self('(' . implode(' OR ', $predicates) . ')', self::likeValue($normalizedTerm));
  }

  /**
   * Method likeValue
   *
   * Retains Unicode lowercasing and literal percent/underscore matching for the existing bound-value contract.
   *
   * @access public
   *
   * @param string $normalizedTerm the term prepared by its caller
   *
   * @return string the wildcard-delimited LIKE value
   */
  public static function likeValue(string $normalizedTerm): string
  {
    return '%' . addcslashes(mb_strtolower($normalizedTerm), '%_') . '%';
  }
  // #endregion
}
