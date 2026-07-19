<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Attachment\ListInspectionAttachments;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListInspectionAttachmentsQuery.
 *
 * Lists either the inspection-level attachments of an inspection, or —
 * when `nonConformityId` is set — the field-proof photos of one
 * non-conformity. Backs both `GET /inspections/{id}/attachments` and
 * `GET /non-conformities/{id}/attachments`.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInspectionAttachmentsQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $inspectionId,
    public ?string $nonConformityId = null,
  ) {
  }
  // #endregion
}
