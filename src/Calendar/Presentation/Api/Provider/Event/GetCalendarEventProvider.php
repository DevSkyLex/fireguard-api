<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Provider\Event;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Calendar\Application\UseCase\Query\Event\GetCalendarEvent\{GetCalendarEventQuery, GetCalendarEventResult};
use Calendar\Presentation\Api\Dto\Output\Event\CalendarEventOutput;
use Calendar\Presentation\Api\Factory\CalendarEventOutputFactory;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function is_string;

/**
 * Provider GetCalendarEventProvider.
 *
 * @category Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<CalendarEventOutput>
 */
final readonly class GetCalendarEventProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param Security $security the security service
   * @param CalendarEventOutputFactory $outputFactory the output factory
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private Security $security,
    private CalendarEventOutputFactory $outputFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): CalendarEventOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $eventId = $uriVariables['eventId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId || !is_string($eventId) || '' === $eventId) {
      throw new BadRequestHttpException('OrganizationId and eventId URI parameters are required.');
    }

    /** @var GetCalendarEventResult $result */
    $result = $this->queryBus->ask(new GetCalendarEventQuery(
      userId: $user->getId(),
      organizationId: $organizationId,
      eventId: $eventId,
    ));

    return $this->outputFactory->fromView($result);
  }
  // #endregion
}
