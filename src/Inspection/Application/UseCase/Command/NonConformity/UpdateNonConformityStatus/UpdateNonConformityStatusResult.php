<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\NonConformity\UpdateNonConformityStatus;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase UpdateNonConformityStatusResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateNonConformityStatusResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public string $nonConformityId,
    public string $inspectionId,
    public string $description,
    public string $severity,
    public string $status,
    public ?string $dueAt,
    public ?string $resolvedAt,
    public ?string $notes,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
