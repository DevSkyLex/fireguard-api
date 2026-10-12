<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Authorization;

use InvalidArgumentException;

use function preg_match;

/**
 * Class OrganizationPermissionDefinition
 *
 * Publishes one stable organization-scoped permission and its presentation metadata.
 *
 * @category Contract
 */
final readonly class OrganizationPermissionDefinition
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param list<string> $aliases historical authorization equivalents; new grants still use the canonical name
   */
  public function __construct(public string $name, public string $description, public int $position = 0, public array $aliases = [])
  {
    if (1 !== preg_match('/^organization\.(?:[a-z_]+\.)*(?:[a-z_]+|\*)$/D', $name)) {
      throw new InvalidArgumentException('Invalid organization permission key.');
    }
    foreach ($aliases as $alias) {
      if (1 !== preg_match('/^(?:[a-z_]+|\*)(?:\.(?:[a-z_]+|\*))*$/D', $alias)) {
        throw new InvalidArgumentException('Invalid historical organization permission alias.');
      }
    }
  }
  // #endregion
}
