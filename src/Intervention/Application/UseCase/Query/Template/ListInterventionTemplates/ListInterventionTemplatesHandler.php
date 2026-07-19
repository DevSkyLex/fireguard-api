<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Template\ListInterventionTemplates;

use Intervention\Application\Port\Outbound\InterventionTemplatePort;
use Intervention\Domain\Exception\InterventionAccessDeniedException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase ListInterventionTemplatesHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInterventionTemplatesHandler implements QueryHandler
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListInterventionTemplatesHandler class.
   *
   * @since 1.0.0
   *
   * @param InterventionTemplatePort $templates the templates value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   */
  public function __construct(
    private InterventionTemplatePort $templates,
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
   * @param ListInterventionTemplatesQuery $query the query value
   *
   * @return ListInterventionTemplatesResult the   invoke result
   */
  public function __invoke(ListInterventionTemplatesQuery $query): ListInterventionTemplatesResult
  {
    if (!$this->authorization->hasPermission($query->userId, $query->organizationId, 'organization.interventions.read')) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.read permission.');
    }

    return new ListInterventionTemplatesResult(
      $this->templates->list($query->organizationId, $query->page, $query->itemsPerPage, $query->search),
    );
  }
}
