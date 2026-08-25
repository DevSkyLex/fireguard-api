<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Processor\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Notification\Application\Contract\Notification\NotificationType;
use Notification\Application\UseCase\Command\Notification\MarkNotificationAsRead\{MarkNotificationAsReadCommand, MarkNotificationAsReadResult};
use Notification\Presentation\Api\Dto\Output\Notification\NotificationOutput;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

use function is_string;

/**
 * Processor MarkNotificationAsReadProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<object, NotificationOutput>
 */
final readonly class MarkNotificationAsReadProcessor implements ProcessorInterface
{
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
   * @param Operation $operation the operation metadata
   * @param array<string, mixed> $uriVariables URI variables
   * @param array<string, mixed> $context processor context
   *
   * @return NotificationOutput the notification output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NotificationOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $notificationId = $uriVariables['id'] ?? null;
    if (!is_string($notificationId) || '' === $notificationId) {
      throw new NotFoundHttpException('Notification not found.');
    }

    /** @var MarkNotificationAsReadResult $result */
    $result = $this->commandBus->dispatch(new MarkNotificationAsReadCommand(
      userId: $user->getId(),
      notificationId: $notificationId,
    ));

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

  // #endregion
}
