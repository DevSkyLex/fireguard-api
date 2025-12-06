<?php

declare(strict_types=1);

namespace Auth\Presentation\Http\WellKnown;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Presentation\Dto\Response\JwksOutput;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provider JwksProvider
 * @final
 *
 * @category Provider
 * @package Auth\Presentation\Http\WellKnown
 * @version 1.0.0
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
   * Initializes a new instance of the
   * JwksProvider class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $publicKeyPath The path to the public key.
   */
  public function __construct(
    #[Autowire('%kernel.project_dir%/config/jwt/public.key')]
    private string $publicKeyPath
  ) {}
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
   * @return JwksOutput The JWKS output.
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): JwksOutput
  {
    $output = new JwksOutput();

    $publicKeyContent = file_get_contents($this->publicKeyPath);
    if ($publicKeyContent === false) {
      return $output;
    }

    $keyData = openssl_pkey_get_public($publicKeyContent);
    if ($keyData === false) {
      return $output;
    }

    $details = openssl_pkey_get_details($keyData);
    if ($details === false || !isset($details['rsa'])) {
      return $output;
    }

    $output->keys = [
      [
        'kty' => 'RSA',
        'use' => 'sig',
        'alg' => 'RS256',
        'kid' => hash('sha256', $publicKeyContent),
        'n' => rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
        'e' => rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '='),
      ]
    ];

    return $output;
  }
  //#endregion
}
