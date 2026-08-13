<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Template\GetInterventionTemplate;

use Intervention\Application\Port\Outbound\InterventionTemplatePort;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetInterventionTemplateHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetInterventionTemplateHandler implements QueryHandler
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetInterventionTemplateHandler class.
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
   * @param GetInterventionTemplateQuery $query the query value
   *
   * @return GetInterventionTemplateResult the   invoke result
   */
  public function __invoke(GetInterventionTemplateQuery $query): GetInterventionTemplateResult
  {
    $template = $this->templates->find($query->templateId);
    if (null === $template) {
      throw InterventionNotFoundException::withId($query->templateId);
    }
    $decision = $this->authorization->resolveAccess($query->userId, $template->organizationId, 'organization.interventions.read');
    if ($decision->isOutsideScope()) {
      throw InterventionNotFoundException::withId($query->templateId);
    }
    if (!$decision->isGranted()) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.read permission.');
    }

    return new GetInterventionTemplateResult($template);
  }
}
