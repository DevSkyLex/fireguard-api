<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto;

use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * DTO OpenIdConfigurationOutput
 * @final
 *
 * DTO for OpenID Connect Discovery endpoint response.
 * Provides metadata about the OpenID Provider's configuration.
 *
 * @category DTO
 * @package Auth\Presentation\Api\Dto
 * @version 1.0.0
 *
 * @see https://openid.net/specs/openid-connect-discovery-1_0.html
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OpenIdConfigurationOutput
{
  //#region Properties
  /**
   * Property issuer
   *
   * URL using the https scheme with no query or fragment component
   * that the OP asserts as its Issuer Identifier.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $issuer = null;

  /**
   * Property authorizationEndpoint
   *
   * URL of the OP's OAuth 2.0 Authorization Endpoint.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[SerializedName('authorization_endpoint')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $authorizationEndpoint = null;

  /**
   * Property tokenEndpoint
   *
   * URL of the OP's OAuth 2.0 Token Endpoint.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[SerializedName('token_endpoint')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $tokenEndpoint = null;

  /**
   * Property userinfoEndpoint
   *
   * URL of the OP's UserInfo Endpoint.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[SerializedName('userinfo_endpoint')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $userinfoEndpoint = null;

  /**
   * Property jwksUri
   *
   * URL of the OP's JSON Web Key Set document.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[SerializedName('jwks_uri')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $jwksUri = null;

  /**
   * Property revocationEndpoint
   *
   * URL of the OP's OAuth 2.0 Token Revocation Endpoint.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[SerializedName('revocation_endpoint')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $revocationEndpoint = null;

  /**
   * Property introspectionEndpoint
   *
   * URL of the OP's OAuth 2.0 Token Introspection Endpoint.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[SerializedName('introspection_endpoint')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $introspectionEndpoint = null;

  /**
   * Property scopesSupported
   *
   * JSON array containing a list of the OAuth 2.0 scope values supported.
   *
   * @access public
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[SerializedName('scopes_supported')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public array $scopesSupported = [];

  /**
   * Property responseTypesSupported
   *
   * JSON array containing a list of the OAuth 2.0 response_type values supported.
   *
   * @access public
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[SerializedName('response_types_supported')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public array $responseTypesSupported = [];

  /**
   * Property grantTypesSupported
   *
   * JSON array containing a list of the OAuth 2.0 Grant Type values supported.
   *
   * @access public
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[SerializedName('grant_types_supported')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public array $grantTypesSupported = [];

  /**
   * Property tokenEndpointAuthMethodsSupported
   *
   * JSON array containing a list of Client Authentication methods supported.
   *
   * @access public
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[SerializedName('token_endpoint_auth_methods_supported')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public array $tokenEndpointAuthMethodsSupported = [];

  /**
   * Property codeChallengeMethodsSupported
   *
   * JSON array containing a list of PKCE code challenge methods supported.
   *
   * @access public
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[SerializedName('code_challenge_methods_supported')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public array $codeChallengeMethodsSupported = [];

  /**
   * Property subjectTypesSupported
   *
   * JSON array containing a list of the Subject Identifier types supported.
   *
   * @access public
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[SerializedName('subject_types_supported')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public array $subjectTypesSupported = [];

  /**
   * Property idTokenSigningAlgValuesSupported
   *
   * JSON array containing a list of the JWS signing algorithms supported.
   *
   * @access public
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[SerializedName('id_token_signing_alg_values_supported')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public array $idTokenSigningAlgValuesSupported = [];
  //#endregion
}
