<?php

declare(strict_types=1);

namespace Authorization\Application\Contract\Catalog;

use InvalidArgumentException;

use function array_map;
use function array_unique;
use function count;
use function preg_match;

/**
 * Class RoleDefinition
 *
 * Publishes bootstrap policy; custom persisted roles remain independent.
 *
 * @category Contract
 */
final readonly class RoleDefinition
{
  // #region Properties
  /**
   * Property permissionNames
   *
   * @var list<string> grants validated by the public permission vocabulary
   */
  public array $permissionNames;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Carries seed metadata and default assignment policy without persistence dependencies.
   *
   * @access public
   *
   * @param string $name persisted role key
   * @param string $description display description
   * @param list<string> $permissionNames registered permission keys in grant order
   * @param bool $isSystem whether deletion is protected
   * @param string|null $reference optional fixture reference
   * @param bool $defaultForNewUsers whether assigned after account creation
   * @param int $order stable seed position
   *
   * @return void
   */
  public function __construct(
    public string $name,
    public string $description,
    array $permissionNames,
    public bool $isSystem = true,
    public ?string $reference = null,
    public bool $defaultForNewUsers = false,
    public int $order = 0,
  ) {
    if (1 !== preg_match('/^[a-z0-9_]{3,50}$/', $name)) {
      throw new InvalidArgumentException('Invalid contributed role name: ' . $name);
    }
    if (count($permissionNames) !== count(array_unique($permissionNames))) {
      throw new InvalidArgumentException('Duplicate permission in contributed role: ' . $name);
    }
    $this->permissionNames = array_map(static fn (string $permissionName): string => new PermissionDefinition($permissionName, '')->name, $permissionNames);
  }
  // #endregion

}
