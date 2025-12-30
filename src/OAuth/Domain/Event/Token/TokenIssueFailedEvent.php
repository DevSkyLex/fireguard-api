<?php

declare(strict_types=1);

namespace OAuth\Domain\Event\Token;

use DateTimeImmutable;

/**
 * Event TokenIssueFailedEvent.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TokenIssueFailedEvent
{
  // #region Properties
  /**
   * Property occurredAt.
   *
   * The timestamp when the event occurred.
   *
   * @since 1.0.0
   */
  public DateTimeImmutable $occurredAt;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance
   * of the TokenIssueFailedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $grantType the grant type used
   * @param string $clientId the client identifier
   * @param string|null $ipAddress the client IP address
   * @param string $reason the failure reason
   */
  public function __construct(
    public readonly string $grantType,
    public readonly string $clientId,
    public readonly ?string $ipAddress,
    public readonly string $reason,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
