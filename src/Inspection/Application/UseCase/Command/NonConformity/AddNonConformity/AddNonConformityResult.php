<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\NonConformity\AddNonConformity;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase AddNonConformityResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddNonConformityResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public string $nonConformityId,
    public string $inspectionId,
    public string $description,
    public string $severity,
    public string $status,
    public ?string $dueAt,
    public ?string $notes,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
