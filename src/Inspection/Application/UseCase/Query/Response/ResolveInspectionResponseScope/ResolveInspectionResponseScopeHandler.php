<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Response\ResolveInspectionResponseScope;

use Inspection\Application\Port\Outbound\{InspectionRepositoryPort, InterventionScopePort};
use Inspection\Domain\ValueObject\InspectionId;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase ResolveInspectionResponseScopeHandler.
 *
 * Resolves the organization the response collection is scoped to, from
 * whichever of the three filters the caller supplied.
 *
 * The precedence is the endpoint's published contract: an explicit
 * `organization` wins; otherwise an `intervention` names its own
 * organization; otherwise an `inspection` names its own. The third is a
 * fallback rather than an alternative — it only runs when the first two
 * produced nothing.
 *
 * **No existence check on the organization itself.** `resolveAccess()` in the
 * provider answers OUTSIDE_SCOPE — and therefore the same 404, with the same
 * message — for an organization that does not exist as for one the caller is
 * not in. Looking it up here would buy a second query and no answer.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResolveInspectionResponseScopeHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InterventionScopePort $interventions the intervention scope port
   * @param InspectionRepositoryPort $inspections the inspection repository
   */
  public function __construct(
    private InterventionScopePort $interventions,
    private InspectionRepositoryPort $inspections,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param ResolveInspectionResponseScopeQuery $query the query payload
   *
   * @return ResolveInspectionResponseScopeResult the resolved organization, when there is one
   */
  public function __invoke(ResolveInspectionResponseScopeQuery $query): ResolveInspectionResponseScopeResult
  {
    if (null !== $query->organizationId) {
      return new ResolveInspectionResponseScopeResult($query->organizationId);
    }

    if (null !== $query->interventionId) {
      $fromIntervention = $this->interventions->organizationIdOf($query->interventionId);

      if (null !== $fromIntervention) {
        return new ResolveInspectionResponseScopeResult($fromIntervention);
      }
    }

    if (null === $query->inspectionId) {
      return new ResolveInspectionResponseScopeResult();
    }

    return new ResolveInspectionResponseScopeResult($this->inspectionOrganizationId($query->inspectionId));
  }

  /**
   * Method inspectionOrganizationId.
   *
   * A malformed identifier resolves to nothing rather than throwing: before
   * the identifier was a value object, `$entityManager->find()` returned null
   * for any unparseable string and the endpoint answered 400.
   *
   * @since 1.0.0
   *
   * @param string $inspectionId the raw inspection identifier
   *
   * @return ?string the owning organization identifier, when the inspection exists
   */
  private function inspectionOrganizationId(string $inspectionId): ?string
  {
    try {
      $id = InspectionId::fromString($inspectionId);
    } catch (InvalidValueException) {
      return null;
    }

    return $this->inspections->findScope($id)?->organizationId;
  }
  // #endregion
}
