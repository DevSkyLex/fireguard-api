<?php

declare(strict_types=1);

namespace Equipment\Application\Contract\Media;

use DateTimeImmutable;

/**
 * Immutable attachment metadata and owning equipment scope.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentMediaView
{
  // #region Constructor
  /**
   * Constructor. @since 1.0.0
   */
  public function __construct(
    public string $id,
    public string $equipmentId,
    public string $organizationId,
    public string $fileName,
    public string $mimeType,
    public int $size,
    public ?string $label,
    public int $revision,
    public DateTimeImmutable $uploadedAt,
    public string $recordStatus,
    public ?string $interventionId,
  ) {
  }
  // #endregion
}
