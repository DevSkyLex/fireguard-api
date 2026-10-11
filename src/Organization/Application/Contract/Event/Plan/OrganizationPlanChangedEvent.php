<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Event\Plan;

use DateTimeImmutable;

/**
 * Event OrganizationPlanChangedEvent.
 *
 * Raised when an organization subscription plan changes.
 *
 * @category Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationPlanChangedEvent
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
   * Initializes a new instance of the
   * OrganizationPlanChangedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $planId the new plan ID
   * @param string|null $previousPlanId the previous plan ID
   * @param list<string> $overQuotaResources resources whose usage exceeds the new plan's caps
   * @param ?DateTimeImmutable $occurredAt persisted occurrence time, or null for a new event
   */
  public function __construct(
    public string $organizationId,
    public string $planId,
    public ?string $previousPlanId = null,
    public array $overQuotaResources = [],
    ?DateTimeImmutable $occurredAt = null,
  ) {
    $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
  }
  // #endregion
}
