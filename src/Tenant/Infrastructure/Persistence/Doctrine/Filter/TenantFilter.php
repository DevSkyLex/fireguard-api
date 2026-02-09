<?php

declare(strict_types=1);

namespace Tenant\Infrastructure\Persistence\Doctrine\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use InvalidArgumentException;

use function sprintf;
use function trim;

/**
 * Filter TenantFilter.
 *
 * Doctrine SQL filter that enforces
 * tenant isolation on entities exposing
 * a tenantId field.
 *
 * @category Doctrine Filter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TenantFilter extends SQLFilter
{
  /**
   * Method addFilterConstraint.
   *
   * Adds a tenant_id constraint when the entity
   * exposes a tenantId field and the filter
   * has a tenant_id parameter.
   *
   * @since 1.0.0
   *
   * @param ClassMetadata<object> $targetEntity the target entity metadata
   * @param string $targetTableAlias the SQL table alias
   *
   * @return string the SQL constraint or empty string when not applicable
   */
  public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
  {
    if (!$targetEntity->hasField('tenantId')) {
      return '';
    }

    try {
      $tenantId = $this->getParameter('tenant_id');
    } catch (InvalidArgumentException) {
      return '';
    }

    if ('' === trim((string) $tenantId, "'")) {
      return '';
    }

    /** @var string $column */
    $column = $targetEntity->getColumnName('tenantId');

    return sprintf('%s.%s = %s', (string) $targetTableAlias, $column, (string) $tenantId);
  }
}
