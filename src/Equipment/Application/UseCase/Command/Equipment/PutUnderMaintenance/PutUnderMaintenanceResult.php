<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\PutUnderMaintenance;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase PutUnderMaintenanceResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PutUnderMaintenanceResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<array{id: string, name: string, organizationId: string}> $tags
   */
  public function __construct(
    public string $equipmentId,
    public string $organizationId,
    public ?string $facilityId,
    public string $type,
    public ?string $subType,
    public ?string $brand,
    public ?string $model,
    public ?string $serialNumber,
    public ?string $locationLabel,
    public string $status,
    public ?string $installedAt,
    public ?string $commissionedAt,
    public array $tags,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
