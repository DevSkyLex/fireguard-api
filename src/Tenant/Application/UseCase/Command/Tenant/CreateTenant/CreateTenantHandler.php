<?php

declare(strict_types=1);

namespace Tenant\Application\UseCase\Command\Tenant\CreateTenant;

use DateTimeImmutable;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Domain\ValueObject\Uuid;
use Tenant\Application\Port\Outbound\TenantRepositoryPort;
use Tenant\Domain\Event\TenantCreatedEvent;
use Tenant\Domain\Model\Tenant\Tenant;
use Tenant\Domain\ValueObject\{TenantId, TenantName, TenantSettings};

/**
 * Handler CreateTenantHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateTenantHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * CreateTenantHandler class.
   *
   * @since 1.0.0
   *
   * @param TenantRepositoryPort $tenantRepository the tenant repository
   * @param UuidFactory $uuidFactory the UUID factory
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
   * Handles the CreateTenantCommand.
   *
   * @since 1.0.0
   *
   * @param CreateTenantCommand $command the command to handle
   *
   * @return CreateTenantResult the result
   */
  public function __invoke(CreateTenantCommand $command): CreateTenantResult
  {
    $tenantId = $this->uuidFactory->create(TenantId::class);

    $tenant = Tenant::create(
      id: $tenantId,
      name: new TenantName(value: $command->name),
      settings: $command->settings ?? new TenantSettings(),
    );

    $this->tenantRepository->save(tenant: $tenant);

    $this->eventDispatcher->dispatch(new TenantCreatedEvent(
      eventId: $this->uuidFactory->create(Uuid::class),
      tenantId: (string) $tenantId,
      tenantName: (string) $tenant->name(),
      occurredAt: new DateTimeImmutable(),
    ));

    return new CreateTenantResult(
      tenantId: (string) $tenantId,
    );
  }
  // #endregion
}
