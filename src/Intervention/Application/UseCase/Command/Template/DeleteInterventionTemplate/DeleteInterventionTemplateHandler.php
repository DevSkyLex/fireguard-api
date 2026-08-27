<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Template\DeleteInterventionTemplate;

use Intervention\Application\Port\Outbound\InterventionTemplatePort;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\{CommandHandler, VoidResult};

/**
 * UseCase DeleteInterventionTemplateHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteInterventionTemplateHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the DeleteInterventionTemplateHandler class.
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
   * @param DeleteInterventionTemplateCommand $command the command value
   *
   * @return VoidResult the   invoke result
   */
  public function __invoke(DeleteInterventionTemplateCommand $command): VoidResult
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

    $this->templates->delete($command->templateId);

    return new VoidResult();
  }
}
