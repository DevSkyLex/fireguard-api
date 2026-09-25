<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Template\UpdateInterventionTemplate;

use Intervention\Application\Contract\Template\{InterventionTemplateCollectionsPatch, InterventionTemplateDefaultsPatch, InterventionTemplateIdentityPatch, InterventionTemplatePlanningPatch, InterventionTemplateUpdateRequest};
use Intervention\Application\Port\Outbound\InterventionTemplatePort;
use Intervention\Application\UseCase\Command\Template\CreateInterventionTemplate\CreateInterventionTemplateHandler;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;

/**
 * UseCase UpdateInterventionTemplateHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateInterventionTemplateHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the UpdateInterventionTemplateHandler class.
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
   * @param UpdateInterventionTemplateCommand $command the command value
   *
   * @return UpdateInterventionTemplateResult the   invoke result
   */
  public function __invoke(UpdateInterventionTemplateCommand $command): UpdateInterventionTemplateResult
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

    $name = $command->hasName && null !== $command->name
      ? CreateInterventionTemplateHandler::validatedName($command->name)
      : null;
    $type = $command->hasType && null !== $command->type
      ? CreateInterventionTemplateHandler::validatedType($command->type)
      : null;
    $priority = $command->hasPriority && null !== $command->priority
      ? CreateInterventionTemplateHandler::validatedPriority($command->priority)
      : null;
    $duration = $command->hasDuration
      ? CreateInterventionTemplateHandler::validatedDuration($command->duration)
      : null;
    $items = $command->hasItems
      ? CreateInterventionTemplateHandler::validatedItems($command->items ?? [])
      : null;

    return new UpdateInterventionTemplateResult(
      $this->templates->update(new InterventionTemplateUpdateRequest(
        $command->templateId,
        new InterventionTemplateIdentityPatch($name, $command->description, $type, $command->hasName, $command->hasDescription, $command->hasType),
        new InterventionTemplatePlanningPatch($priority, $duration, $command->hasPriority, $command->hasDuration),
        new InterventionTemplateDefaultsPatch($command->defaultSiteId, $command->defaultResponsibleId, $command->hasDefaultSiteId, $command->hasDefaultResponsibleId),
        new InterventionTemplateCollectionsPatch($command->labelIds, $items, $command->hasLabelIds, $command->hasItems),
      )),
    );
  }
}
