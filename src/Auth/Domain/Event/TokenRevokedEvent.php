<?php

declare(strict_types=1);

namespace Auth\Domain\Event;

use DateTimeImmutable;

/**
 * Event TokenRevokedEvent
 * @final
 *
 * Domain event raised when a token is revoked.
 *
 * @category Event
 * @package Auth\Domain\Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TokenRevokedEvent
{
  //#region Properties
  /**
   * Property occurredAt
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
   * TokenRevokedEvent class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $tokenId The token identifier.
   * @param string $tokenType The token type (access_token, refresh_token).
   * @param string|null $reason The revocation reason.
   */
  public function __construct(
    public string $tokenId,
    public string $tokenType,
    public ?string $reason = null,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  //#endregion
}
