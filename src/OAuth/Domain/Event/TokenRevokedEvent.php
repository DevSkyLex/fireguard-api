<?php

declare(strict_types=1);

namespace OAuth\Domain\Event;

use DateTimeImmutable;

/**
 * Event TokenRevokedEvent.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TokenRevokedEvent
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
     * of the TokenRevokedEvent class.
     *
     * @since 1.0.0
     *
     * @param string      $tokenId   the token identifier
     * @param string      $tokenType the token type (access_token, refresh_token)
     * @param string|null $reason    the revocation reason
     */
    public function __construct(
        public string $tokenId,
        public string $tokenType,
        public ?string $reason = null,
    ) {
        $this->occurredAt = new DateTimeImmutable();
    }
    // #endregion
}
