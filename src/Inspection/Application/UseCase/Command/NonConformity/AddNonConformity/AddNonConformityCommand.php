<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\NonConformity\AddNonConformity;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase AddNonConformityCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddNonConformityCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $inspectionId,
    public string $description,
    public string $severity,
    public ?string $dueAt = null,
    public ?string $notes = null,
  ) {
  }
  // #endregion
}
