<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Invitation;

use Doctrine\DBAL\Connection;
use LogicException;
use Organization\Application\Port\Outbound\InvitationDeliveryQueuePort;
use Organization\Application\UseCase\Command\Organization\DeliverOrganizationInvitation\DeliverOrganizationInvitationCommand;
use SensitiveParameter;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

final readonly class MessengerInvitationDeliveryQueueAdapter implements InvitationDeliveryQueuePort
{
  public function __construct(private Connection $connection, private SenderInterface $sender)
  {
  }

  public function enqueue(string $invitationId, #[SensitiveParameter] string $acceptUrl, string $tokenHash): void
  {
    if (!$this->connection->isTransactionActive()) {
      throw new LogicException('Deferred invitations require a main transaction.');
    }
    $this->sender->send(new Envelope(new DeliverOrganizationInvitationCommand($invitationId, $acceptUrl, $tokenHash)));
  }
}
