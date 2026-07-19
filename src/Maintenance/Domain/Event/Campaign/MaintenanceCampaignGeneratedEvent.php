<?php

declare(strict_types=1);

namespace Maintenance\Domain\Event\Campaign;

use DateTimeImmutable;

/**
 * Event MaintenanceCampaignGeneratedEvent.
 *
 * Raised when `POST /api/maintenance/campaigns` generates an intervention
 * draft from due/overdue maintenance schedules. Recorded in the audit
 * ledger as `maintenance.campaign_generated`.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MaintenanceCampaignGeneratedEvent
{
  // #region Properties
  /**
   * Property occurredAt.
   */
  public DateTimeImmutable $occurredAt;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the MaintenanceCampaignGeneratedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $interventionId the created intervention identifier
   * @param int $workItemsCount the number of seeded work items
   * @param ?string $actorUserId the acting user identifier
   */
  public function __construct(
    public string $organizationId,
    public string $interventionId,
    public int $workItemsCount,
    public ?string $actorUserId = null,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
