<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\CheckConsent;

use Shared\Application\Message\ResultMessage;

/**
 * Result CheckConsentResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CheckConsentResult implements ResultMessage
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of the
     * CheckConsentResult class.
     *
     * @since 1.0.0
     *
     * @param bool         $hasConsent            whether consent exists
     * @param list<string> $grantedScopes         the granted scopes
     * @param list<string> $missingScopes         the missing scopes
     * @param bool         $requiresConsentScreen whether consent screen should be shown
     */
    public function __construct(
        public readonly bool $hasConsent,
        public readonly array $grantedScopes,
        public readonly array $missingScopes,
        public readonly bool $requiresConsentScreen,
    ) {
    }
    // #endregion
}
