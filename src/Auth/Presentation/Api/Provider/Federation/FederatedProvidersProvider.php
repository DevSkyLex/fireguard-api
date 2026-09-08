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
  public function __construct(private FederatedAuthenticationService $federation)
  {
  }

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
}
