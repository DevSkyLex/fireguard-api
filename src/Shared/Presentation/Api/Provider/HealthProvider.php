<?php

declare(strict_types=1);

namespace Shared\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\UseCase\Query\Health\{HealthCheckQuery, HealthCheckResult};
use Shared\Presentation\Api\Dto\Output\HealthOutput;

/**
 * Provider HealthProvider.
 *
 * Provides health check data for the API.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<HealthOutput>
 */
final readonly class HealthProvider implements ProviderInterface
{
  // #region Constructor
  public function __construct(
    private QueryBusPort $queryBus,
  ) {
  }
  // #endregion

  // #region Methods
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): HealthOutput
  {
    $query = new HealthCheckQuery(includeDetails: true);

    /** @var HealthCheckResult $result */
    $result = $this->queryBus->ask($query);

    $output = new HealthOutput();
    $output->status = $result->status;
    $output->timestamp = $result->timestamp;
    $output->database = $result->database;
    $output->cache = $result->cache;
    $output->version = $result->version;

    return $output;
  }
  // #endregion
}
