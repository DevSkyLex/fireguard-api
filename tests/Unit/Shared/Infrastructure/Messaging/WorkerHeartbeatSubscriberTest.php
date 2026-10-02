<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Messaging;

use Maintenance\Application\UseCase\Command\Sweep\RecomputeMaintenanceSchedules\RecomputeMaintenanceSchedulesCommand;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Messaging\WorkerHeartbeatSubscriber;
use Symfony\Component\Messenger\{Envelope, MessageBusInterface, Worker};
use Symfony\Component\Messenger\Event\{WorkerMessageHandledEvent, WorkerRunningEvent, WorkerStartedEvent};
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

use function array_keys;
use function file_get_contents;
use function file_put_contents;
use function getmypid;
use function is_file;
use function json_decode;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function tempnam;
use function time;
use function unlink;

use const JSON_THROW_ON_ERROR;

final class WorkerHeartbeatSubscriberTest extends TestCase
{
  public function testSchedulerStartupKeepsThePersistentFirstRunBaselineAcrossRestarts(): void
  {
    $path = tempnam(sys_get_temp_dir(), 'fg-worker-health-');
    self::assertNotFalse($path);
    $directory = $path . '-sweeps';
    mkdir($directory, 0700);
    $baseline = $directory . '/monitoring-start.timestamp';
    $sweep = $directory . '/RecomputeMaintenanceSchedulesCommand.timestamp';

    try {
      $worker = new Worker(['scheduler_maintenance' => $this->createStub(ReceiverInterface::class)], $this->createStub(MessageBusInterface::class));
      $subscriber = new WorkerHeartbeatSubscriber($path, $directory);
      $subscriber->heartbeat(new WorkerStartedEvent($worker));
      self::assertGreaterThanOrEqual(time() - 1, (int) file_get_contents($baseline));
      file_put_contents($baseline, '123');
      $subscriber->heartbeat(new WorkerStartedEvent($worker));
      self::assertSame('123', file_get_contents($baseline));
      $subscriber->sweepHandled(new WorkerMessageHandledEvent(new Envelope(new RecomputeMaintenanceSchedulesCommand()), 'scheduler_maintenance'));
      self::assertGreaterThanOrEqual(time() - 1, (int) file_get_contents($sweep));
      self::assertSame('123', file_get_contents($baseline));
    } finally {
      unlink($path);
      unlink($baseline);
      if (is_file($sweep)) {
        unlink($sweep);
      }
      rmdir($directory);
    }
  }

  public function testStartedAndIdleConsumerLoopsRefreshTheirReceiverIdentityWithoutPayloads(): void
  {
    $path = tempnam(sys_get_temp_dir(), 'fg-worker-health-');
    self::assertNotFalse($path);

    try {
      $worker = new Worker(['main_outbox' => $this->createStub(ReceiverInterface::class)], $this->createStub(MessageBusInterface::class));
      $subscriber = new WorkerHeartbeatSubscriber($path);
      foreach ([new WorkerStartedEvent($worker), new WorkerRunningEvent($worker, true)] as $event) {
        $subscriber->heartbeat($event);
        $state = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($state);
        self::assertSame(['pid', 'timestamp', 'started_at', 'transports'], array_keys($state));
        self::assertSame(getmypid(), $state['pid']);
        self::assertSame(['main_outbox'], $state['transports']);
        self::assertGreaterThanOrEqual(time() - 1, $state['timestamp']);
      }
    } finally {
      unlink($path);
    }
  }
}
