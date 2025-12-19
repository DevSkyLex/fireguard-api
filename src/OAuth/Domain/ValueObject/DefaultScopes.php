<?php

declare(strict_types=1);

namespace OAuth\Domain\ValueObject;

/**
 * Class DefaultScopes.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DefaultScopes
{
    // #region Constants
    /**
     * Constant USER_SCOPES.
     *
     * Default scopes for authenticated users.
     *
     * @since 1.0.0
     *
     * @var list<string>
     */
    public const array USER_SCOPES = [
        'OPENID',
        'PROFILE',
        'EMAIL',
        'READ',
        'WRITE',
    ];

    /**
     * Constant CLIENT_SCOPES.
     *
     * Default scopes for client credentials grant.
     *
     * @since 1.0.0
     *
     * @var list<string>
     */
    public const array CLIENT_SCOPES = [
        'READ',
        'WRITE',
    ];

    /**
     * Constant OPENID_SCOPES.
     *
     * OpenID Connect standard scopes.
     *
     * @since 1.0.0
     *
     * @var list<string>
     */
    public const array OPENID_SCOPES = [
        'OPENID',
        'PROFILE',
        'EMAIL',
    ];
    // #endregion
}
