<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Provider\Config;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Otp\Application\UseCase\Query\Config\ListChannels\{ListChannelsQuery, ListChannelsResult};
use Otp\Presentation\Api\Dto\Output\Config\ChannelOutput;
use Shared\Application\Port\Inbound\QueryBusPort;

/**
 * Provider ListChannelsProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<ChannelOutput>
 */
final readonly class ListChannelsProvider implements ProviderInterface
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
   * @return list<ChannelOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $query = new ListChannelsQuery();
    /** @var ListChannelsResult $result */
    $result = $this->queryBus->ask($query);

    $channels = [];

    foreach ($result->items as $item) {
      $output = new ChannelOutput();
      $output->value = $item->value;
      $output->label = $item->label;
      $output->requiresDelivery = $item->requiresDelivery;

      $channels[] = $output;
    }

    return $channels;
  }
  // #endregion
}
