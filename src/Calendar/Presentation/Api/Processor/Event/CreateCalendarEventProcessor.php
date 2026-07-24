<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Processor\Event;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Calendar\Application\UseCase\Command\Event\CreateCalendarEvent\{CreateCalendarEventCommand, CreateCalendarEventResult};
use Calendar\Presentation\Api\Dto\Input\Event\CreateCalendarEventInput;
use Calendar\Presentation\Api\Dto\Output\Event\CalendarEventOutput;
use Calendar\Presentation\Api\Factory\CalendarEventOutputFactory;
use Calendar\Presentation\Api\Trait\CalendarExceptionMapperTrait;
use DateTimeImmutable;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function is_string;

/**
 * Processor CreateCalendarEventProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<CreateCalendarEventInput, CalendarEventOutput>
 */
final readonly class CreateCalendarEventProcessor implements ProcessorInterface
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
   * @param CalendarEventOutputFactory $outputFactory the output factory
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
    private CalendarEventOutputFactory $outputFactory,
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
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CalendarEventOutput
  {
    /** @var CreateCalendarEventInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    try {
      /** @var CreateCalendarEventResult $result */
      $result = $this->commandBus->dispatch(new CreateCalendarEventCommand(
        organizationId: $organizationId,
        actorUserId: $user->getId(),
        title: $data->title,
        description: $data->description,
        startsAt: new DateTimeImmutable($data->startsAt),
        endsAt: null !== $data->endsAt ? new DateTimeImmutable($data->endsAt) : null,
        allDay: $data->allDay,
        facilityId: $data->facilityId,
      ));
    } catch (Throwable $exception) {
      throw $this->mapCalendarException($exception);
    }

    return $this->outputFactory->fromView($result);
  }
  // #endregion
}
