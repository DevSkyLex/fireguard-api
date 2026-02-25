<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Provider\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Notification\Application\Exception\NotificationNotFoundException;
use Notification\Application\UseCase\Query\Notification\GetUserNotification\{GetUserNotificationQuery, GetUserNotificationResult};
use Notification\Domain\ValueObject\NotificationType;
use Notification\Presentation\Api\Dto\Output\Notification\NotificationOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};
use Throwable;

use function is_string;

/**
 * Provider GetNotificationProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<NotificationOutput>
 */
final readonly class GetNotificationProvider implements ProviderInterface
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
   * @return NotificationOutput the notification output
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): NotificationOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $notificationId = $uriVariables['id'] ?? null;
    if (!is_string($notificationId) || '' === $notificationId) {
      throw new NotFoundHttpException('Notification not found.');
    }

    try {
      /** @var GetUserNotificationResult $result */
      $result = $this->queryBus->ask(new GetUserNotificationQuery(
        userId: $user->getId(),
        notificationId: $notificationId,
      ));
    } catch (Throwable $exception) {
      $notFound = $this->findNotificationNotFound($exception);
      if (null !== $notFound) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      throw $exception;
    }

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

    return $output;
  }

  /**
   * Method findNotificationNotFound.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception chain root
   *
   * @return NotificationNotFoundException|null the not found exception when present
   */
  private function findNotificationNotFound(Throwable $exception): ?NotificationNotFoundException
  {
    $current = $exception;
    while (null !== $current) {
      if ($current instanceof NotificationNotFoundException) {
        return $current;
      }

      $current = $current->getPrevious();
    }

    return null;
  }

  // #endregion
}
