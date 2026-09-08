<?php

declare(strict_types=1);

namespace Compliance\Application\UseCase\Command\Snapshot\CreateSafetyRegisterSnapshot;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase CreateSafetyRegisterSnapshotCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateSafetyRegisterSnapshotCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param ?string $facilityId the facility identifier, or null for an organization-wide register
   * @param string $userId the requesting user identifier
   */
  public function __construct(
    public string $organizationId,
    public ?string $facilityId,
    public string $userId,
  ) {
  }
  // #endregion
}
