<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Processor\Event;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Calendar\Application\UseCase\Command\Event\UpdateCalendarEvent\{UpdateCalendarEventCommand, UpdateCalendarEventResult};
use Calendar\Presentation\Api\Dto\Input\Event\UpdateCalendarEventInput;
use Calendar\Presentation\Api\Dto\Output\Event\CalendarEventOutput;
use Calendar\Presentation\Api\Factory\CalendarEventOutputFactory;
use Calendar\Presentation\Api\Trait\CalendarExceptionMapperTrait;
use DateTimeImmutable;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\MergePatchFields;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function array_key_exists;
use function is_string;

/**
 * Processor UpdateCalendarEventProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<UpdateCalendarEventInput, CalendarEventOutput>
 */
final readonly class UpdateCalendarEventProcessor implements ProcessorInterface
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
    private MergePatchFields $mergePatchFields,
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
    /** @var UpdateCalendarEventInput $data */
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
      $fields = $this->mergePatchFields->all();

      /** @var UpdateCalendarEventResult $result */
      $result = $this->commandBus->dispatch(new UpdateCalendarEventCommand(
        organizationId: $organizationId,
        actorUserId: $user->getId(),
        eventId: $eventId,
        title: $data->title,
        description: $data->description,
        startsAt: null !== $data->startsAt ? new DateTimeImmutable($data->startsAt) : null,
        endsAt: null !== $data->endsAt ? new DateTimeImmutable($data->endsAt) : null,
        allDay: $data->allDay,
        facilityId: $data->facilityId,
        hasTitle: array_key_exists('title', $fields),
        hasDescription: array_key_exists('description', $fields),
        hasStartsAt: array_key_exists('startsAt', $fields),
        hasEndsAt: array_key_exists('endsAt', $fields),
        hasAllDay: array_key_exists('allDay', $fields),
        hasFacilityId: array_key_exists('facilityId', $fields),
      ));
    } catch (Throwable $exception) {
      throw $this->mapCalendarException($exception);
    }

    return $this->outputFactory->fromView($result);
  }
  // #endregion
}
