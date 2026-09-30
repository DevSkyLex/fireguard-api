<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Provider\Federation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Application\Service\Federation\FederatedAuthenticationService;
use Auth\Presentation\Api\Dto\Output\Federation\FederatedProviderOutput;

use function array_map;

/**
 * @implements ProviderInterface<FederatedProviderOutput>
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedProvidersProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives the federation service used to list configured providers that are enabled for sign-in.
   *
   * @access public
   *
   * @param FederatedAuthenticationService $federation service that exposes configured federated providers
   *
   * @return void
   */
  public function __construct(private FederatedAuthenticationService $federation)
  {
  }
  // #endregion

  // #region Methods

  /**
   * @return list<FederatedProviderOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    return array_map(
      static fn ($provider): FederatedProviderOutput => new FederatedProviderOutput($provider->value),
      $this->federation->enabledProviders(),
    );
  }
  // #endregion
}
