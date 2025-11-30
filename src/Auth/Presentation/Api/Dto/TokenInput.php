<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto;

use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use Auth\Presentation\Api\Validator\GrantTypeRequirements\GrantTypeRequirements;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO TokenInput
 * @final
 *
 * DTO for OAuth2 Token Input.
 *
 * @category DTO
 * @package Auth\Presentation\Api\Dto
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[GrantTypeRequirements]
final class TokenInput
{
  //#region Properties
  /**
   * Property grantType
   *
   * The grant type.
   *
   * @var string|null
   */
  #[Assert\NotBlank(message: 'The grant_type field is required.')]
  #[Assert\Choice(
    choices: ['client_credentials', 'refresh_token', 'authorization_code'],
    message: 'Invalid grant_type. Allowed values: client_credentials, refresh_token, authorization_code.'
  )]
  #[Groups(groups: [AuthSerializationGroup::WRITE])]
  public ?string $grantType = null;

  /**
   * Property clientId
   *
   * The client ID.
   *
   * @var string|null
   */
  #[Assert\NotBlank]
  #[Groups(groups: [AuthSerializationGroup::WRITE])]
  public ?string $clientId = null;

  /**
   * Property clientSecret
   *
   * The client secret.
   *
   * @var string|null
   */
  #[Assert\NotBlank]
  #[Groups(groups: [AuthSerializationGroup::WRITE])]
  public ?string $clientSecret = null;

  /**
   * Property scope
   *
   * The requested scope(s).
   *
   * @var string|null
   */
  #[Groups(groups: [AuthSerializationGroup::WRITE])]
  public ?string $scope = null;

  /**
   * Property refreshToken
   *
   * The refresh token (for refresh_token grant).
   *
   * @var string|null
   */
  #[Groups(groups: [AuthSerializationGroup::WRITE])]
  public ?string $refreshToken = null;

  /**
   * Property code
   *
   * The authorization code (for authorization_code grant).
   *
   * @var string|null
   */
  #[Groups(groups: [AuthSerializationGroup::WRITE])]
  public ?string $code = null;

  /**
   * Property redirectUri
   *
   * The redirect URI (for authorization_code grant).
   *
   * @var string|null
   */
  #[Groups(groups: [AuthSerializationGroup::WRITE])]
  public ?string $redirectUri = null;

  /**
   * Property codeVerifier
   *
   * The PKCE code verifier (for authorization_code grant with PKCE).
   * Required for public clients and recommended for all clients.
   *
   * @var string|null
   */
  #[Groups(groups: [AuthSerializationGroup::WRITE])]
  public ?string $codeVerifier = null;
  //#endregion
}
