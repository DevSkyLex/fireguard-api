<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Inspection\ResolveCanonicalInspectionScope;

use Inspection\Application\Port\Outbound\InterventionScopePort;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase ResolveCanonicalInspectionScopeHandler.
 *
 * An explicit `organization` wins; otherwise an `intervention` names its own.
 *
 * **No existence check on the organization itself.** `resolveAccess()` in the
 * provider answers OUTSIDE_SCOPE — and therefore the same 404, with the same
 * message — for an organization that does not exist as for one the caller is
 * not in.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResolveCanonicalInspectionScopeHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InterventionScopePort $interventions the intervention scope port
   */
  public function __construct(
    private InterventionScopePort $interventions,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param ResolveCanonicalInspectionScopeQuery $query the query payload
   *
   * @return ResolveCanonicalInspectionScopeResult the resolved organization, when there is one
   */
  public function __invoke(ResolveCanonicalInspectionScopeQuery $query): ResolveCanonicalInspectionScopeResult
  {
    if (null !== $query->organizationId) {
      return new ResolveCanonicalInspectionScopeResult($query->organizationId);
    }

    if (null === $query->interventionId) {
      return new ResolveCanonicalInspectionScopeResult();
    }

    return new ResolveCanonicalInspectionScopeResult(
      $this->interventions->organizationIdOf($query->interventionId),
    );
  }
  // #endregion
}
