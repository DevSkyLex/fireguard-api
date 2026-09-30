<?php

declare(strict_types=1);

namespace Facility\Application\Contract\Event;

use DateTimeImmutable;

/** Event FacilityPlanGeometryChangedEvent. Committed spatial change; coordinates and free text stay out of the audit feed. */
final readonly class FacilityPlanGeometryChangedEvent
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Records a committed facility-plan geometry change without including coordinates or free text in the audit feed.
   *
   * @access public
   *
   * @param string $organizationId organization owning the facility
   * @param string $resourceId identifier of the facility whose plan geometry changed
   * @param ?string $previousAttachmentId previous plan attachment identifier, when set
   * @param ?string $attachmentId new plan attachment identifier, or null when cleared
   * @param int $revision committed geometry revision used to order changes
   * @param ?string $interventionId intervention associated with the change, when present
   * @param DateTimeImmutable $occurredAt time the committed geometry change occurred
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
