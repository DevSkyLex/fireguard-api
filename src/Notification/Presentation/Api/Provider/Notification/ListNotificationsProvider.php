<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Provider\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Notification\Application\UseCase\Query\Notification\GetUserNotification\GetUserNotificationResult;
use Notification\Application\UseCase\Query\Notification\ListUserNotifications\{ListUserNotificationsQuery, ListUserNotificationsResult};
use Notification\Presentation\Api\Dto\Output\Notification\NotificationOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function array_map;
use function in_array;
use function is_numeric;
use function is_string;
use function max;
use function min;
use function strtolower;

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
   * @return list<NotificationOutput> the notifications
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    /** @var array<string, mixed> $filters */
    $filters = $context['filters'] ?? [];

    $onlyUnread = $this->toBool($filters['unreadOnly'] ?? false);
    $limit = $this->toLimit($filters['limit'] ?? 50);

    /** @var ListUserNotificationsResult $result */
    $result = $this->queryBus->ask(new ListUserNotificationsQuery(
      userId: $user->getId(),
      onlyUnread: $onlyUnread,
      limit: $limit,
    ));

    return array_map(
      fn (GetUserNotificationResult $notification): NotificationOutput => $this->mapOutput($notification),
      $result->notifications,
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
    $output->subject = $result->subject;
    $output->body = $result->body;
    $output->channels = $result->channels;
    $output->payload = $result->payload;
    $output->isRead = $result->isRead;
    $output->createdAt = $result->createdAt->format('c');
    $output->readAt = null !== $result->readAt ? $result->readAt->format('c') : null;

    return $output;
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

  /**
   * Method toLimit.
   *
   * @since 1.0.0
   *
   * @param mixed $value the raw value
   *
   * @return int the normalized limit
   */
  private function toLimit(mixed $value): int
  {
    if (!is_numeric($value)) {
      return 50;
    }

    $normalized = (int) $value;

    return min(100, max(1, $normalized));
  }
  // #endregion
}
