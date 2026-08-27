<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Label\CreateInterventionLabel;

use Intervention\Application\Port\Outbound\InterventionLabelPort;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException, InterventionValidationException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;

use function trim;

/**
 * UseCase CreateInterventionLabelHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateInterventionLabelHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the CreateInterventionLabelHandler class.
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
   * @param CreateInterventionLabelCommand $command the command value
   *
   * @return CreateInterventionLabelResult the   invoke result
   */
  public function __invoke(CreateInterventionLabelCommand $command): CreateInterventionLabelResult
  {
    $decision = $this->authorization->resolveAccess($command->userId, $command->organizationId, 'organization.interventions.write');
    if ($decision->isOutsideScope()) {
      throw InterventionNotFoundException::forOrganizationScope($command->organizationId);
    }
    if (!$decision->isGranted()) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.write permission.');
    }

    $name = trim($command->name);
    if ('' === $name) {
      throw new InterventionValidationException('The label name must not be blank.');
    }

    return new CreateInterventionLabelResult(
      $this->labels->create($command->organizationId, $name, $command->color),
    );
  }
}
