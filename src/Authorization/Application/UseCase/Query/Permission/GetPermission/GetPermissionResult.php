<?php

declare(strict_types=1);

namespace Authorization\Application\UseCase\Query\Permission\GetPermission;

use Shared\Application\Message\ResultMessage;

/**
 * Result GetPermissionResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetPermissionResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the permission ID
   * @param string $name the permission name
   * @param string|null $description the permission description
   * @param string $createdAt the creation timestamp
   */
  public function __construct(
    public string $id,
    public string $name,
    public ?string $description,
    public string $createdAt,
  ) {
  }
  // #endregion
}
