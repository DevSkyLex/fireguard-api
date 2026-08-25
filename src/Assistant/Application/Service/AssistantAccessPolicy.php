<?php

declare(strict_types=1);

namespace Assistant\Application\Service;

use Assistant\Application\Port\Outbound\Organization\AssistantOrganizationSettingsPort;
use Assistant\Domain\Exception\AssistantDisabledException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;

/**
 * Service AssistantAccessPolicy.
 *
 * The single gate every assistant use case passes through. Two conditions have
 * to hold, and both are opt-in:
 *
 * - the caller holds `organization.assistant.use`;
 * - the organization has turned the assistant on (`settings.assistant.enabled`).
 *
 * The second half is why this service exists. `AssistantOrganizationSettingsPort::isEnabledFor()`
 * was declared and bound but never called from anywhere, so the organization
 * toggle documented as a gate was decorative: switching the assistant off in
 * settings left every endpoint answering. The permission alone decided access.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssistantAccessPolicy
{
  // #region Constants
  private const string USE_PERMISSION = 'organization.assistant.use';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param AssistantOrganizationSettingsPort $organizationSettings the organization assistant settings port
   */
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private AssistantOrganizationSettingsPort $organizationSettings,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method assertCanUseAssistant.
   *
   * Order matters: the permission is asserted first, so a caller outside the
   * organization keeps getting the answer the authorization port decides
   * (404 outside scope, 403 without the permission) rather than learning from
   * a toggle whether the organization exists.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user identifier
   * @param string $organizationId the organization identifier
   *
   * @throws AssistantDisabledException when the assistant is off for this organization
   */
  public function assertCanUseAssistant(string $userId, string $organizationId): void
  {
    $this->authorization->assertGrantedPermissions($userId, $organizationId, [self::USE_PERMISSION]);

    if (!$this->organizationSettings->isEnabledFor($organizationId)) {
      throw AssistantDisabledException::forOrganization();
    }
  }
  // #endregion
}
