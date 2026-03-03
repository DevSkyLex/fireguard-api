<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\CreateInspection;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase CreateInspectionResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateInspectionResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   */
  public function __construct(
    public string $inspectionId,
    public string $organizationId,
    public string $equipmentId,
    public ?string $facilityId,
    public string $result,
    public string $status,
    public string $performedAt,
    public string $inspectorType,
    public string $inspectorName,
    public ?string $inspectorUserId,
    public ?string $inspectorOrganizationName,
    public ?string $checklistId,
    public ?string $notes,
    public ?string $signature,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
