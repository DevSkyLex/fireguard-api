<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Provider\Config;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Otp\Application\UseCase\Query\Config\ListPurposes\{ListPurposesQuery, ListPurposesResult};
use Otp\Presentation\Api\Dto\Output\Config\PurposeOutput;
use Shared\Application\Port\Inbound\QueryBusPort;

/**
 * Provider ListPurposesProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<PurposeOutput>
 */
final readonly class ListPurposesProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param QueryBusPort $queryBus the query bus
   */
  public function __construct(
    private QueryBusPort $queryBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide
   * {@inheritDoc}
   *
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return list<PurposeOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $query = new ListPurposesQuery();
    /** @var ListPurposesResult $result */
    $result = $this->queryBus->ask($query);

    $purposes = [];

    foreach ($result->items as $item) {
      $output = new PurposeOutput();
      $output->value = $item->value;
      $output->label = $item->label;
      $output->ttlSeconds = $item->ttlSeconds;
      $output->maxAttempts = $item->maxAttempts;

      $purposes[] = $output;
    }

    return $purposes;
  }
  // #endregion
}
