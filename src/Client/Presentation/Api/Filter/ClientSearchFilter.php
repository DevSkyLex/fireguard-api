<?php

declare(strict_types=1);

namespace Client\Presentation\Api\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * Filter ClientSearchFilter
 * @final
 *
 * Searches clients by name.
 *
 * @category Filter
 * @package Client\Presentation\Api\Filter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ClientSearchFilter extends AbstractFilter
{
  //#region Methods
  /**
   * Method filterProperty
   * {@inheritDoc}
   *
   * Filters clients by name.
   *
   * @access protected
   * @since 1.0.0
   *
   * @param string $property The property name.
   * @param mixed $value The property value.
   * @param QueryBuilder $queryBuilder The query builder.
   * @param QueryNameGeneratorInterface $queryNameGenerator The query name generator.
   * @param string $resourceClass The resource class.
   * @param Operation|null $operation The operation.
   * @param array<string, mixed> $context The context.
   *
   * @return void No return value.
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
    if ($property !== 'search' || !is_string($value) || $value === '') {
      return;
    }

    $parameterName = $queryNameGenerator->generateParameterName(name: 'search');
    $queryBuilder
      ->andWhere(sprintf('LOWER(o.name) LIKE LOWER(:%s)', $parameterName))
      ->setParameter($parameterName, '%' . $value . '%');
  }

  /**
   * Method getDescription
   * {@inheritDoc}
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $resourceClass The resource class.
   *
   * @return array<string, array<string, mixed>> The description.
   */
  public function getDescription(string $resourceClass): array
  {
    return [
      'search' => [
        'property' => 'name',
        'type' => 'string',
        'required' => false,
        'description' => 'Search clients by name (case-insensitive partial match)',
      ],
    ];
  }
  //#endregion
}
