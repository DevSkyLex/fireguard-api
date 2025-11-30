<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Presentation\Api\Dto\JwksOutput;

/**
 * Provider JwksProvider
 * @final
 *
 * Provider for JSON Web Key Set (JWKS) endpoint.
 * Returns the public keys used to verify JWT signatures.
 *
 * @category Provider
 * @package Auth\Presentation\Api\Provider
 * @version 1.0.0
 *
 * @see https://datatracker.ietf.org/doc/html/rfc7517
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<JwksOutput>
 */
final readonly class JwksProvider implements ProviderInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the JwksProvider class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $publicKeyPath Path to the public key file.
   */
  public function __construct(
    private string $publicKeyPath
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method provide
   * {@inheritDoc}
   *
   * Provides the JSON Web Key Set.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return JwksOutput The JWKS.
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): JwksOutput
  {
    $output = new JwksOutput();

    if (!file_exists($this->publicKeyPath)) {
      return $output;
    }

    $publicKeyContent = file_get_contents($this->publicKeyPath);
    if ($publicKeyContent === false) {
      return $output;
    }

    $publicKey = openssl_pkey_get_public($publicKeyContent);
    if ($publicKey === false) {
      return $output;
    }

    $keyDetails = openssl_pkey_get_details($publicKey);
    if ($keyDetails === false || !isset($keyDetails['rsa'])) {
      return $output;
    }

    $rsa = $keyDetails['rsa'];

    // Build the JWK
    $jwk = [
      'kty' => 'RSA',
      'use' => 'sig',
      'alg' => 'RS256',
      'kid' => $this->generateKeyId($publicKeyContent),
      'n' => $this->base64UrlEncode($rsa['n']),
      'e' => $this->base64UrlEncode($rsa['e']),
    ];

    $output->keys = [$jwk];

    return $output;
  }

  /**
   * Method base64UrlEncode
   *
   * Encodes data using Base64 URL-safe encoding.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $data The data to encode.
   *
   * @return string The encoded data.
   */
  private function base64UrlEncode(string $data): string
  {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }

  /**
   * Method generateKeyId
   *
   * Generates a key ID from the public key.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $publicKey The public key content.
   *
   * @return string The key ID.
   */
  private function generateKeyId(string $publicKey): string
  {
    return substr(hash('sha256', $publicKey), 0, 16);
  }
  //#endregion
}
