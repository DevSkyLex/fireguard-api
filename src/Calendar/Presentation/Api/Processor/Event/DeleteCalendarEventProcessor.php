<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Processor\Event;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Calendar\Application\UseCase\Command\Event\DeleteCalendarEvent\DeleteCalendarEventCommand;
use Calendar\Presentation\Api\Trait\CalendarExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function is_string;

/**
 * Processor DeleteCalendarEventProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, mixed>
 */
final readonly class DeleteCalendarEventProcessor implements ProcessorInterface
{
  use CalendarExceptionMapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param Security $security the security service
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $eventId = $uriVariables['eventId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId || !is_string($eventId) || '' === $eventId) {
      throw new BadRequestHttpException('OrganizationId and eventId URI parameters are required.');
    }

    try {
      $this->commandBus->dispatch(new DeleteCalendarEventCommand(
        organizationId: $organizationId,
        actorUserId: $user->getId(),
        eventId: $eventId,
      ));
    } catch (Throwable $exception) {
      throw $this->mapCalendarException($exception);
    }

    return null;
  }
  // #endregion
}
