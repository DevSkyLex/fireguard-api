<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Catalog;

use Authorization\Application\Contract\Catalog\RoleDefinition;
use Authorization\Application\Port\Inbound\RoleCatalogContributionPort;
use Authorization\Application\Port\Outbound\{PermissionCatalogPort, RoleCatalogPort};
use LogicException;

use function array_values;
use function strcmp;
use function uasort;

/**
 * Class RoleCatalog
 *
 * Composes bootstrap policy and rejects ambiguous roles, fixture references and defaults.
 *
 * @category Catalog
 */
final readonly class RoleCatalog implements RoleCatalogPort
{
  // #region Properties
  /**
   * Property entries
   *
   * @var array<string, RoleDefinition>
   */
  private array $entries;

  /**
   * Property defaultName
   */
  private ?string $defaultName;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Uses the same validation for runtime contributors and compiler-provided classes.
   *
   * @access public
   *
   * @param iterable<RoleCatalogContributionPort|class-string<RoleCatalogContributionPort>> $contributors owner contributions
   *
   * @return void
   *
   * @throws LogicException on ambiguous bootstrap policy
   */
  public function __construct(iterable $contributors)
  {
    $entries = [];
    $references = [];
    $defaultName = null;
    foreach ($contributors as $contributor) {
      foreach ($contributor::definitions() as $definition) {
        if (isset($entries[$definition->name])) {
          throw new LogicException('Duplicate role catalog key: ' . $definition->name);
        }
        $this->registerReference($definition, $references);
        $defaultName = $this->registerDefault($definition, $defaultName);
        $entries[$definition->name] = $definition;
      }
    }
    uasort($entries, static fn (RoleDefinition $left, RoleDefinition $right): int => ($left->order <=> $right->order) ?: strcmp($left->name, $right->name));
    $this->entries = $entries;
    $this->defaultName = $defaultName;
  }

  /**
   * Method definitions
   *
   * @access public
   *
   * @return list<RoleDefinition> deterministic bootstrap definitions
   */
  public function definitions(): array
  {
    return array_values($this->entries);
  }

  /**
   * Method defaultRoleName
   *
   * @access public
   *
   * @return string|null single registered default
   */
  public function defaultRoleName(): ?string
  {
    return $this->defaultName;
  }

  /**
   * Method validatePermissions
   *
   * Lets composition reject unresolved grants before fixtures or commands run.
   *
   * @access public
   *
   * @param PermissionCatalogPort $permissions composed vocabulary
   *
   * @return void
   *
   * @throws LogicException on unregistered grants
   */
  public function validatePermissions(PermissionCatalogPort $permissions): void
  {
    foreach ($this->entries as $definition) {
      foreach ($definition->permissionNames as $name) {
        if (!$permissions->contains($name)) {
          throw new LogicException('Unknown permission catalog key ' . $name . ' in role ' . $definition->name);
        }
      }
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method registerReference
   *
   * Rejects duplicate fixture ownership without changing catalog ordering.
   *
   * @access private
   *
   * @param RoleDefinition $definition the contributed role
   * @param array<string, true> $references selected fixture references
   *
   * @return void
   */
  private function registerReference(RoleDefinition $definition, array &$references): void
  {
    if (null !== $definition->reference) {
      if (isset($references[$definition->reference])) {
        throw new LogicException('Duplicate role fixture reference: ' . $definition->reference);
      }
      $references[$definition->reference] = true;
    }
  }

  /**
   * Method registerDefault
   *
   * Keeps one explicit default assignment across selected contributors.
   *
   * @access private
   *
   * @param RoleDefinition $definition the contributed role
   * @param string|null $defaultName the already registered default
   *
   * @return string|null the single selected default
   */
  private function registerDefault(RoleDefinition $definition, ?string $defaultName): ?string
  {
    if ($definition->defaultForNewUsers) {
      if (null !== $defaultName) {
        throw new LogicException('Multiple default role catalog contributions.');
      }
      $defaultName = $definition->name;
    }

    return $defaultName;
  }
  // #endregion
}
