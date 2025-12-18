<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\IntrospectToken;

use Shared\Application\Message\ResultMessage;

/**
 * Result IntrospectTokenResult
 * @final
 *
 * Result of the IntrospectTokenQuery (RFC 7662).
 *
 * @category Result
 * @package OAuth\Application\UseCase\Query\IntrospectToken
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class IntrospectTokenResult implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * IntrospectTokenResult class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param bool $active Whether the token is active.
   * @param string|null $scope Space-separated scopes.
   * @param string|null $clientId The client identifier.
   * @param string|null $username The resource owner username.
   * @param string|null $tokenType The token type.
   * @param int|null $exp Expiration timestamp.
   * @param int|null $iat Issued at timestamp.
   * @param int|null $nbf Not before timestamp.
   * @param string|null $sub Subject (user ID).
   * @param string|null $aud Audience.
   * @param string|null $iss Issuer.
   * @param string|null $jti Token identifier.
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
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method inactive
   *
   * Creates an inactive result.
   *
   * @access public
   * @since 1.0.0
   *
   * @return self The inactive result.
   */
  public static function inactive(): self
  {
    return new self(active: false);
  }
  //#endregion
}
