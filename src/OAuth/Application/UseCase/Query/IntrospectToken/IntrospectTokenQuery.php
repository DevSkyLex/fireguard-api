<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\IntrospectToken;

use Shared\Application\Message\QueryMessage;

/**
 * Query IntrospectTokenQuery.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class IntrospectTokenQuery implements QueryMessage
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of the
     * IntrospectTokenQuery class.
     *
     * @since 1.0.0
     *
     * @param string $token         the token to introspect
     * @param string $tokenTypeHint the token type hint (access_token, refresh_token)
     */
    public function __construct(
        public string $token,
        public string $tokenTypeHint = 'access_token',
    ) {
    }
    // #endregion
}
