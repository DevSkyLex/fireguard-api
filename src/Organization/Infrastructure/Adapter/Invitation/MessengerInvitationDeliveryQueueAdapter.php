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

/**
 * Class MessengerInvitationDeliveryQueueAdapter
 *
 * Queues invitation delivery through Messenger while the database transaction is active.
 *
 * @category Adapter
 */
final readonly class MessengerInvitationDeliveryQueueAdapter implements InvitationDeliveryQueuePort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Supplies the transaction connection and Messenger sender used to defer invitation delivery.
   *
   * @access public
   *
   * @param Connection $connection checks that invitation enqueueing is transactional
   * @param SenderInterface $sender sends the deferred delivery command
   *
   * @return void
   */
  public function __construct(private Connection $connection, private SenderInterface $sender)
  {
  }

  // #endregion
  // #region Methods
  /**
   * Method enqueue.
   *
   * Schedules invitation delivery and requires the owning transaction to remain active.
   *
   * @access public
   *
   * @param string $invitationId the invitation identifier
   * @param string $acceptUrl the generated invitation acceptance URL
   * @param string $tokenHash the stored invitation token hash
   *
   * @return void no return value
   *
   * @throws LogicException when no transaction is active
   */
  public function enqueue(string $invitationId, #[SensitiveParameter] string $acceptUrl, string $tokenHash): void
  {
    if (!$this->connection->isTransactionActive()) {
      throw new LogicException('Deferred invitations require a main transaction.');
    }
    $this->sender->send(new Envelope(new DeliverOrganizationInvitationCommand($invitationId, $acceptUrl, $tokenHash)));
  }
  // #endregion
}
