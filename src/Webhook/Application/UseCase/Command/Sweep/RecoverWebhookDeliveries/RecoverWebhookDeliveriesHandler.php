<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Command\Sweep\RecoverWebhookDeliveries;

use DateTimeImmutable;
use Shared\Application\Message\{CommandHandler, VoidResult};
use Webhook\Application\Port\Outbound\Internal\WebhookDeliveryWorkPort;
use Webhook\Application\Port\Outbound\WebhookDeliverySchedulePort;

/**
 * Class RecoverWebhookDeliveriesHandler
 *
 * Recovery shares the same atomic wake reservation as normal delivery.
 *
 * @category UseCase
 */
final readonly class RecoverWebhookDeliveriesHandler implements CommandHandler
{
  /**
   * Method __construct
   *
   * @access public
   *
   * @param WebhookDeliveryWorkPort $work
   * @param WebhookDeliverySchedulePort $schedule
   *
   * @return void
   */
  public function __construct(private WebhookDeliveryWorkPort $work, private WebhookDeliverySchedulePort $schedule)
  {
  }

  /**
   * Method __invoke
   *
   * @access public
   *
   * @param RecoverWebhookDeliveriesCommand $command
   *
   * @return VoidResult
   */
  public function __invoke(RecoverWebhookDeliveriesCommand $command): VoidResult
  {
    $now = new DateTimeImmutable();

    foreach ($this->work->recoverable($now, 1000) as $pending) {
      $this->schedule->enqueue($pending->id, $pending->generation, $now);
    }

    return new VoidResult();
  }
}
