<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\NonConformity\UpdateNonConformityStatus;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase UpdateNonConformityStatusCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateNonConformityStatusCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $inspectionId,
    public string $nonConformityId,
    public string $status,
  ) {
  }
  // #endregion
}
