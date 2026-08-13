<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Template\InstantiateInterventionTemplate;

use Intervention\Application\Port\Outbound\InterventionTemplatePort;
use Intervention\Application\Service\InterventionTemplateInstantiator;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;

/**
 * UseCase InstantiateInterventionTemplateHandler.
 *
 * Authorizes the request (the API-side concern) and delegates the actual
 * draft creation to {@see InterventionTemplateInstantiator} — the shared
 * core also used by the recurrence materializer — with
 * `origin: 'intervention:template'`.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InstantiateInterventionTemplateHandler implements CommandHandler
{
  // #region Constants
  /**
   * The origin label recorded on drafts created from a template.
   */
  private const string ORIGIN = 'intervention:template';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the InstantiateInterventionTemplateHandler class.
   *
   * @since 1.0.0
   *
   * @param InterventionTemplatePort $templates the templates value
   * @param InterventionTemplateInstantiator $instantiator the shared template instantiation service
   * @param OrganizationAuthorizationPort $authorization the authorization value
   */
  public function __construct(
    private InterventionTemplatePort $templates,
    private InterventionTemplateInstantiator $instantiator,
    private OrganizationAuthorizationPort $authorization,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Executes the   invoke operation.
   *
   * @since 1.0.0
   *
   * @param InstantiateInterventionTemplateCommand $command the command value
   *
   * @return InstantiateInterventionTemplateResult the   invoke result
   */
  public function __invoke(InstantiateInterventionTemplateCommand $command): InstantiateInterventionTemplateResult
  {
    $template = $this->templates->find($command->templateId);
    if (null === $template) {
      throw InterventionNotFoundException::withId($command->templateId);
    }
    $decision = $this->authorization->resolveAccess($command->userId, $template->organizationId, 'organization.interventions.plan');
    if ($decision->isOutsideScope()) {
      throw InterventionNotFoundException::withId($command->templateId);
    }
    if (!$decision->isGranted()) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.plan permission.');
    }

    $draft = $this->instantiator->instantiate(
      templateId: $command->templateId,
      origin: self::ORIGIN,
      name: $command->name,
      siteId: $command->siteId,
      responsibleId: $command->responsibleId,
      plannedStartAt: $command->plannedStartAt,
      actorUserId: $command->userId,
    );

    return new InstantiateInterventionTemplateResult($draft->interventionId, $draft->number);
  }
  // #endregion
}
