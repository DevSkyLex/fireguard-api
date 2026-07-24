<?php

declare(strict_types=1);

namespace Inspection\Application\Port\Outbound;

use Inspection\Domain\Model\Attachment\InspectionAttachment;
use Inspection\Domain\ValueObject\{InspectionAttachmentId, InspectionId, NonConformityId};

/**
 * Port InspectionAttachmentRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InspectionAttachmentRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists an inspection attachment.
   *
   * @since 1.0.0
   *
   * @param InspectionAttachment $attachment the attachment
   */
  public function save(InspectionAttachment $attachment): void;

  /**
   * Method findById.
   *
   * Finds an attachment by identifier.
   *
   * @since 1.0.0
   *
   * @param InspectionAttachmentId $id the attachment identifier
   *
   * @return ?InspectionAttachment the attachment when found
   */
  public function findById(InspectionAttachmentId $id): ?InspectionAttachment;

  /**
   * Method findByInspectionId.
   *
   * Lists the inspection-level attachments of an inspection (i.e. those
   * NOT linked to a non-conformity).
   *
   * @since 1.0.0
   *
   * @param InspectionId $inspectionId the inspection identifier
   *
   * @return list<InspectionAttachment> the attachment list
   */
  public function findByInspectionId(InspectionId $inspectionId): array;

  /**
   * Method findByNonConformityId.
   *
   * Lists the field-proof photo attachments of a non-conformity.
   *
   * @since 1.0.0
   *
   * @param NonConformityId $nonConformityId the non-conformity identifier
   *
   * @return list<InspectionAttachment> the attachment list
   */
  public function findByNonConformityId(NonConformityId $nonConformityId): array;

  /**
   * Method delete.
   *
   * Deletes an attachment record.
   *
   * @since 1.0.0
   *
   * @param InspectionAttachmentId $id the attachment identifier
   */
  public function delete(InspectionAttachmentId $id): void;
  // #endregion
}
