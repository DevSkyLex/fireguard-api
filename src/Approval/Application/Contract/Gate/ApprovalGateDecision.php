<?php

declare(strict_types=1);

namespace Approval\Application\Contract\Gate;

use DateTimeImmutable;

/**
 * Contract ApprovalGateDecision.
 *
 * The outcome of {@see \Approval\Application\Port\Inbound\ApprovalGatePort::evaluate()}:
 * either "apply now" (the owning module dispatches its existing command
 * unchanged) or "deferred" (a pending approval request was created and the
 * owning module's processor must return HTTP 202 instead).
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ApprovalGateDecision
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool $deferred whether the action was deferred behind an approval request
   * @param ?string $requestId the created/existing pending request identifier, when deferred
   * @param ?string $status the request status, when deferred
   * @param ?DateTimeImmutable $expiresAt the request's expiry deadline, when deferred
   */
  private function __construct(
    public bool $deferred,
    public ?string $requestId = null,
    public ?string $status = null,
    public ?DateTimeImmutable $expiresAt = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method applyNow.
   *
   * @static
   *
   * Creates a decision instructing the caller to apply the action
   * immediately.
   *
   * @since 1.0.0
   *
   * @return self the decision instance
   */
  public static function applyNow(): self
  {
    return new self(deferred: false);
  }

  /**
   * Method deferred.
   *
   * @static
   *
   * Creates a decision instructing the caller to defer the action: a
   * pending approval request was created (or already existed).
   *
   * @since 1.0.0
   *
   * @param string $requestId the pending request identifier
   * @param string $status the request status
   * @param DateTimeImmutable $expiresAt the request's expiry deadline
   *
   * @return self the decision instance
   */
  public static function deferred(string $requestId, string $status, DateTimeImmutable $expiresAt): self
  {
    return new self(deferred: true, requestId: $requestId, status: $status, expiresAt: $expiresAt);
  }
  // #endregion
}
