<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\NonConformity\ListOrganizationNonConformities;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase OrganizationNonConformityResult.
 *
 * Row shape for the organization-wide non-conformity collection: the same
 * fields as {@see \Inspection\Application\UseCase\Query\NonConformity\ListNonConformities\NonConformityResult}
 * plus the equipment the parent inspection was performed on, resolved once
 * per page rather than per row (the aggregate itself only knows its
 * inspection; equipment is the inspection's concern).
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationNonConformityResult implements ResultMessage
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
    public ?string $equipmentId,
    public ?string $equipmentSerialNumber,
  ) {
  }
  // #endregion
}
