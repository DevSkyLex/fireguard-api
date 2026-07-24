<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Query\Notification\GetUnreadNotificationsCount;

use Notification\Application\Port\Outbound\NotificationRepositoryPort;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetUnreadNotificationsCountHandler.
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetUnreadNotificationsCountHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.1.0
   *
   * @param NotificationRepositoryPort $notificationRepository the notification repository port
   */
  public function __construct(
    private NotificationRepositoryPort $notificationRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Returns the unread notification count for a user, optionally scoped to
   * one organization.
   *
   * @since 1.1.0
   *
   * @param GetUnreadNotificationsCountQuery $query the query payload
   *
   * @return GetUnreadNotificationsCountResult the use case result
   */
  public function __invoke(GetUnreadNotificationsCountQuery $query): GetUnreadNotificationsCountResult
  {
    $count = $this->notificationRepository->countUnreadByUserId(
      userId: $query->userId,
      organizationId: $query->organizationId,
    );

    return new GetUnreadNotificationsCountResult(count: $count);
  }
  // #endregion
}
