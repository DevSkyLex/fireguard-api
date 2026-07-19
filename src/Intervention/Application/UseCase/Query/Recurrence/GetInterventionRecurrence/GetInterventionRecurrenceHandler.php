<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Recurrence\GetInterventionRecurrence;

use Intervention\Application\Port\Outbound\InterventionRecurrencePort;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetInterventionRecurrenceHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetInterventionRecurrenceHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InterventionRecurrencePort $recurrences the recurrences port
   * @param OrganizationAuthorizationPort $authorization the authorization port
   */
  public function __construct(
    private InterventionRecurrencePort $recurrences,
    private OrganizationAuthorizationPort $authorization,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param GetInterventionRecurrenceQuery $query the query value
   *
   * @return GetInterventionRecurrenceResult the query result
   */
  public function __invoke(GetInterventionRecurrenceQuery $query): GetInterventionRecurrenceResult
  {
    $recurrence = $this->recurrences->find($query->recurrenceId);
    if (null === $recurrence) {
      throw InterventionNotFoundException::withId($query->recurrenceId);
    }
    if (!$this->authorization->hasPermission($query->userId, $recurrence->organizationId, 'organization.interventions.read')) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.read permission.');
    }

    return new GetInterventionRecurrenceResult($recurrence);
  }
  // #endregion
}
