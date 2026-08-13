<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

use Intervention\Domain\Model\Attachment\InterventionAttachment;
use Intervention\Domain\ValueObject\InterventionAttachmentId;

/**
 * Port InterventionAttachmentRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionAttachmentRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists an intervention attachment.
   *
   * @since 1.0.0
   *
   * @param InterventionAttachment $attachment the attachment
   */
  public function save(InterventionAttachment $attachment): void;

  /**
   * Method findById.
   *
   * Finds an attachment by identifier.
   *
   * @since 1.0.0
   *
   * @param InterventionAttachmentId $id the attachment identifier
   *
   * @return ?InterventionAttachment the attachment when found
   */
  public function findById(InterventionAttachmentId $id): ?InterventionAttachment;

  /**
   * Method findByInterventionId.
   *
   * Lists all attachments for an intervention, optionally narrowed to those
   * scoped to a single work item.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention identifier
   * @param ?string $workItemId the optional owning work item identifier
   *
   * @return list<InterventionAttachment> the attachment list
   */
  public function findByInterventionId(string $interventionId, ?string $workItemId = null): array;

  /**
   * Method countByInterventionId.
   *
   * Counts the attachments an intervention already carries, without
   * hydrating them — the input of the
   * `AttachmentConstraints::MAX_ATTACHMENTS_PER_PARENT` cap.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention identifier
   *
   * @return int the attachment count
   */
  public function countByInterventionId(string $interventionId): int;

  /**
   * Method delete.
   *
   * Deletes an attachment record.
   *
   * @since 1.0.0
   *
   * @param InterventionAttachmentId $id the attachment identifier
   */
  public function delete(InterventionAttachmentId $id): void;
  // #endregion
}
