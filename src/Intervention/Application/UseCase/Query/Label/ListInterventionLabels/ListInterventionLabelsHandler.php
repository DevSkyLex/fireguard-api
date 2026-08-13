<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Label\ListInterventionLabels;

use Intervention\Application\Port\Outbound\InterventionLabelPort;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase ListInterventionLabelsHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInterventionLabelsHandler implements QueryHandler
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListInterventionLabelsHandler class.
   *
   * @since 1.0.0
   *
   * @param InterventionLabelPort $labels the labels value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   */
  public function __construct(
    private InterventionLabelPort $labels,
    private OrganizationAuthorizationPort $authorization,
  ) {
  }

  /**
   * Method __invoke.
   *
   * Executes the   invoke operation.
   *
   * @since 1.0.0
   *
   * @param ListInterventionLabelsQuery $query the query value
   *
   * @return ListInterventionLabelsResult the   invoke result
   */
  public function __invoke(ListInterventionLabelsQuery $query): ListInterventionLabelsResult
  {
    $decision = $this->authorization->resolveAccess($query->userId, $query->organizationId, 'organization.interventions.read');
    if ($decision->isOutsideScope()) {
      throw InterventionNotFoundException::forOrganizationScope($query->organizationId);
    }
    if (!$decision->isGranted()) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.read permission.');
    }

    return new ListInterventionLabelsResult(
      $this->labels->list($query->organizationId, $query->page, $query->itemsPerPage),
    );
  }
}
