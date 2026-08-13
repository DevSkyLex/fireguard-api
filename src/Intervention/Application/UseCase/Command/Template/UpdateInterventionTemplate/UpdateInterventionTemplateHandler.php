<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Template\UpdateInterventionTemplate;

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
      $this->templates->update(
        id: $command->templateId,
        name: $name,
        description: $command->description,
        type: $type,
        priority: $priority,
        defaultSiteId: $command->defaultSiteId,
        defaultResponsibleId: $command->defaultResponsibleId,
        duration: $duration,
        labelIds: $command->labelIds,
        items: $items,
        hasName: $command->hasName,
        hasDescription: $command->hasDescription,
        hasType: $command->hasType,
        hasPriority: $command->hasPriority,
        hasDefaultSiteId: $command->hasDefaultSiteId,
        hasDefaultResponsibleId: $command->hasDefaultResponsibleId,
        hasDuration: $command->hasDuration,
        hasLabelIds: $command->hasLabelIds,
        hasItems: $command->hasItems,
      ),
    );
  }
}
