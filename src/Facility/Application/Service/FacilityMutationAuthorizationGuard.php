<?php

declare(strict_types=1);

namespace Facility\Application\Service;

use Facility\Application\Contract\Facility\CanonicalFacilityView;
use Facility\Application\Port\Inbound\FacilityMutationAuthorizationPort;
use Facility\Domain\Exception\{FacilityAccessDeniedException, FacilityNotFoundException};
use Intervention\Application\Port\Inbound\InterventionResourcesPort;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;

/**
 * Guard FacilityMutationAuthorizationGuard.
 *
 * Resolves contextual permissions while hiding records outside the actor's organization.
 *
 * @category Guard
 */
final readonly class FacilityMutationAuthorizationGuard implements FacilityMutationAuthorizationPort
{
  // #region Constructor
  /**
   * Method __construct.
   */
  public function __construct(private OrganizationAuthorizationPort $authorization, private InterventionResourcesPort $interventions)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method assertCanMutate.
   */
  public function assertCanMutate(string $userId, CanonicalFacilityView $view, bool $forUpdate = true): void
  {
    $permission = 'draft' !== $view->recordStatus || null === $view->interventionId
      ? 'organization.facilities.write'
      : $this->interventions->mutationPermission($view->interventionId, $userId, $view->organizationId, $forUpdate);
    $decision = $this->authorization->resolveAccess($userId, $view->organizationId, $permission);
    if ($decision->isOutsideScope()) {
      throw new FacilityNotFoundException('Facility not found.');
    }
    if (!$decision->isGranted()) {
      throw new FacilityAccessDeniedException('Missing ' . $permission . ' permission.');
    }
  }
  // #endregion
}
