<?php

declare(strict_types=1);

namespace Authorization\Application\Contract\Catalog;

use InvalidArgumentException;

use function preg_match;

/**
 * Class PermissionDefinition
 *
 * Publishes an immutable vocabulary entry without framework or owner-private types.
 *
 * @category Contract
 */
final readonly class PermissionDefinition
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Rejects malformed keys. Active registration governs writes, never historical hydration.
   *
   * @access public
   *
   * @param string $name persisted permission key
   * @param string $description display description
   * @param int $order stable catalog position
   *
   * @return void
   */
  public function __construct(public string $name, public string $description, public int $order = 0)
  {
    if (1 !== preg_match('/^([a-z_]+|\\*)(\\.([a-z_]+|\\*))?$/', $name)) {
      throw new InvalidArgumentException('Invalid contributed permission name: ' . $name);
    }
  }
  // #endregion
}
