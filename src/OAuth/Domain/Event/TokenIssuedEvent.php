<?php

declare(strict_types=1);

namespace OAuth\Domain\Event;

use DateTimeImmutable;

/**
 * Event TokenIssuedEvent
 * @final
 *
 * Domain event raised when a token is issued.
 *
 * @category Event
 * @package Auth\Domain\Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TokenIssuedEvent
{
  //#region Properties
  /**
   * Property occurredAt
   *
   * The timestamp when the event occurred.
   *
   * @access public
   * @since 1.0.0
   *
   * @var DateTimeImmutable
   */
  public DateTimeImmutable $occurredAt;
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * TokenIssuedEvent class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $tokenId The token identifier.
   * @param string $grantType The grant type used.
   * @param string $clientId The client identifier.
   * @param string|null $userId The user ID (if applicable).
   * @param list<string> $scopes The granted scopes.
   * @param int $expiresIn The token lifetime in seconds.
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
  //#endregion
}
