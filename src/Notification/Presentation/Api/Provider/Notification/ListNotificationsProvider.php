<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Provider\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Notification\Application\UseCase\Query\Notification\GetUserNotification\GetUserNotificationResult;
use Notification\Application\UseCase\Query\Notification\ListUserNotifications\{ListUserNotificationsQuery, ListUserNotificationsResult};
use Notification\Domain\ValueObject\NotificationType;
use Notification\Presentation\Api\Dto\Output\Notification\NotificationOutput;
use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Pagination\PaginationExtractor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function array_map;
use function in_array;
use function is_numeric;
use function is_string;
use function strtolower;
use function trim;

/**
 * Provider ListNotificationsProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<NotificationOutput>
 */
final readonly class ListNotificationsProvider implements ProviderInterface
{
  private const int DEFAULT_ITEMS_PER_PAGE = 20;

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
   * @param Operation $operation the operation metadata
   * @param array<string, mixed> $uriVariables URI variables
   * @param array<string, mixed> $context provider context
   *
   * @return TraversablePaginator<NotificationOutput> the paginated notifications
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    /** @var array<string, mixed> $filters */
    $filters = $context['filters'] ?? [];

    $onlyUnread = $this->toBool($filters['unreadOnly'] ?? false);
    $type = $this->toNullableString($filters['type'] ?? null);
    $category = $this->toNullableString($filters['category'] ?? null);
    $organizationId = $this->toNullableString($filters['organization'] ?? null);

    // Backward compatibility: before this endpoint became a real paginated
    // collection it accepted a bare `limit`, and existing clients still send
    // it. `limit` is therefore honoured as an alias for `itemsPerPage` —
    // WITHOUT it, such a client silently gets the default page size while its
    // own paginator computes offsets for the size it asked for, which skips
    // and duplicates rows rather than failing visibly.
    //
    // Deliberately scoped to this provider rather than added to the shared
    // `PaginationExtractor`: `limit` is legacy vocabulary for this one
    // endpoint, and teaching every collection in the API a second pagination
    // parameter would be a far larger contract change than the one being
    // repaired. `itemsPerPage` wins when both are present.
    $legacyLimit = $filters['limit'] ?? null;
    if (!isset($filters['itemsPerPage']) && is_numeric($legacyLimit)) {
      $filters['itemsPerPage'] = (int) $legacyLimit;
    }

    $pagination = PaginationExtractor::fromContext(['filters' => $filters], self::DEFAULT_ITEMS_PER_PAGE);

    /** @var ListUserNotificationsResult $result */
    $result = $this->queryBus->ask(new ListUserNotificationsQuery(
      userId: $user->getId(),
      onlyUnread: $onlyUnread,
      pagination: new Pagination(offset: $pagination->offset, limit: $pagination->itemsPerPage),
      type: $type,
      category: $category,
      organizationId: $organizationId,
    ));

    $outputs = array_map(
      fn (GetUserNotificationResult $notification): NotificationOutput => $this->mapOutput($notification),
      $result->notifications,
    );

    return new TraversablePaginator(
      traversable: new ArrayIterator($outputs),
      currentPage: (float) $pagination->page,
      itemsPerPage: (float) $pagination->itemsPerPage,
      totalItems: (float) $result->total,
    );
  }

  /**
   * Method mapOutput.
   *
   * @since 1.0.0
   *
   * @param GetUserNotificationResult $result the query result
   *
   * @return NotificationOutput the API output
   */
  private function mapOutput(GetUserNotificationResult $result): NotificationOutput
  {
    $output = new NotificationOutput();
    $output->id = $result->id;
    $output->type = $result->type;
    $output->category = NotificationType::category($result->type);
    $output->subject = $result->subject;
    $output->body = $result->body;
    $output->channels = $result->channels;
    $output->payload = $result->payload;
    $output->isRead = $result->isRead;
    $output->createdAt = $result->createdAt->format('c');
    $output->readAt = null !== $result->readAt ? $result->readAt->format('c') : null;
    $output->organizationId = $result->organizationId;

    return $output;
  }

  /**
   * Method toNullableString.
   *
   * @since 1.0.0
   *
   * @param mixed $value the raw filter value
   *
   * @return string|null trimmed non-empty string, or null
   */
  private function toNullableString(mixed $value): ?string
  {
    if (!is_string($value)) {
      return null;
    }

    $trimmed = trim($value);

    return '' !== $trimmed ? $trimmed : null;
  }

  /**
   * Method toBool.
   *
   * @since 1.0.0
   *
   * @param mixed $value the raw value
   *
   * @return bool the normalized boolean
   */
  private function toBool(mixed $value): bool
  {
    if (true === $value || false === $value) {
      return $value;
    }

    if (!is_string($value)) {
      return false;
    }

    return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
  }
  // #endregion
}
