<?php

declare(strict_types=1);

namespace OAuth\Domain\Event;

use DateTimeImmutable;

/**
 * Event TokenIssuedEvent.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TokenIssuedEvent
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
   * Initializes a new instance of the
   * TokenIssuedEvent class.
   *
   * @since 1.0.0
   *
   * @param string       $tokenId   the token identifier
   * @param string       $grantType the grant type used
   * @param string       $clientId  the client identifier
   * @param string|null  $userId    the user ID (if applicable)
   * @param list<string> $scopes    the granted scopes
   * @param int          $expiresIn the token lifetime in seconds
   */
  public function __construct(
    public readonly string $tokenId,
    public readonly string $grantType,
    public readonly string $clientId,
    public readonly ?string $userId = null,
    public readonly array $scopes = [],
    public readonly int $expiresIn = 0,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
