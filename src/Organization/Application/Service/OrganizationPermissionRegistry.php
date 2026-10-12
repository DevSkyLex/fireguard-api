<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use LogicException;
use Organization\Application\Contract\Authorization\OrganizationPermissionDefinition;
use Organization\Application\Port\Inbound\OrganizationPermissionCatalogPort;
use Organization\Application\Port\Outbound\OrganizationPermissionContributionPort;
use Shared\Domain\Exception\InvalidValueException;

use function array_values;
use function sprintf;
use function uasort;

/**
 * Class OrganizationPermissionRegistry
 *
 * Composes permission vocabulary from pure manifests while rejecting duplicate ownership.
 *
 * @category Service
 */
final readonly class OrganizationPermissionRegistry implements OrganizationPermissionCatalogPort
{
  // #region Properties
  /**
   * @var array<string, OrganizationPermissionDefinition>
   */
  private array $permissions;

  /**
   * @var array<string, string> owner-declared historical authorization equivalents
   */
  private array $aliases;

  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param iterable<OrganizationPermissionContributionPort> $contributions registered vocabulary owners
   */
  public function __construct(iterable $contributions)
  {
    $permissions = [];
    foreach ($contributions as $contribution) {
      foreach ($contribution::definitions() as $definition) {
        if (isset($permissions[$definition->name])) {
          throw new LogicException(sprintf('Duplicate organization permission "%s".', $definition->name));
        }
        $permissions[$definition->name] = $definition;
      }
    }
    uasort($permissions, static fn (OrganizationPermissionDefinition $left, OrganizationPermissionDefinition $right): int => [$left->position, $left->name] <=> [$right->position, $right->name]);
    $this->permissions = $permissions;
    $aliases = [];
    foreach ($permissions as $definition) {
      foreach ($definition->aliases as $alias) {
        if (isset($permissions[$alias]) || isset($aliases[$alias])) {
          throw new LogicException(sprintf('Duplicate organization permission alias "%s".', $alias));
        }
        $aliases[$alias] = $definition->name;
      }
    }
    $this->aliases = $aliases;
  }

  // #endregion

  // #region Methods
  /**
   * Method definitions
   *
   * @access public
   *
   * @return list<OrganizationPermissionDefinition>
   */
  public function definitions(): array
  {
    return array_values($this->permissions);
  }

  /**
   * Method contains
   *
   * @access public
   *
   * @return bool
   */
  public function contains(string $name): bool
  {
    return isset($this->permissions[$name]);
  }

  /**
   * {@inheritdoc}
   */
  public function canonicalName(string $name): ?string
  {
    return isset($this->permissions[$name]) ? $name : ($this->aliases[$name] ?? null);
  }

  /**
   * Method descriptionFor
   *
   * @access public
   *
   * @return string
   */
  public function descriptionFor(string $name): string
  {
    return $this->permissions[$name]->description ?? '';
  }

  /**
   * Method assertKnown
   *
   * @access public
   *
   * @param list<string> $permissions
   */
  public function assertKnown(array $permissions): void
  {
    foreach ($permissions as $permission) {
      if (!$this->contains($permission)) {
        throw InvalidValueException::because(sprintf('Unknown organization permission "%s".', $permission));
      }
    }
  }
  // #endregion
}
