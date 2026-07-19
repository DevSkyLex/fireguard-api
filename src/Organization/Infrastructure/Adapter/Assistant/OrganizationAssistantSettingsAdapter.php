<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Assistant;

use Assistant\Application\Port\Outbound\Organization\AssistantOrganizationSettingsPort;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\ValueObject\{OrganizationAssistantSettings, OrganizationId};
use Shared\Domain\Exception\InvalidValueException;

/**
 * Adapter OrganizationAssistantSettingsAdapter.
 *
 * Implements the Assistant module's organization-settings port using the
 * organization's existing `OrganizationAssistantSettings` value object —
 * mirrors {@see \Organization\Infrastructure\Adapter\Approval\OrganizationApprovalPolicyAdapter}.
 * Never throws on an unknown/malformed organization: falls back to
 * "everything disabled" (fail-closed) so a lookup failure can never
 * silently leak business context, nor silently bypass the (still deferred)
 * enabled gate.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationAssistantSettingsAdapter implements AssistantOrganizationSettingsPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository port
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
  ) {
  }
  // #endregion

  // #region Methods
  public function isEnabledFor(string $organizationId): bool
  {
    return $this->settingsFor($organizationId)->enabled ?? false;
  }

  public function includeBusinessContextFor(string $organizationId): bool
  {
    return $this->settingsFor($organizationId)->includeBusinessContext ?? false;
  }

  /**
   * Method settingsFor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return ?OrganizationAssistantSettings the organization's assistant settings, or null when unresolvable
   */
  private function settingsFor(string $organizationId): ?OrganizationAssistantSettings
  {
    try {
      $organization = $this->organizationRepository->findById(OrganizationId::fromString($organizationId));
    } catch (InvalidValueException) {
      return null;
    }

    return $organization?->settings()->assistant;
  }
  // #endregion
}
