<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Dto\Input\Client;

use ApiPlatform\Metadata\ApiProperty;
use OAuth\Domain\ValueObject\Security\GrantType;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO ClientInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ClientInput
{
  // #region Properties
  /**
   * Property name.
   *
   * The client name (client_name).
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  #[Assert\Length(min: 3, max: 100)]
  #[SerializedName('client_name')]
  #[ApiProperty(
    description: 'Human-readable name of the client application',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    example: 'My Awesome App',
    openapiContext: [
      'type' => 'string',
      'minLength' => 3,
      'maxLength' => 100,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'minLength' => 3,
      'maxLength' => 100,
    ],
  )]
  public ?string $name = null;

  /**
   * Property redirectUris.
   *
   * The allowed redirect URIs.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[Assert\NotBlank]
  #[Assert\All([
    new Assert\NotBlank(),
    new Assert\Url(protocols: ['http', 'https']),
  ])]
  #[SerializedName('redirect_uris')]
  #[ApiProperty(
    description: 'List of allowed redirect URIs for OAuth2 callbacks',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    example: ['https://app.example.com/callback'],
    openapiContext: [
      'type' => 'array',
      'items' => ['type' => 'string', 'format' => 'uri'],
    ],
    jsonSchemaContext: [
      'type' => 'array',
      'items' => ['type' => 'string', 'format' => 'uri'],
    ],
  )]
  public array $redirectUris = [];

  /**
   * Property grantTypes.
   *
   * The allowed grant types.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[Assert\NotBlank]
  #[Assert\All([
    new Assert\NotBlank(),
    new Assert\Choice(choices: GrantType::VALUES),
  ])]
  #[SerializedName('grant_types')]
  #[ApiProperty(
    description: 'List of allowed OAuth2 grant types',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    example: ['authorization_code', 'refresh_token'],
    openapiContext: [
      'type' => 'array',
      'items' => ['type' => 'string', 'enum' => ['authorization_code', 'client_credentials', 'refresh_token']],
    ],
    jsonSchemaContext: [
      'type' => 'array',
      'items' => ['type' => 'string', 'enum' => ['authorization_code', 'client_credentials', 'refresh_token']],
    ],
  )]
  public array $grantTypes = [];

  /**
   * Property scopes.
   *
   * The allowed scopes.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[Assert\NotBlank]
  #[Assert\All([
    new Assert\NotBlank(),
    new Assert\Type('string'),
  ])]
  #[SerializedName('scopes')]
  #[ApiProperty(
    description: 'List of allowed OAuth2 scopes',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    example: ['openid', 'profile', 'email'],
    openapiContext: [
      'type' => 'array',
      'items' => ['type' => 'string'],
    ],
    jsonSchemaContext: [
      'type' => 'array',
      'items' => ['type' => 'string'],
    ],
  )]
  public array $scopes = [];
  // #endregion
}
