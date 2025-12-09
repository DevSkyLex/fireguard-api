<?php

declare(strict_types=1);

namespace Client\Presentation\Api\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * Filter ClientActiveFilter
 * @final
 *
 * Filters clients by active status.
 *
 * @category Filter
 * @package Client\Presentation\Api\Filter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ClientActiveFilter extends AbstractFilter
{
  //#region Methods
  /**
   * Method filterProperty
   * {@inheritDoc}
   *
   * @param string $property The property name.
   * @param mixed $value The filter value.
   * @param QueryBuilder $queryBuilder The query builder.
   * @param QueryNameGeneratorInterface $queryNameGenerator The query name generator.
   * @param string $resourceClass The resource class.
   * @param Operation|null $operation The operation.
   * @param array<string, mixed> $context The context.
   *
   * @return void
   */
  protected function filterProperty(
    string $property,
    mixed $value,
    QueryBuilder $queryBuilder,
    QueryNameGeneratorInterface $queryNameGenerator,
    string $resourceClass,
    ?Operation $operation = null,
    array $context = []
  ): void {
    if ($property !== 'isActive') return;
    
    $parameterName = $queryNameGenerator->generateParameterName(name: $property);
    $queryBuilder
      ->andWhere(sprintf('o.isActive = :%s', $parameterName))
      ->setParameter($parameterName, filter_var($value, FILTER_VALIDATE_BOOLEAN));
  }

  /**
   * Method getDescription
   * {@inheritDoc}
   *
   * @param string $resourceClass The resource class.
   *
   * @return array<string, array<string, mixed>> The description.
   */
  public function getDescription(string $resourceClass): array
  {
    return [
      'isActive' => [
        'property' => 'isActive',
        'type' => 'bool',
        'required' => false,
        'description' => 'Filter by active status',
      ],
    ];
  }
  //#endregion
}
