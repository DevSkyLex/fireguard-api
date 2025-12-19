<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\IntrospectToken;

use Shared\Application\Message\ResultMessage;

/**
 * Result IntrospectTokenResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class IntrospectTokenResult implements ResultMessage
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of the
     * IntrospectTokenResult class.
     *
     * @since 1.0.0
     *
     * @param bool        $active    whether the token is active
     * @param string|null $scope     space-separated scopes
     * @param string|null $clientId  the client identifier
     * @param string|null $username  the resource owner username
     * @param string|null $tokenType the token type
     * @param int|null    $exp       expiration timestamp
     * @param int|null    $iat       issued at timestamp
     * @param int|null    $nbf       not before timestamp
     * @param string|null $sub       subject (user ID)
     * @param string|null $aud       audience
     * @param string|null $iss       issuer
     * @param string|null $jti       token identifier
     */
    public function __construct(
        public bool $active,
        public ?string $scope = null,
        public ?string $clientId = null,
        public ?string $username = null,
        public ?string $tokenType = null,
        public ?int $exp = null,
        public ?int $iat = null,
        public ?int $nbf = null,
        public ?string $sub = null,
        public ?string $aud = null,
        public ?string $iss = null,
        public ?string $jti = null,
    ) {
    }
    // #endregion

    // #region Methods
    /**
     * Method inactive.
     *
     * Creates an inactive result.
     *
     * @since 1.0.0
     *
     * @return self the inactive result
     */
    public static function inactive(): self
    {
        return new self(active: false);
    }
    // #endregion
}
