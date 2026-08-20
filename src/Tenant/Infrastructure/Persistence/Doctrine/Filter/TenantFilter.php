<?php

declare(strict_types=1);

namespace Tenant\Infrastructure\Persistence\Doctrine\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use InvalidArgumentException;
use ReflectionClass;
use Shared\Infrastructure\Doctrine\Attribute\TenantFilterExempt;

use function sprintf;
use function trim;

/**
 * Filter TenantFilter.
 *
 * Doctrine SQL filter that enforces
 * tenant isolation on business entities exposing
 * a tenantId field.
 *
 * Records marked {@see TenantFilterExempt} are left
 * untouched: the identity and authorization tables
 * must resolve whatever tenant the current request
 * is scoped to, otherwise the caller loses its own
 * grants and every permission-gated endpoint answers
 * 403.
 *
 * @category Doctrine Filter
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TenantFilter extends SQLFilter
{
  /**
   * Property exemptions.
   *
   * Memoized {@see TenantFilterExempt} lookups,
   * keyed by entity class name.
   *
   * @since 1.1.0
   *
   * @var array<class-string, bool>
   */
  private static array $exemptions = [];

  /**
   * Method addFilterConstraint.
   *
   * Adds a tenant_id constraint when the entity
   * exposes a tenantId field, is not exempt, and
   * the filter has a tenant_id parameter.
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

    if (self::isExempt($targetEntity)) {
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

  /**
   * Method isExempt.
   *
   * Tells whether the entity opts out of tenant
   * isolation through {@see TenantFilterExempt}.
   *
   * @since 1.1.0
   *
   * @param ClassMetadata<object> $targetEntity the target entity metadata
   *
   * @return bool true when the entity is exempt
   */
  private static function isExempt(ClassMetadata $targetEntity): bool
  {
    $class = $targetEntity->getName();

    if (!isset(self::$exemptions[$class])) {
      self::$exemptions[$class] = [] !== new ReflectionClass($class)
        ->getAttributes(TenantFilterExempt::class);
    }

    return self::$exemptions[$class];
  }
}
