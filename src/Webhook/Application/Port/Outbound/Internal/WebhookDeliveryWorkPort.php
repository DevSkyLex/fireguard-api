<?php

declare(strict_types=1);

namespace Webhook\Application\Port\Outbound\Internal;

use DateTimeImmutable;
use Webhook\Application\Contract\Delivery\PendingWebhookDelivery;
use Webhook\Domain\Model\Delivery\WebhookDelivery;

/**
 * Interface WebhookDeliveryWorkPort
 *
 * Owner-internal durable ownership, fencing and wake reservations on main.
 * Claims commit independently; wake reservations join the caller's transaction.
 *
 * @category Outbound Port
 */
interface WebhookDeliveryWorkPort
{
  // #region Methods
  /**
   * Method claim
   * Claims an eligible generation for 90 seconds; null means absent, busy, future or terminal.
   *
   * @access public
   *
   * @param string $id
   * @param int $generation
   * @param ?string $wakeToken
   * @param string $attemptToken
   * @param DateTimeImmutable $now
   *
   * @return ?WebhookDelivery
   */
  public function claim(string $id, int $generation, ?string $wakeToken, string $attemptToken, DateTimeImmutable $now): ?WebhookDelivery;

  /**
   * Method startHttpAttempt
   * Durably consumes one HTTP attempt only while the generation/token lease is owned.
   *
   * @access public
   *
   * @param string $id
   * @param int $generation
   * @param string $attemptToken
   *
   * @return bool
   */
  public function startHttpAttempt(string $id, int $generation, string $attemptToken): bool;

  /**
   * Method finish
   * Confirms an unexpired owned attempt; false means a stale response must be discarded.
   *
   * @access public
   *
   * @param WebhookDelivery $delivery
   * @param int $generation
   * @param string $attemptToken
   *
   * @return bool
   */
  public function finish(WebhookDelivery $delivery, int $generation, string $attemptToken): bool;

  /**
   * Method reopen
   * Atomically resets manual redelivery and returns its new generation, or null when absent.
   *
   * @access public
   *
   * @param string $id
   * @param string $subscriptionId
   * @param DateTimeImmutable $now
   *
   * @return ?int
   */
  public function reopen(string $id, string $subscriptionId, DateTimeImmutable $now): ?int;

  /**
   * Method reserveWake
   * Reserves one wake; enqueue must share its main transaction, including failure rollback.
   *
   * @access public
   *
   * @param string $id
   * @param int $generation
   * @param string $wakeToken
   * @param DateTimeImmutable $dueAt
   * @param DateTimeImmutable $now
   *
   * @return bool
   */
  public function reserveWake(string $id, int $generation, string $wakeToken, DateTimeImmutable $dueAt, DateTimeImmutable $now): bool;

  /**
   * Method recoverable
   * Returns due recoverable identities and closes exhausted crashed HTTP attempts.
   *
   * @access public
   *
   * @param DateTimeImmutable $now
   * @param int $limit
   *
   * @return list<PendingWebhookDelivery>
   */
  public function recoverable(DateTimeImmutable $now, int $limit): array;

  /**
   * Method technicalFailure
   * Releases only the owning lease and persists the bounded technical budget.
   *
   * @access public
   *
   * @param string $id
   * @param int $generation
   * @param string $attemptToken
   * @param DateTimeImmutable $now
   *
   * @return void
   */
  public function technicalFailure(string $id, int $generation, string $attemptToken, DateTimeImmutable $now): void;

  /**
   * Method quarantine
   * Excludes a technically exhausted generation from automatic recovery.
   *
   * @access public
   *
   * @param string $id
   * @param int $generation
   * @param ?string $commandToken
   *
   * @return void
   */
  public function quarantine(string $id, int $generation, ?string $commandToken = null): void;
  // #endregion
}
