<?php

declare(strict_types=1);

namespace Facility\Application\Port\Outbound;

use Facility\Domain\Model\Attachment\FacilityAttachment;
use Facility\Domain\ValueObject\{FacilityAttachmentId, FacilityId};

/**
 * Port FacilityAttachmentRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FacilityAttachmentRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists a facility attachment.
   *
   * @since 1.0.0
   *
   * @param FacilityAttachment $attachment the attachment
   */
  public function save(FacilityAttachment $attachment): void;

  /**
   * Method findById.
   *
   * Finds an attachment by identifier.
   *
   * @since 1.0.0
   *
   * @param FacilityAttachmentId $id the attachment identifier
   *
   * @return ?FacilityAttachment the attachment when found
   */
  public function findById(FacilityAttachmentId $id): ?FacilityAttachment;

  /**
   * Method findByFacilityId.
   *
   * Lists all attachments for a facility.
   *
   * @since 1.0.0
   *
   * @param FacilityId $facilityId the facility identifier
   *
   * @return list<FacilityAttachment> the attachment list
   */
  public function findByFacilityId(FacilityId $facilityId): array;

  /**
   * Method countByFacilityId.
   *
   * Counts the attachments a facility already carries, without hydrating
   * them — the input of the
   * `AttachmentConstraints::MAX_ATTACHMENTS_PER_PARENT` cap.
   *
   * @since 1.0.0
   *
   * @param FacilityId $facilityId the facility identifier
   *
   * @return int the attachment count
   */
  public function countByFacilityId(FacilityId $facilityId): int;

  /**
   * Method delete.
   *
   * Deletes an attachment record.
   *
   * @since 1.0.0
   *
   * @param FacilityAttachmentId $id the attachment identifier
   */
  public function delete(FacilityAttachmentId $id): void;
  // #endregion
}
