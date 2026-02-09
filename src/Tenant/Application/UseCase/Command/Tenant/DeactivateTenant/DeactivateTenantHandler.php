<?php

declare(strict_types=1);

namespace Tenant\Application\UseCase\Command\Tenant\DeactivateTenant;

use DateTimeImmutable;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Domain\ValueObject\Uuid;
use Tenant\Application\Port\Outbound\TenantRepositoryPort;
use Tenant\Domain\Event\TenantDeactivatedEvent;
use Tenant\Domain\Exception\TenantNotFoundException;
use Tenant\Domain\ValueObject\TenantId;

/**
 * Handler DeactivateTenantHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeactivateTenantHandler implements CommandHandler
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
   * Deactivates the tenant if needed.
   *
   * @since 1.0.0
   *
   * @param DeactivateTenantCommand $command the command
   *
   * @throws TenantNotFoundException if tenant is not found
   */
  public function __invoke(DeactivateTenantCommand $command): void
  {
    $tenantId = TenantId::fromString(value: $command->tenantId);
    $tenant = $this->tenantRepository->findById(id: $tenantId);

    if (null === $tenant) {
      throw TenantNotFoundException::withId(id: $command->tenantId);
    }

    $wasActive = $tenant->isActive();
    $tenant->deactivate();
    $this->tenantRepository->save(tenant: $tenant);

    if ($wasActive) {
      $this->eventDispatcher->dispatch(new TenantDeactivatedEvent(
        eventId: $this->uuidFactory->create(Uuid::class),
        tenantId: (string) $tenant->id(),
        tenantName: (string) $tenant->name(),
        occurredAt: new DateTimeImmutable(),
      ));
    }
  }
  // #endregion
}
