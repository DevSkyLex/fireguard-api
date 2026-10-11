<?php

declare(strict_types=1);

namespace Webhook\Infrastructure\Scheduler;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\{RecurringMessage, Schedule, ScheduleProviderInterface};
use Symfony\Contracts\Cache\CacheInterface;
use Webhook\Application\UseCase\Command\Sweep\RecoverWebhookDeliveries\RecoverWebhookDeliveriesCommand;

/**
 * Class WebhookScheduleProvider
 *
 * Recovers expired leases and lost wake messages once a minute.
 *
 * @category Scheduler
 */
#[AsSchedule('webhook')]
final readonly class WebhookScheduleProvider implements ScheduleProviderInterface
{
  /**
   * Method __construct
   *
   * @access public
   *
   * @param CacheInterface $cache
   * @param LockFactory $lockFactory
   *
   * @return void
   */
  public function __construct(private CacheInterface $cache, private LockFactory $lockFactory)
  {
  }

  /**
   * Method getSchedule
   *
   * @access public
   *
   * @return Schedule
   */
  public function getSchedule(): Schedule
  {
    return new Schedule()
      ->add(RecurringMessage::every('1 minute', new RecoverWebhookDeliveriesCommand()))
      ->stateful($this->cache)
      ->lock($this->lockFactory->createLock('webhook.delivery_recovery'));
  }
}
