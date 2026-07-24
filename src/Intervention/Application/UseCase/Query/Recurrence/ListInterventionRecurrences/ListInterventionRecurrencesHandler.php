<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Recurrence\ListInterventionRecurrences;

use Intervention\Application\Port\Outbound\InterventionRecurrencePort;
use Intervention\Domain\Exception\InterventionAccessDeniedException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase ListInterventionRecurrencesHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInterventionRecurrencesHandler implements QueryHandler
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
   * @param ListInterventionRecurrencesQuery $query the query value
   *
   * @return ListInterventionRecurrencesResult the query result
   */
  public function __invoke(ListInterventionRecurrencesQuery $query): ListInterventionRecurrencesResult
  {
    if (!$this->authorization->hasPermission($query->userId, $query->organizationId, 'organization.interventions.read')) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.read permission.');
    }

    return new ListInterventionRecurrencesResult(
      $this->recurrences->list($query->organizationId, $query->page, $query->itemsPerPage, $query->isActive),
    );
  }
  // #endregion
}
