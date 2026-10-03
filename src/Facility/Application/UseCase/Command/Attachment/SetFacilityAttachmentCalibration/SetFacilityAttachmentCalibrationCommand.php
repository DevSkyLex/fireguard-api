<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Attachment\SetFacilityAttachmentCalibration;

use Shared\Application\Message\CommandMessage;

/**
 * Command SetFacilityAttachmentCalibrationCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SetFacilityAttachmentCalibrationCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ?array<string, mixed> $calibration the metric calibration or null to clear it
   */
  public function __construct(
    public string $userId,
    public string $attachmentId,
    public int $expectedRevision,
    public ?array $calibration,
  ) {
  }
  // #endregion
}
