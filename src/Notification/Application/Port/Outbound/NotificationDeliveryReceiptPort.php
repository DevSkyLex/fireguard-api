<?php

declare(strict_types=1);

namespace Notification\Application\Port\Outbound;

use Notification\Domain\Model\Notification\Notification;

/**
 * Stores durable notification identities and terminal channel outcomes.
 *
 * @category Port
 */
interface NotificationDeliveryReceiptPort
{
  /**
   * @template T
   *
   * @param callable(): T $work delivery operation
   *
   * @return T delivery result
   */
  public function synchronized(string $key, callable $work): mixed;

  /**
   * Returns the persisted inbox notification for this delivery identity.
   */
  public function find(string $key): ?Notification;

  /**
   * Atomically stores the inbox row and its stable receipt identity.
   */
  public function save(string $key, Notification $notification): void;

  /**
   * @return array<string, string> terminal outcomes for acknowledged channels
   */
  public function statuses(string $key): array;

  /**
   * Records delivered or intentionally suppressed channels independently of fan-out retries.
   */
  public function record(string $key, string $channel, string $status): void;
}
