<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use Organization\Application\Port\Inbound\OrganizationNotificationPolicyPort;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationNotificationSettings};
use Shared\Domain\Exception\InvalidValueException;

/**
 * Service OrganizationNotificationPolicyService.
 *
 * Resolves an organization's effective notification policy from its persisted
 * settings. An unknown organization or a malformed identifier resolves to the
 * permissive "all on" defaults so notification gating can never crash or silently
 * suppress delivery for an edge case.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationNotificationPolicyService implements OrganizationNotificationPolicyPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationNotificationPolicyService class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
  ) {
  }
  // #endregion

  // #region Methods
  public function notificationPolicy(string $organizationId): OrganizationNotificationSettings
  {
    try {
      $organization = $this->organizationRepository->findById(OrganizationId::fromString($organizationId));
    } catch (InvalidValueException) {
      return new OrganizationNotificationSettings();
    }

    return $organization?->settings()->notifications ?? new OrganizationNotificationSettings();
  }
  // #endregion
}
