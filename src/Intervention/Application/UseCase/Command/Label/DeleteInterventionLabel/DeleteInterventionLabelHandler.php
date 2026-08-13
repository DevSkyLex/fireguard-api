<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Label\DeleteInterventionLabel;

use Intervention\Application\Port\Outbound\InterventionLabelPort;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\{CommandHandler, VoidResult};

/**
 * UseCase DeleteInterventionLabelHandler.
 *
 * Deletes an intervention label. Removing a label also removes its
 * assignments (`intervention_label_assignments` cascades on delete) but
 * never deletes the interventions it was attached to.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteInterventionLabelHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the DeleteInterventionLabelHandler class.
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
   * @param DeleteInterventionLabelCommand $command the command value
   *
   * @return VoidResult the   invoke result
   */
  public function __invoke(DeleteInterventionLabelCommand $command): VoidResult
  {
    $label = $this->labels->find($command->labelId);
    if (null === $label) {
      throw InterventionNotFoundException::withId($command->labelId);
    }
    $decision = $this->authorization->resolveAccess($command->userId, $label->organizationId, 'organization.interventions.write');
    if ($decision->isOutsideScope()) {
      throw InterventionNotFoundException::withId($command->labelId);
    }
    if (!$decision->isGranted()) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.write permission.');
    }

    $this->labels->delete($command->labelId);

    return new VoidResult();
  }
}
