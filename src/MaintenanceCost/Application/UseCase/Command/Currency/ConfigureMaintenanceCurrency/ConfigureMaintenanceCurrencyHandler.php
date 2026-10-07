<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\UseCase\Command\Currency\ConfigureMaintenanceCurrency;

use MaintenanceCost\Application\Contract\Currency\MaintenanceCurrencySnapshot;
use MaintenanceCost\Application\Port\Outbound\Currency\MaintenanceCurrencyStorePort;
use MaintenanceCost\Application\Service\MaintenanceCostAccessGuard;
use MaintenanceCost\Domain\Model\Currency\MaintenanceCurrency;
use Shared\Application\Message\CommandHandler;

/** Serializes configuration with the first capture of a financial fact. */
final readonly class ConfigureMaintenanceCurrencyHandler implements CommandHandler
{
  public function __construct(private MaintenanceCurrencyStorePort $store, private MaintenanceCostAccessGuard $access)
  {
  }

  public function __invoke(ConfigureMaintenanceCurrencyCommand $command): ConfigureMaintenanceCurrencyResult
  {
    $this->access->assertAccess($command->actorUserId, $command->organizationId, true);

    return $this->store->synchronized($command->organizationId, function () use ($command): ConfigureMaintenanceCurrencyResult {
      $current = $this->store->read($command->organizationId);
      $configured = new MaintenanceCurrency($current->currency, $current->locked)->configure($command->currency);
      $snapshot = new MaintenanceCurrencySnapshot($command->organizationId, $configured->currency, $configured->locked);
      $this->store->save($snapshot);

      return new ConfigureMaintenanceCurrencyResult($snapshot);
    });
  }
}
