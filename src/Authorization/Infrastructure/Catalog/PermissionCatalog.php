<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Catalog;

use Authorization\Application\Contract\Catalog\PermissionDefinition;
use Authorization\Application\Port\Inbound\PermissionCatalogContributionPort;
use Authorization\Application\Port\Outbound\PermissionCatalogPort;
use LogicException;

use function array_values;
use function strcmp;
use function uasort;

/**
 * Class PermissionCatalog
 *
 * Composes owner vocabulary and rejects ambiguous keys before seeding or accepting writes.
 *
 * @category Catalog
 */
final readonly class PermissionCatalog implements PermissionCatalogPort
{
  // #region Properties
  /**
   * Property entries
   *
   * @var array<string, PermissionDefinition>
   */
  private array $entries;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Accepts tagged contributors or their classes for identical runtime and compiler validation.
   *
   * @access public
   *
   * @param iterable<PermissionCatalogContributionPort|class-string<PermissionCatalogContributionPort>> $contributors owner contributions
   *
   * @return void
   *
   * @throws LogicException on duplicate keys
   */
  public function __construct(iterable $contributors)
  {
    $entries = [];
    foreach ($contributors as $contributor) {
      foreach ($contributor::definitions() as $definition) {
        if (isset($entries[$definition->name])) {
          throw new LogicException('Duplicate permission catalog key: ' . $definition->name);
        }
        $entries[$definition->name] = $definition;
      }
    }
    uasort($entries, static fn (PermissionDefinition $left, PermissionDefinition $right): int => ($left->order <=> $right->order) ?: strcmp($left->name, $right->name));
    $this->entries = $entries;
  }
  // #endregion

  // #region Methods
  /**
   * Method definitions
   *
   * @access public
   *
   * @return list<PermissionDefinition> deterministic registered vocabulary
   */
  public function definitions(): array
  {
    return array_values($this->entries);
  }

  /**
   * Method contains
   *
   * @access public
   *
   * @param string $name exact permission key
   *
   * @return bool whether registered
   */
  public function contains(string $name): bool
  {
    return isset($this->entries[$name]);
  }
  // #endregion
}
