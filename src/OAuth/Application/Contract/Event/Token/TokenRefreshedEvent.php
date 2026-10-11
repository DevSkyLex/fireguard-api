<?php

declare(strict_types=1);

namespace OAuth\Application\Contract\Event\Token;

use DateTimeImmutable;

/**
 * Event TokenRefreshedEvent.
 *
 * @category Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TokenRefreshedEvent
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
   * of the TokenRefreshedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   * @param string|null $ipAddress the IP address
   * @param ?DateTimeImmutable $occurredAt persisted occurrence time, or null for a new event
   */
  public function __construct(
    public readonly string $userId,
    public readonly ?string $ipAddress,
    ?DateTimeImmutable $occurredAt = null,
  ) {
    $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
  }
  // #endregion
}
