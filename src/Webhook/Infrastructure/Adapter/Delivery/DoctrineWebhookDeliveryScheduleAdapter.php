<?php

declare(strict_types=1);

namespace Webhook\Infrastructure\Adapter\Delivery;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Shared\Application\Port\Outbound\UuidGeneratorPort;
use Webhook\Application\Port\Outbound\Internal\WebhookDeliveryWorkPort;
use Webhook\Application\Port\Outbound\{WebhookDeliveryQueuePort, WebhookDeliverySchedulePort};

use function max;

/**
 * Class DoctrineWebhookDeliveryScheduleAdapter
 *
 * Enqueues only the winning wake reservation on the owning main connection.
 * A sender failure rolls back the wake and its enclosing business mutation.
 *
 * @category Adapter
 */
final readonly class DoctrineWebhookDeliveryScheduleAdapter implements WebhookDeliverySchedulePort
{
  /**
   * Method __construct
   *
   * @access public
   *
   * @param Connection $connection
   * @param WebhookDeliveryWorkPort $work
   * @param WebhookDeliveryQueuePort $queue
   * @param UuidGeneratorPort $ids
   *
   * @return void
   */
  public function __construct(private Connection $connection, private WebhookDeliveryWorkPort $work, private WebhookDeliveryQueuePort $queue, private UuidGeneratorPort $ids)
  {
  }

  /**
   * Method enqueue
   *
   * @access public
   *
   * @param string $id
   * @param int $generation
   * @param DateTimeImmutable $dueAt
   *
   * @return bool
   */
  public function enqueue(string $id, int $generation, DateTimeImmutable $dueAt): bool
  {
    return $this->connection->transactional(function () use ($id, $generation, $dueAt): bool {
      $now = new DateTimeImmutable();
      $token = $this->ids->generate();

      if (!$this->work->reserveWake($id, $generation, $token, $dueAt, $now)) {
        return false;
      }
      $this->queue->dispatch($id, $generation, $token, max(0, ($dueAt->getTimestamp() - $now->getTimestamp()) * 1000));

      return true;
    });
  }
}
