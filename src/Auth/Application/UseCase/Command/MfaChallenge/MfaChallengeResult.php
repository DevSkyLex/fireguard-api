<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\MfaChallenge;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * Result MfaChallengeResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MfaChallengeResult implements ResultMessage
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of the MfaChallengeResult class.
     *
     * @since 1.0.0
     *
     * @param string            $challengeToken  the challenge token for verification
     * @param string            $maskedRecipient the masked recipient for display
     * @param DateTimeImmutable $expiresAt       when the challenge expires
     * @param int               $maxAttempts     maximum verification attempts allowed
     */
    public function __construct(
        public readonly string $challengeToken,
        public readonly string $maskedRecipient,
        public readonly DateTimeImmutable $expiresAt,
        public readonly int $maxAttempts,
    ) {
    }
    // #endregion
}
