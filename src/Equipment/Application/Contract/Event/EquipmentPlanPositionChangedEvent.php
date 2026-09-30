<?php

declare(strict_types=1);

namespace Equipment\Application\Contract\Event;

use DateTimeImmutable;

/** Event EquipmentPlanPositionChangedEvent. Committed spatial change; coordinates and free text stay out of the audit feed. */
final readonly class EquipmentPlanPositionChangedEvent
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Records a committed equipment position change without exposing coordinates or free text to the audit feed.
   *
   * @access public
   *
   * @param string $organizationId organization owning the equipment
   * @param string $resourceId identifier of the equipment whose position changed
   * @param ?string $previousAttachmentId previous floor-plan attachment identifier, when one was set
   * @param ?string $attachmentId new floor-plan attachment identifier, or null when cleared
   * @param int $revision committed position revision used to order changes
   * @param ?string $interventionId intervention associated with the change, when present
   * @param DateTimeImmutable $occurredAt time the committed position change occurred
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $resourceId,
    public ?string $previousAttachmentId,
    public ?string $attachmentId,
    public int $revision,
    public ?string $interventionId,
    public DateTimeImmutable $occurredAt,
  ) {
  }
  // #endregion
}
