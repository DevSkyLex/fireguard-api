<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Label\UpdateInterventionLabel;

use Intervention\Application\Port\Outbound\InterventionLabelPort;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException, InterventionValidationException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;

use function trim;

/**
 * UseCase UpdateInterventionLabelHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateInterventionLabelHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the UpdateInterventionLabelHandler class.
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
   * @param UpdateInterventionLabelCommand $command the command value
   *
   * @return UpdateInterventionLabelResult the   invoke result
   */
  public function __invoke(UpdateInterventionLabelCommand $command): UpdateInterventionLabelResult
  {
    $label = $this->labels->find($command->labelId);
    if (null === $label) {
      throw InterventionNotFoundException::withId($command->labelId);
    }
    if (!$this->authorization->hasPermission($command->userId, $label->organizationId, 'organization.interventions.write')) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.write permission.');
    }

    $name = null;
    if ($command->hasName) {
      $name = trim((string) $command->name);
      if ('' === $name) {
        throw new InterventionValidationException('The label name must not be blank.');
      }
    }

    return new UpdateInterventionLabelResult(
      $this->labels->update($command->labelId, $name, $command->color, $command->hasName, $command->hasColor),
    );
  }
}
