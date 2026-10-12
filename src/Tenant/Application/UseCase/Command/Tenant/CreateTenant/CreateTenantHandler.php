<?php

declare(strict_types=1);

namespace Tenant\Application\UseCase\Command\Tenant\CreateTenant;

use DateTimeImmutable;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, UuidGeneratorPort};
use Shared\Domain\ValueObject\Uuid;
use Tenant\Application\Port\Outbound\Internal\TenantRepositoryPort;
use Tenant\Domain\Event\TenantCreatedEvent;
use Tenant\Domain\Model\Tenant\Tenant;
use Tenant\Domain\ValueObject\{TenantId, TenantName, TenantSettings};

/**
 * Handler CreateTenantHandler.
 *
 * @category Handler
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
   * @param UuidGeneratorPort $uuidFactory the UUID factory
   */
  public function __construct(
    private readonly TenantRepositoryPort $tenantRepository,
    private readonly UuidGeneratorPort $uuidFactory,
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
    $tenantId = new TenantId($this->uuidFactory->generate());

    $tenant = Tenant::create(
      id: $tenantId,
      name: new TenantName(value: $command->name),
      settings: $command->settings ?? new TenantSettings(),
    );

    $this->tenantRepository->save(tenant: $tenant);

    $this->eventDispatcher->dispatch(new TenantCreatedEvent(
      eventId: new Uuid($this->uuidFactory->generate()),
      tenantId: (string) $tenantId,
      tenantName: (string) $tenant->name(),
      occurredAt: new DateTimeImmutable(),
    ));

    return new CreateTenantResult(
      tenantId: (string) $tenantId,
      createdAt: $tenant->createdAt(),
    );
  }
  // #endregion
}
