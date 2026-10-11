<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Doctrine\Search;

use Doctrine\ORM\QueryBuilder;
use Shared\Application\Contract\Search\{SearchCriteria, TrigramSearchPredicate};

/**
 * Service TrigramSearchExpression.
 *
 * Binds the shared pure search predicate to Equipment-owned query builders.
 * Column selection remains local; normalization and wildcard escaping have one shared implementation.
 *
 * @category Search
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TrigramSearchExpression
{
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
    $predicate = TrigramSearchPredicate::build($paramName, $term, ...$columnExpressions);
    if (null !== $predicate) {
      $queryBuilder->andWhere($predicate->expression)->setParameter($paramName, $predicate->value);
    }
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
    return TrigramSearchPredicate::likeValue($normalizedTerm);
  }

  // #endregion
}
