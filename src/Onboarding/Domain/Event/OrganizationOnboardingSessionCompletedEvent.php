<?php

declare(strict_types=1);

namespace Onboarding\Domain\Event;

use DateTimeImmutable;

/**
 * Event OrganizationOnboardingSessionCompletedEvent.
 *
 * Dispatched when an organization onboarding session transitions to the
 * {@see \Onboarding\Domain\ValueObject\OrganizationOnboardingState::COMPLETED} state
 * for the first time. Downstream listeners (notification, facility bootstrapping,
 * analytics, etc.) can react to this event without coupling to the onboarding flow.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationOnboardingSessionCompletedEvent
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $sessionId the onboarding session identifier
   * @param string $userId the authenticated user who completed the flow
   * @param string $targetOrganizationId the organization created during the flow
   * @param DateTimeImmutable $completedAt the exact completion timestamp
   */
  public function __construct(
    public string $sessionId,
    public string $userId,
    public string $targetOrganizationId,
    public DateTimeImmutable $completedAt,
  ) {
  }
  // #endregion
}
