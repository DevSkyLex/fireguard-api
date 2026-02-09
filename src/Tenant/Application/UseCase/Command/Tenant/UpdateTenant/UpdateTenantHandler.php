<?php

declare(strict_types=1);

namespace Tenant\Application\UseCase\Command\Tenant\UpdateTenant;

use DateTimeImmutable;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Domain\ValueObject\Uuid;
use Tenant\Application\Port\Outbound\TenantRepositoryPort;
use Tenant\Domain\Event\TenantSettingsUpdatedEvent;
use Tenant\Domain\Exception\TenantNotFoundException;
use Tenant\Domain\ValueObject\{TenantId, TenantName};

/**
 * Handler UpdateTenantHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateTenantHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param TenantRepositoryPort $tenantRepository the tenant repository
   * @param UuidFactory $uuidFactory the UUID factory
   * @param EventDispatcherPort $eventDispatcher the event dispatcher
   */
  public function __construct(
    private readonly TenantRepositoryPort $tenantRepository,
    private readonly UuidFactory $uuidFactory,
    private readonly EventDispatcherPort $eventDispatcher,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Updates tenant data.
   *
   * @since 1.0.0
   *
   * @param UpdateTenantCommand $command the command to handle
   *
   * @throws TenantNotFoundException if tenant is not found
   *
   * @return UpdateTenantResult the result
   */
  public function __invoke(UpdateTenantCommand $command): UpdateTenantResult
  {
    $tenantId = TenantId::fromString(value: $command->tenantId);
    $tenant = $this->tenantRepository->findById(id: $tenantId);

    if (null === $tenant) {
      throw TenantNotFoundException::withId(id: $command->tenantId);
    }

    if (null !== $command->name) {
      $tenant->rename(new TenantName(value: $command->name));
    }

    $settingsUpdated = false;
    if (null !== $command->settings) {
      $tenant->updateSettings($command->settings);
      $settingsUpdated = true;
    }

    $this->tenantRepository->save(tenant: $tenant);

    if ($settingsUpdated) {
      $this->eventDispatcher->dispatch(new TenantSettingsUpdatedEvent(
        eventId: $this->uuidFactory->create(Uuid::class),
        tenantId: (string) $tenant->id(),
        settings: $tenant->settings()->toArray(),
        occurredAt: new DateTimeImmutable(),
      ));
    }

    return new UpdateTenantResult(tenantId: (string) $tenant->id());
  }
  // #endregion
}
