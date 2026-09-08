<?php

declare(strict_types=1);

namespace User\Application\Port\Outbound;

use DateTimeImmutable;
use User\Domain\Model\EmailChange\EmailChangeRequest;
use User\Domain\ValueObject\UserId;

/**
 * Interface EmailChangeRequestRepositoryPort.
 *
 * Persistence port for pending email change requests (auth database).
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface EmailChangeRequestRepositoryPort
{
  /**
   * Method save.
   *
   * Persists a request (insert or update).
   *
   * @since 1.0.0
   *
   * @param EmailChangeRequest $request the request to persist
   *
   * @return void No return value
   */
  public function save(EmailChangeRequest $request): void;

  /**
   * Method findActiveByTokenHash.
   *
   * Finds the pending (unconfirmed, unexpired) request matching a
   * confirmation token hash.
   *
   * @since 1.0.0
   *
   * @param string $tokenHash the SHA-256 hash of the presented token
   * @param DateTimeImmutable $now the current time, for the expiry bound
   *
   * @return EmailChangeRequest|null the pending request, or null
   */
  public function findActiveByTokenHash(string $tokenHash, DateTimeImmutable $now): ?EmailChangeRequest;

  /**
   * Method confirmIfPending.
   *
   * Atomically marks a request confirmed if — and only if — it is still
   * pending (unconfirmed and unexpired) at the time of the write. The
   * conditional update is the single-use guard against two concurrent
   * confirmations of the same token: exactly one caller sees `true`.
   *
   * @since 1.0.0
   *
   * @param string $requestId the request identifier
   * @param DateTimeImmutable $now the confirmation time, also the expiry bound
   *
   * @return bool true when this call consumed the pending request, false when it was already confirmed or expired
   */
  public function confirmIfPending(string $requestId, DateTimeImmutable $now): bool;

  /**
   * Method findActiveByUserId.
   *
   * Finds the user's pending (unconfirmed, unexpired) request, if any.
   *
   * @since 1.0.0
   *
   * @param UserId $userId the user identifier
   * @param DateTimeImmutable $now the current time, for the expiry bound
   *
   * @return EmailChangeRequest|null the pending request, or null
   */
  public function findActiveByUserId(UserId $userId, DateTimeImmutable $now): ?EmailChangeRequest;

  /**
   * Method removePendingForUser.
   *
   * Deletes every unconfirmed request of the user, expired or not.
   * Called before saving a new request (one pending request per user)
   * and when the user cancels.
   *
   * @since 1.0.0
   *
   * @param UserId $userId the user identifier
   *
   * @return int the number of deleted requests
   */
  public function removePendingForUser(UserId $userId): int;
}
