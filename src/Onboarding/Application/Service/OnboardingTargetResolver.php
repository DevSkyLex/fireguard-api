<?php

declare(strict_types=1);

namespace Onboarding\Application\Service;

use Onboarding\Application\Contract\Organization\OnboardingOrganizationCandidate;
use Onboarding\Application\Contract\Setup\{OrganizationSetupConflict, OrganizationSetupOperation};
use Onboarding\Application\Port\Outbound\OrganizationSetupRepositoryPort;
use Onboarding\Domain\Model\OrganizationOnboardingSession\{ComputedOnboardingState, OrganizationOnboardingSession};
use Onboarding\Domain\ValueObject\{OrganizationOnboardingState, OrganizationOnboardingStep};

use function is_string;

/** Selects only an organization proven to belong to the current onboarding. */
final class OnboardingTargetResolver
{
  /**
   * Method resolveAlreadyJoinedOrganization.
   *
   * Finds the organization a member already belongs to without having created
   * it here — the shape of an invitation: they accepted, so they have a
   * workspace, but no organization was created during this onboarding session
   * for {@see self::resolveTargetOrganization()} to adopt.
   *
   * Only a session that never pinned an organization qualifies. A pinned one
   * that disappeared is a different story, and resetting the flow there stays
   * deliberate.
   *
   * @since 1.2.0
   *
   * @param OrganizationOnboardingSession $session the onboarding session aggregate
   * @param array<OnboardingOrganizationCandidate> $organizationsResult the current organizations list
   *
   * @return ?OnboardingOrganizationCandidate the membership to complete the flow against
   */
  public static function resolveAlreadyJoinedOrganization(
    OrganizationOnboardingSession $session,
    array $organizationsResult,
  ): ?OnboardingOrganizationCandidate {
    if ($session->creationIntent()) {
      return null;
    }
    $pinnedOrganizationId = $session->targetOrganizationId();
    if (is_string($pinnedOrganizationId) && '' !== $pinnedOrganizationId) {
      return null;
    }

    $candidate = null;
    foreach ($organizationsResult as $organization) {
      if (null === $candidate || $organization->createdAt > $candidate->createdAt) {
        $candidate = $organization;
      }
    }

    return $candidate;
  }

  /**
   * Method completeForAlreadyJoinedOrganization.
   *
   * Closes the flow for a member who arrived through an invitation. Without
   * this the activation wizard has no organization to adopt, resets to
   * `create_organization`, and `onboardingRequiredGuard` holds the member on
   * the wizard for good — locked out of every page of the product.
   *
   * The rollback stack is cleared rather than extended: this organization
   * predates the session and must never become something a later rollback can
   * delete, which is exactly why {@see self::resolveTargetOrganization()}
   * refuses to adopt it in the first place.
   *
   * @since 1.2.0
   *
   * @param OrganizationOnboardingSession $session the onboarding session aggregate
   * @param OnboardingOrganizationCandidate $organization the organization the member already belongs to
   *
   * @return ComputedOnboardingState the completed flow state
   */
  public static function completeForAlreadyJoinedOrganization(
    OrganizationOnboardingSession $session,
    OnboardingOrganizationCandidate $organization,
  ): ComputedOnboardingState {
    $session->setTargetOrganization($organization->id, $organization->name);
    $session->clearRollbackStack();

    foreach (OrganizationOnboardingStep::all() as $step) {
      $session->markStepCompleted($step);
    }

    $session->setCompleted();

    return new ComputedOnboardingState(
      state: OrganizationOnboardingState::COMPLETED,
      nextStep: null,
      blockedReason: null,
      targetOrganizationId: $organization->id,
      targetOrganizationName: $organization->name,
    );
  }

  /**
   * Method resolveTargetOrganization.
   *
   * A setup journal is authoritative: only the completed creator receipt may
   * select an organization. An unavailable result refuses recovery without
   * discarding that receipt; a prepared item never adopts an unrelated result.
   * The legacy rules below apply only to sessions without a setup journal.
   *
   * When a targetOrganizationId is already pinned on the session, only that
   * organization is accepted. If it was deleted externally the method returns
   * null so the flow resets instead of silently switching to another org.
   *
   * When no org is pinned yet (fresh session) only an organization whose
   * createdAt is greater than or equal to the session createdAt is adopted.
   * This prevents pre-existing production organizations from being silently
   * adopted and later destroyed by the create_organization rollback action.
   *
   * @since 1.0.0
   *
   * @param OrganizationOnboardingSession $session the onboarding session aggregate
   * @param array<OnboardingOrganizationCandidate> $organizationsResult the current organizations list
   *
   * @return ?OnboardingOrganizationCandidate the resolved target organization
   */
  public static function resolveTargetOrganization(
    OrganizationOnboardingSession $session,
    array $organizationsResult,
    ?OrganizationSetupRepositoryPort $setupRepository,
  ): ?OnboardingOrganizationCandidate {
    $setupOperations = $setupRepository?->listOperations($session->id()) ?? [];
    if ([] !== $setupOperations || ($setupRepository?->hasJournal($session->id()) ?? false)) {
      return self::resolveFromSetupReceipt($session, $organizationsResult, $setupOperations);
    }

    $targetOrganizationId = $session->targetOrganizationId();
    if (is_string($targetOrganizationId) && '' !== $targetOrganizationId) {
      return self::findPinnedOrganization($organizationsResult, $targetOrganizationId);
    }

    return self::latestEligibleOrganization($session, $organizationsResult);
  }

  /**
   * @param array<OnboardingOrganizationCandidate> $organizationsResult
   */
  private static function findPinnedOrganization(array $organizationsResult, string $targetOrganizationId): ?OnboardingOrganizationCandidate
  {
    foreach ($organizationsResult as $organization) {
      if ($organization->id === $targetOrganizationId) {
        return $organization;
      }
    }

    // A stale pin must never silently select another organization.
    return null;
  }

  /**
   * @param array<OnboardingOrganizationCandidate> $organizationsResult
   * @param list<OrganizationSetupOperation> $setupOperations
   */
  private static function resolveFromSetupReceipt(
    OrganizationOnboardingSession $session,
    array $organizationsResult,
    array $setupOperations,
  ): ?OnboardingOrganizationCandidate {
    if ([] === $setupOperations) {
      return null;
    }
    foreach ($setupOperations as $operation) {
      if (OrganizationOnboardingStep::CREATE_ORGANIZATION !== $operation->stepKey) {
        continue;
      }
      // Only a completed creator receipt may pin its resulting organization.
      if (null === $operation->resourceId) {
        return null;
      }
      foreach ($organizationsResult as $organization) {
        if ($organization->id === $operation->resourceId && self::isEligibleCreationTarget($organization, $session)) {
          return $organization;
        }
      }

      // Preserve a stale receipt instead of adopting an unrelated organization.
      throw OrganizationSetupConflict::because('The organization created by this setup is no longer available. Reset the creation session explicitly.');
    }

    throw OrganizationSetupConflict::because('The setup creation receipt is missing.');
  }

  /**
   * @param array<OnboardingOrganizationCandidate> $organizationsResult
   */
  private static function latestEligibleOrganization(
    OrganizationOnboardingSession $session,
    array $organizationsResult,
  ): ?OnboardingOrganizationCandidate {
    $candidate = null;
    foreach ($organizationsResult as $organization) {
      if (!self::isEligibleCreationTarget($organization, $session)) {
        continue;
      }
      if (null === $candidate || $organization->createdAt > $candidate->createdAt) {
        $candidate = $organization;
      }
    }

    return $candidate;
  }

  private static function isEligibleCreationTarget(
    OnboardingOrganizationCandidate $organization,
    OrganizationOnboardingSession $session,
  ): bool {
    return $organization->isActive
      && $organization->createdByUserId === $session->userId()
      && $organization->ownerUserId === $session->userId()
      && $organization->createdAt >= $session->createdAt();
  }
}
