<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

use Intervention\Domain\Exception\InterventionConflictException;
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
   * Method findSignatureByInterventionId.
   *
   * Finds the intervention's current completion signature attachment, when
   * one exists. At most one `signature`-kind attachment exists per
   * intervention — the input of the replace-on-reupload semantics in
   * `AddInterventionAttachmentHandler`.
   *
   * @since 1.2.0
   *
   * @param string $interventionId the intervention identifier
   *
   * @return ?InterventionAttachment the signature attachment when one exists
   */
  public function findSignatureByInterventionId(string $interventionId): ?InterventionAttachment;

  /**
   * Method hasSignature.
   *
   * Cheap existence check for the intervention's completion signature — the
   * input of `InterventionOutput::$hasSignature`.
   *
   * @since 1.2.0
   *
   * @param string $interventionId the intervention identifier
   *
   * @return bool whether the intervention carries a completion signature
   */
  public function hasSignature(string $interventionId): bool;

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

  /**
   * Method saveReplacingSignature.
   *
   * Persists a `signature`-kind attachment, atomically removing the
   * intervention's previous signature record (when one is given) in the
   * SAME database transaction — required by the
   * `uniq_intervention_attachment_signature` partial unique index
   * (`(intervention_id) WHERE kind = 'signature'`): saving the new row
   * BEFORE deleting the old one, as the plain `save()` path does, would
   * violate that constraint whenever a previous signature still exists.
   *
   * @since 1.3.0
   *
   * @param InterventionAttachment $attachment the new signature attachment to persist
   * @param ?InterventionAttachmentId $previousSignatureId the previous signature's identifier, when one exists
   *
   * @throws InterventionConflictException when the unique constraint is still violated
   *                                       (a genuine concurrent duplicate)
   */
  public function saveReplacingSignature(InterventionAttachment $attachment, ?InterventionAttachmentId $previousSignatureId): void;
  // #endregion
}
