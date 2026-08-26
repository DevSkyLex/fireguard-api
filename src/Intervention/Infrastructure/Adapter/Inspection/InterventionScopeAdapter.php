<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Inspection;

use Inspection\Application\Port\Outbound\InterventionScopePort;
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;

/**
 * Adapter InterventionScopeAdapter.
 *
 * Implements the Inspection module's intervention scope port using this
 * module's own resource gateway.
 *
 * `touchDraft()` only bumps an intervention whose status is one of
 * `draft`, `planned`, `in_progress` or `changes_requested`, where the code
 * this replaced bumped unconditionally. The two agree wherever the port is
 * called: `InterventionResourceManager::mutationPermission()` — which every
 * caller runs first, to resolve the permission — rejects `submitted`,
 * `published` and `abandoned` outright, and `InterventionStatus` has exactly
 * those seven cases. Widening that enum would break the equivalence.
 *
 * @category Adapter
 *
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
   */
  public function __construct(
    private InterventionResourceGatewayPort $resources,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function organizationIdOf(string $interventionId): ?string
  {
    return $this->resources->interventionAssignmentContext($interventionId)?->organizationId;
  }

  /**
   * {@inheritDoc}
   */
  public function touchDraft(?string $interventionId): void
  {
    $this->resources->touchDraftIntervention($interventionId);
  }
  // #endregion
}
