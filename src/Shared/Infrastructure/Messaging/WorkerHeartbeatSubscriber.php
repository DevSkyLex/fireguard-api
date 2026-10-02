<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Messaging;

use Shared\Infrastructure\Exception\WorkerHeartbeatException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\{WorkerMessageHandledEvent, WorkerRunningEvent, WorkerStartedEvent};

use function fclose;
use function file_get_contents;
use function file_put_contents;
use function fopen;
use function fwrite;
use function getmypid;
use function in_array;
use function is_array;
use function is_dir;
use function is_file;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function mkdir;
use function rename;
use function str_starts_with;
use function strrpos;
use function substr;
use function sys_get_temp_dir;
use function time;

use const JSON_THROW_ON_ERROR;
use const LOCK_EX;

/**
 * Class WorkerHeartbeatSubscriber
 *
 * Records progress through the consumer loop, including idle polls. A live PID alone
 * cannot establish that the receiver has initialized or is still making progress.
 *
 * @category Messaging
 */
final readonly class WorkerHeartbeatSubscriber implements EventSubscriberInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @param string $heartbeatPath optional observation destination for isolated tests
   * @param string $sweepDirectory persistent bounded sweep timestamps, without payloads
   */
  public function __construct(private string $heartbeatPath = '', private string $sweepDirectory = '')
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method getSubscribedEvents
   *
   * Registers consumer lifecycle events without touching message payloads.
   *
   * @return array<class-string, string> lifecycle callbacks
   */
  public static function getSubscribedEvents(): array
  {
    return [WorkerStartedEvent::class => 'heartbeat', WorkerRunningEvent::class => 'heartbeat', WorkerMessageHandledEvent::class => 'sweepHandled'];
  }

  /**
   * Method heartbeat
   *
   * Replaces the per-container observation atomically; no token or payload is recorded.
   *
   * @param WorkerStartedEvent|WorkerRunningEvent $event consumer lifecycle event
   *
   * @return void
   */
  public function heartbeat(WorkerStartedEvent|WorkerRunningEvent $event): void
  {
    if ($event instanceof WorkerStartedEvent) {
      $this->initializeSchedulerMonitoring($event);
    }
    $path = '' !== $this->heartbeatPath ? $this->heartbeatPath : sys_get_temp_dir() . '/fireguard-worker-heartbeat.json';
    $temporary = $path . '.' . getmypid();
    $previous = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
    $startedAt = is_array($previous) && is_int($previous['started_at'] ?? null) ? $previous['started_at'] : time();
    $content = json_encode([
      'pid' => getmypid(),
      'timestamp' => time(),
      'started_at' => $event instanceof WorkerStartedEvent ? time() : $startedAt,
      'transports' => $event->getWorker()->getMetadata()->getTransportNames(),
    ], JSON_THROW_ON_ERROR);
    if (false === file_put_contents($temporary, $content, LOCK_EX) || !rename($temporary, $path)) {
      throw new WorkerHeartbeatException('Worker heartbeat could not be recorded.');
    }
  }

  /**
   * Method sweepHandled
   *
   * Records a successful known sweep separately from receiver-loop activity.
   *
   * @param WorkerMessageHandledEvent $event completed message metadata
   *
   * @return void
   */
  public function sweepHandled(WorkerMessageHandledEvent $event): void
  {
    $class = $event->getEnvelope()->getMessage()::class;
    $name = substr($class, (int) strrpos($class, '\\') + 1);
    if (!in_array($name, ['RecomputeMaintenanceSchedulesCommand', 'MaterializeDueRecurrencesCommand', 'ExpireStaleApprovalRequestsCommand', 'EscalateNonConformitySlaBreachesCommand', 'SendWeeklyDigestsCommand', 'VerifyOrganizationDomainsCommand'], true)) {
      return;
    }
    $directory = $this->sweepDirectoryPath();
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
      return;
    }
    $path = $directory . '/' . $name . '.timestamp';
    $temporary = $path . '.' . getmypid();
    if (false !== file_put_contents($temporary, (string) time(), LOCK_EX)) {
      rename($temporary, $path);
    }
  }

  /**
   * Method initializeSchedulerMonitoring
   *
   * Creates the persistent first-run baseline once when a scheduler receiver starts.
   * Competing workers keep the existing baseline through exclusive file creation.
   *
   * @access private
   *
   * @param WorkerStartedEvent $event receiver startup metadata
   *
   * @return void
   */
  private function initializeSchedulerMonitoring(WorkerStartedEvent $event): void
  {
    foreach ($event->getWorker()->getMetadata()->getTransportNames() as $transport) {
      if (!is_string($transport) || !str_starts_with($transport, 'scheduler_')) {
        continue;
      }
      $directory = $this->sweepDirectoryPath();
      if (is_dir($directory) || mkdir($directory, 0700, true) || is_dir($directory)) {
        $baseline = $directory . '/monitoring-start.timestamp';
        if (!is_file($baseline)) {
          $handle = fopen($baseline, 'x');
          if (false !== $handle) {
            fwrite($handle, (string) time());
            fclose($handle);
          }
        }
      }

      break;
    }
  }

  /**
   * Method sweepDirectoryPath
   *
   * Keeps startup baselines and successful sweep timestamps on the same persistent directory.
   *
   * @access private
   *
   * @return string configured or default observation directory
   */
  private function sweepDirectoryPath(): string
  {
    return '' !== $this->sweepDirectory ? $this->sweepDirectory : __DIR__ . '/../../../../var/worker-sweeps';
  }
  // #endregion
}
