<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Attachment;

/** Immutable stored attachment projection.
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionAttachmentView
{
  public function __construct(
    public string $id,
    public string $organizationId,
    public string $interventionId,
    public string $fileName,
    public string $mimeType,
    public int $size,
    public ?string $label,
    public int $revision,
    public string $uploadedAt,
    public ?string $workItemId,
    public string $kind,
  ) {
  }
}
