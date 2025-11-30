<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto;

use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO AuthorizationOutput
 * @final
 *
 * DTO for OAuth2 Authorization Output.
 * Used to return authorization request details before user consent.
 *
 * @category DTO
 * @package Auth\Presentation\Api\Dto
 * @version 2.0.0
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6749#section-4.1.1
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AuthorizationOutput
{
  //#region Properties
  /**
   * Property responseType
   *
   * The response type (must be "code" for OAuth 2.1).
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Assert\NotBlank]
  #[Assert\Choice(choices: ['code'])]
  #[SerializedName('response_type')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $responseType = null;

  /**
   * Property clientId
   *
   * The client ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Assert\NotBlank]
  #[SerializedName('client_id')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $clientId = null;

  /**
   * Property clientName
   *
   * The client name (for display in consent screen).
   *
   * @access public
   * @since 2.0.0
   *
   * @var string|null
   */
  #[SerializedName('client_name')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $clientName = null;

  /**
   * Property redirectUri
   *
   * The redirect URI.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[SerializedName('redirect_uri')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $redirectUri = null;

  /**
   * Property scope
   *
   * The requested scope(s).
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $scope = null;

  /**
   * Property state
   *
   * The state parameter (CSRF protection).
   * REQUIRED in OAuth 2.1.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $state = null;

  /**
   * Property nonce
   *
   * The nonce parameter (replay protection for OpenID Connect).
   *
   * @access public
   * @since 2.0.0
   *
   * @var string|null
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $nonce = null;

  /**
   * Property codeChallenge
   *
   * The PKCE code challenge.
   *
   * @access public
   * @since 2.0.0
   *
   * @var string|null
   */
  #[SerializedName('code_challenge')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $codeChallenge = null;

  /**
   * Property codeChallengeMethod
   *
   * The PKCE code challenge method (S256 or plain).
   *
   * @access public
   * @since 2.0.0
   *
   * @var string|null
   */
  #[SerializedName('code_challenge_method')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $codeChallengeMethod = null;

  /**
   * Property requiresLogin
   *
   * Whether the user needs to authenticate.
   *
   * @access public
   * @since 2.0.0
   *
   * @var bool
   */
  #[SerializedName('requires_login')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public bool $requiresLogin = true;

  /**
   * Property requiresConsent
   *
   * Whether the user needs to consent to the requested scopes.
   *
   * @access public
   * @since 2.0.0
   *
   * @var bool
   */
  #[SerializedName('requires_consent')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public bool $requiresConsent = true;
  //#endregion
}
