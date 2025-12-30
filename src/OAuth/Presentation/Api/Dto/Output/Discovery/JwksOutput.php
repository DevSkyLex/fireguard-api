<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Dto\Output\Discovery;

use ApiPlatform\Metadata\ApiProperty;
use OAuth\Presentation\Api\Serialization\OAuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO JwksOutput.
 *
 * @category Output DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class JwksOutput
{
  // #region Properties
  /**
   * Property keys.
   *
   * Array of JSON Web Keys.
   * Each key contains parameters for verifying JWT signatures.
   * Includes: kty (key type), use (usage), alg (algorithm), kid (key ID),
   * n (modulus), e (exponent) for RSA keys.
   *
   * @example [{"kty": "RSA", "use": "sig", "alg": "RS256", "kid": "key-id-123"}]
   *
   * @since 1.0.0
   *
   * @var list<array<string, string>>
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'Array of JSON Web Keys for signature verification',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: [['kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256', 'kid' => 'key-id-123']],
    openapiContext: [
      'type' => 'array',
      'items' => [
        'type' => 'object',
        'properties' => [
          'kty' => ['type' => 'string', 'description' => 'Key type (e.g., RSA)'],
          'use' => ['type' => 'string', 'description' => 'Key usage (sig for signature)'],
          'alg' => ['type' => 'string', 'description' => 'Algorithm (e.g., RS256)'],
          'kid' => ['type' => 'string', 'description' => 'Key identifier'],
          'n' => ['type' => 'string', 'description' => 'RSA modulus (Base64URL)'],
          'e' => ['type' => 'string', 'description' => 'RSA exponent (Base64URL)'],
        ],
      ],
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'array',
      'items' => [
        'type' => 'object',
        'properties' => [
          'kty' => ['type' => 'string'],
          'use' => ['type' => 'string'],
          'alg' => ['type' => 'string'],
          'kid' => ['type' => 'string'],
          'n' => ['type' => 'string'],
          'e' => ['type' => 'string'],
        ],
      ],
    ],
  )]
  public array $keys = [];
  // #endregion
}
