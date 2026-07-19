<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Doctrine\Search;

use Doctrine\ORM\QueryBuilder;
use Shared\Application\Contract\Search\SearchCriteria;

use function addcslashes;
use function array_map;
use function implode;
use function mb_strtolower;
use function sprintf;
use function trim;

/**
 * Service TrigramSearchExpression.
 *
 * Centralizes the free-text search predicate pushed down into repository
 * query builders. Emits a case-insensitive `LOWER(<col>) LIKE :<param>` OR
 * clause across the given column expressions, with the search term properly
 * escaped for the `%`/`_` LIKE wildcards, so every collection shares a single
 * wildcard-safe, index-aligned predicate (the `lower(col)` shape matches the
 * `gin_trgm_ops` expression indexes introduced separately). Emits no
 * predicate at all when the term is null/blank.
 *
 * @category Search
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TrigramSearchExpression
{
  /**
   * The DQL escape character declared on every `LIKE` predicate this builder
   * emits, matching the backslash used by {@see self::likeValue()}.
   *
   * Explicitly declaring `ESCAPE '\'` (rather than relying on the platform
   * default) is required for portability: PostgreSQL defaults its `LIKE`
   * escape character to backslash, but SQLite (used by the test suite) does
   * NOT treat backslash as an escape character unless an `ESCAPE` clause is
   * present, so omitting it would silently break wildcard-safe matching on
   * SQLite while working by accident on PostgreSQL.
   */
  private const string ESCAPE_CHARACTER = '\\';

  // #region Methods
  /**
   * Method apply.
   *
   * Appends the wildcard-safe search predicate to the query builder when the
   * term is non-empty; does nothing otherwise.
   *
   * @since 1.0.0
   *
   * @param QueryBuilder $queryBuilder the query builder to mutate
   * @param string $paramName the bound parameter name
   * @param ?string $term the raw search term (null/blank = no predicate)
   * @param string ...$columnExpressions the column expressions to search (e.g. 'e.serialNumber')
   */
  public static function apply(QueryBuilder $queryBuilder, string $paramName, ?string $term, string ...$columnExpressions): void
  {
    if ([] === $columnExpressions) {
      return;
    }

    $normalizedTerm = self::normalize($term);
    if (null === $normalizedTerm) {
      return;
    }

    $predicates = array_map(
      static fn (string $columnExpression): string => sprintf(
        'LOWER(%s) LIKE :%s ESCAPE \'%s\'',
        $columnExpression,
        $paramName,
        self::ESCAPE_CHARACTER,
      ),
      $columnExpressions,
    );

    $queryBuilder
      ->andWhere('(' . implode(' OR ', $predicates) . ')')
      ->setParameter($paramName, self::likeValue($normalizedTerm));
  }

  /**
   * Method applyCriteria.
   *
   * Same as {@see self::apply()}, sourcing the term from a SearchCriteria
   * value object.
   *
   * @since 1.0.0
   *
   * @param QueryBuilder $queryBuilder the query builder to mutate
   * @param string $paramName the bound parameter name
   * @param SearchCriteria $criteria the search criteria
   * @param string ...$columnExpressions the column expressions to search
   */
  public static function applyCriteria(QueryBuilder $queryBuilder, string $paramName, SearchCriteria $criteria, string ...$columnExpressions): void
  {
    self::apply($queryBuilder, $paramName, $criteria->normalizedTerm(), ...$columnExpressions);
  }

  /**
   * Method likeValue.
   *
   * Builds the wildcard-safe `LIKE` bound value for a normalized (non-empty)
   * term: lower-cased, `%`/`_` escaped, wrapped in `%...%`.
   *
   * @since 1.0.0
   *
   * @param string $normalizedTerm the trimmed, non-empty term
   *
   * @return string the bound LIKE value
   */
  public static function likeValue(string $normalizedTerm): string
  {
    return '%' . addcslashes(mb_strtolower($normalizedTerm), '%_') . '%';
  }

  /**
   * Method normalize.
   *
   * Trims the term and collapses a blank value to null.
   *
   * @since 1.0.0
   *
   * @param ?string $term the raw term
   *
   * @return ?string the normalized term
   */
  private static function normalize(?string $term): ?string
  {
    if (null === $term) {
      return null;
    }

    $trimmed = trim($term);

    return '' !== $trimmed ? $trimmed : null;
  }
  // #endregion
}
