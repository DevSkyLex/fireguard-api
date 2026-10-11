<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Attachment;

/** Immutable stored attachment projection.
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionAttachmentView
{
  public function __construct(
    public string $id,
    public string $organizationId,
    public string $inspectionId,
    public string $fileName,
    public string $mimeType,
    public int $size,
    public ?string $label,
    public int $revision,
    public string $uploadedAt,
    public ?string $nonConformityId,
  ) {
  }
}
