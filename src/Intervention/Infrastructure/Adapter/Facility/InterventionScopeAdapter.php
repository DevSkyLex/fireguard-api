<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Facility;

use Facility\Application\Contract\Hierarchy\InterventionParentAccess;
use Facility\Application\Port\Outbound\InterventionScopePort;
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Application\Service\InterventionMemberPolicy;
use Intervention\Domain\Exception\InterventionAccessDeniedException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;

use function in_array;

/**
 * Adapter InterventionScopeAdapter.
 *
 * Implements the Facility module's intervention scope port using this
 * module's own resource gateway. Third of three identical adapters, one per
 * consumer — see the port's docblock for why they are not shared.
 *
 * `touchDraft()` only bumps an intervention whose status is one of
 * `draft`, `planned`, `in_progress` or `changes_requested`, where the code
 * this replaced bumped unconditionally. The two agree wherever the port is
 * called: `InterventionResourceManager::mutationPermission()` — which every
 * caller runs first, to resolve the permission — rejects `submitted`,
 * `published` and `abandoned` outright.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionScopeAdapter implements InterventionScopePort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InterventionResourceGatewayPort $resources the intervention resource gateway
   * @param OrganizationAuthorizationPort $authorization existing intervention permissions
   * @param InterventionMemberPolicy $members execution participation policy
   */
  public function __construct(
    private InterventionResourceGatewayPort $resources,
    private OrganizationAuthorizationPort $authorization,
    private InterventionMemberPolicy $members,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function touchDraft(?string $interventionId): void
  {
    $this->resources->touchDraftIntervention($interventionId);
  }

  /**
   * {@inheritDoc}
   */
  public function preparationAccess(string $organizationId, string $interventionId, string $userId): InterventionParentAccess
  {
    $intervention = $this->resources->interventionAssignmentContext($interventionId);
    if (null === $intervention || $organizationId !== $intervention->organizationId) {
      return InterventionParentAccess::NOT_FOUND;
    }
    if (!in_array($intervention->status, ['draft', 'planned', 'in_progress', 'changes_requested'], true)) {
      return InterventionParentAccess::DENIED;
    }
    $permission = 'draft' === $intervention->status ? 'organization.interventions.plan' : 'organization.interventions.execute';
    if (!$this->authorization->hasPermission($userId, $organizationId, $permission)) {
      return InterventionParentAccess::DENIED;
    }
    if ('draft' !== $intervention->status) {
      try {
        $this->members->assertCanExecuteIntervention($organizationId, $userId, $intervention->responsibleId, $intervention->participants);
      } catch (InterventionAccessDeniedException) {
        return InterventionParentAccess::DENIED;
      }
    }

    return InterventionParentAccess::GRANTED;
  }
  // #endregion
}
