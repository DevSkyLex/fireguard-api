<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Media\PrepareEquipmentMediaUpload;

use Equipment\Application\Port\Outbound\EquipmentMediaReadPort;
use Equipment\Application\Port\Outbound\Internal\CanonicalEquipmentRepositoryPort;
use Equipment\Application\Service\EquipmentMediaAccess;
use Equipment\Domain\Exception\{EquipmentMediaIdentityConflictException, EquipmentMediaNotFoundException};
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentRecordStatus};
use Shared\Application\Message\QueryHandler;

/** Preserves write authorization before upload replay or file validation. @category UseCase @version 1.0.0 @author Valentin FORTIN <contact@valentin-fortin.pro> */
final readonly class PrepareEquipmentMediaUploadHandler implements QueryHandler
{
  /**
   * @since 1.0.0
   */
  public function __construct(private CanonicalEquipmentRepositoryPort $equipment, private EquipmentMediaReadPort $media, private EquipmentMediaAccess $access)
  {
  }

  /**
   * @since 1.0.0
   */
  public function __invoke(PrepareEquipmentMediaUploadQuery $query): PrepareEquipmentMediaUploadResult
  {
    $equipment = $this->equipment->findById(EquipmentId::fromString($query->equipmentId))
      ?? throw new EquipmentMediaNotFoundException('Equipment not found.');
    $organizationId = (string) $equipment->organizationId();
    $interventionId = $query->interventionId ?? (EquipmentRecordStatus::DRAFT === $equipment->recordStatus() ? $equipment->interventionId() : null);
    $this->access->assertWrite($query->equipmentId, $organizationId, $equipment->interventionId(), $interventionId, $query->userId, forUpdate: false);
    $replay = null === $query->clientId || '' === $query->clientId ? null : $this->media->find($query->clientId);
    if (null !== $replay && $replay->equipmentId !== $query->equipmentId) {
      throw new EquipmentMediaIdentityConflictException('Media client UUID is already assigned to another equipment.');
    }

    return new PrepareEquipmentMediaUploadResult($query->equipmentId, $organizationId, $interventionId, $replay);
  }
}
