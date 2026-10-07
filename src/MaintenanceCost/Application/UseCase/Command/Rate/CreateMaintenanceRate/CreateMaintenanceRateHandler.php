<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\UseCase\Command\Rate\CreateMaintenanceRate;

use MaintenanceCost\Application\Contract\Rate\MaintenanceRateSnapshot;
use MaintenanceCost\Application\Port\Inbound\MaintenanceCurrencyPort;
use MaintenanceCost\Application\Port\Outbound\Rate\MaintenanceRateStorePort;
use MaintenanceCost\Application\Service\MaintenanceCostAccessGuard;
use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use MaintenanceCost\Domain\Model\Rate\MaintenanceHourlyRate;
use Organization\Application\Port\Inbound\OrganizationWorkforceDirectoryPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\UuidGeneratorPort;

use function strtolower;

/** Appends exact rate parameters without replacing rates that existing facts may reference. */
final readonly class CreateMaintenanceRateHandler implements CommandHandler
{
  public function __construct(
    private MaintenanceRateStorePort $store,
    private MaintenanceCurrencyPort $currency,
    private MaintenanceCostAccessGuard $access,
    private OrganizationWorkforceDirectoryPort $workforce,
    private UuidGeneratorPort $identifiers,
  ) {
  }

  public function __invoke(CreateMaintenanceRateCommand $command): CreateMaintenanceRateResult
  {
    $this->access->assertAccess($command->actorUserId, $command->organizationId, true);
    $rate = new MaintenanceHourlyRate(strtolower($command->memberId), $command->hourlyAmount, $command->effectiveFrom, strtolower($command->clientId));
    $member = $this->workforce->profiles($command->organizationId, [$rate->memberId]);
    if (!isset($member[$rate->memberId])) {
      throw MaintenanceCostException::notFound();
    }

    return $this->store->synchronized($command->organizationId, function () use ($command, $rate): CreateMaintenanceRateResult {
      $existing = $this->store->findByClientId($command->organizationId, $rate->clientId);
      if (null !== $existing) {
        if ($existing->memberId !== $rate->memberId || $existing->hourlyAmount !== $rate->hourlyAmount || $existing->effectiveFrom !== $rate->effectiveFrom) {
          throw MaintenanceCostException::conflict('Rate client identifier was already used for different parameters.');
        }

        return new CreateMaintenanceRateResult($existing, true);
      }
      if (null !== $this->store->findByEffectiveDate($command->organizationId, $rate->memberId, $rate->effectiveFrom)) {
        throw MaintenanceCostException::conflict('An hourly rate already exists for this member and effective date.');
      }
      $snapshot = new MaintenanceRateSnapshot($this->identifiers->generate(), $rate->memberId, $rate->hourlyAmount, $this->currency->forOrganization($command->organizationId), $rate->effectiveFrom);
      $this->store->append($command->organizationId, $rate->clientId, $snapshot);

      return new CreateMaintenanceRateResult($snapshot, false);
    });
  }
}
