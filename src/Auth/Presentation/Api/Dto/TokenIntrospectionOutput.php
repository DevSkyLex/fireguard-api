<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto;

use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO TokenIntrospectionOutput
 * @final
 *
 * DTO for OAuth2 Token Introspection Output (RFC 7662).
 *
 * @category DTO
 * @package Auth\Presentation\Api\Dto
 * @version 1.0.0
 *
 * @see https://datatracker.ietf.org/doc/html/rfc7662
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TokenIntrospectionOutput
{
  //#region Properties
  /**
   * Property active
   *
   * Boolean indicator of whether the token is currently active.
   * This is the only REQUIRED field in the response.
   *
   * @access public
   * @since 1.0.0
   *
   * @var bool
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public bool $active = false;

  /**
   * Property scope
   *
   * A space-separated list of scopes associated with this token.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $scope = null;

  /**
   * Property clientId
   *
   * Client identifier for the OAuth 2.0 client that requested this token.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $clientId = null;

  /**
   * Property username
   *
   * Human-readable identifier for the resource owner who authorized this token.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $username = null;

  /**
   * Property tokenType
   *
   * Type of the token (e.g., "Bearer").
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $tokenType = null;

  /**
   * Property exp
   *
   * Integer timestamp (seconds since epoch) indicating when this token expires.
   *
   * @access public
   * @since 1.0.0
   *
   * @var int|null
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?int $exp = null;

  /**
   * Property iat
   *
   * Integer timestamp (seconds since epoch) indicating when this token was issued.
   *
   * @access public
   * @since 1.0.0
   *
   * @var int|null
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?int $iat = null;

  /**
   * Property nbf
   *
   * Integer timestamp (seconds since epoch) indicating when this token is not to be used before.
   *
   * @access public
   * @since 1.0.0
   *
   * @var int|null
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?int $nbf = null;

  /**
   * Property sub
   *
   * Subject of the token (usually the user identifier).
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $sub = null;

  /**
   * Property aud
   *
   * Audience of the token (service or resource the token is intended for).
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $aud = null;

  /**
   * Property iss
   *
   * Issuer of the token.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $iss = null;

  /**
   * Property jti
   *
   * Unique identifier for the token.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $jti = null;
  //#endregion
}
