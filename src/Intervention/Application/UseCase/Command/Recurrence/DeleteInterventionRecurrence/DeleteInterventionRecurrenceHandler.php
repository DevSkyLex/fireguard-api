<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Recurrence\DeleteInterventionRecurrence;

use Intervention\Application\Port\Outbound\InterventionRecurrencePort;
use Intervention\Domain\Event\Recurrence\InterventionRecurrenceDeletedEvent;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\{CommandHandler, VoidResult};
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * UseCase DeleteInterventionRecurrenceHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteInterventionRecurrenceHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InterventionRecurrencePort $recurrences the recurrences port
   * @param OrganizationAuthorizationPort $authorization the authorization port
   * @param EventDispatcherPort $eventDispatcher the event dispatcher port
   */
  public function __construct(
    private InterventionRecurrencePort $recurrences,
    private OrganizationAuthorizationPort $authorization,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param DeleteInterventionRecurrenceCommand $command the command value
   *
   * @return VoidResult the command result
   */
  public function __invoke(DeleteInterventionRecurrenceCommand $command): VoidResult
  {
    $existing = $this->recurrences->find($command->recurrenceId);
    if (null === $existing) {
      throw InterventionNotFoundException::withId($command->recurrenceId);
    }
    if (!$this->authorization->hasPermission($command->userId, $existing->organizationId, 'organization.interventions.plan')) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.plan permission.');
    }

    $this->recurrences->delete($command->recurrenceId);

    $this->eventDispatcher->dispatch(new InterventionRecurrenceDeletedEvent(
      organizationId: $existing->organizationId,
      recurrenceId: $command->recurrenceId,
      actorUserId: $command->userId,
    ));

    return new VoidResult();
  }
  // #endregion
}
