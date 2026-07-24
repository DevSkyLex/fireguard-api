<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Provider\Feed;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Calendar\Application\Contract\Feed\CalendarFeedItem;
use Calendar\Application\UseCase\Query\Feed\GetCalendarFeed\{GetCalendarFeedQuery, GetCalendarFeedResult};
use Calendar\Presentation\Api\Dto\Output\Feed\{CalendarFeedItemOutput, CalendarFeedOutput};
use Calendar\Presentation\Api\Trait\CalendarExceptionMapperTrait;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function array_map;
use function is_array;
use function is_string;

/**
 * Provider GetCalendarFeedProvider.
 *
 * Thin request-shape extraction only; every business rule (permission,
 * range parsing, the 366-day cap, the merge) lives in
 * {@see \Calendar\Application\UseCase\Query\Feed\GetCalendarFeed\GetCalendarFeedHandler}
 * and {@see \Calendar\Application\Service\CalendarFeedAggregator}.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<CalendarFeedOutput>
 */
final readonly class GetCalendarFeedProvider implements ProviderInterface
{
  use CalendarExceptionMapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param Security $security the security service
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private Security $security,
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
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): CalendarFeedOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    $filters = $context['filters'] ?? [];
    $filters = is_array($filters) ? $filters : [];
    $from = $filters['from'] ?? null;
    $to = $filters['to'] ?? null;
    if (!is_string($from) || '' === $from || !is_string($to) || '' === $to) {
      throw new BadRequestHttpException('Both "from" and "to" query filters are required.');
    }

    try {
      /** @var GetCalendarFeedResult $result */
      $result = $this->queryBus->ask(new GetCalendarFeedQuery(
        userId: $user->getId(),
        organizationId: $organizationId,
        from: $from,
        to: $to,
      ));
    } catch (Throwable $exception) {
      throw $this->mapCalendarException($exception);
    }

    $output = new CalendarFeedOutput();
    $output->from = $result->from->format('c');
    $output->to = $result->to->format('c');
    $output->items = array_map($this->toItemOutput(...), $result->items);

    return $output;
  }

  /**
   * Method toItemOutput.
   *
   * @since 1.0.0
   *
   * @param CalendarFeedItem $item the feed item contract value
   *
   * @return CalendarFeedItemOutput the mapped output
   */
  private function toItemOutput(CalendarFeedItem $item): CalendarFeedItemOutput
  {
    $output = new CalendarFeedItemOutput();
    $output->sourceKey = $item->sourceKey;
    $output->id = $item->id;
    $output->title = $item->title;
    $output->description = $item->description;
    $output->startsAt = $item->startsAt->format('c');
    $output->endsAt = $item->endsAt?->format('c');
    $output->allDay = $item->allDay;
    $output->facilityId = $item->facilityId;
    $output->status = $item->status;
    $output->targetType = $item->targetType;
    $output->targetId = $item->targetId;

    return $output;
  }
  // #endregion
}
