<?php

declare(strict_types=1);

namespace OAuth\Domain\Event;

use DateTimeImmutable;

/**
 * Event TokenRefreshFailedEvent.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TokenRefreshFailedEvent
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
     * of the TokenRefreshFailedEvent class.
     *
     * @since 1.0.0
     *
     * @param string|null $userId    the user ID if known
     * @param string|null $ipAddress the IP address
     * @param string      $reason    the failure reason
     */
    public function __construct(
        public readonly ?string $userId,
        public readonly ?string $ipAddress,
        public readonly string $reason,
    ) {
        $this->occurredAt = new DateTimeImmutable();
    }
    // #endregion
}
