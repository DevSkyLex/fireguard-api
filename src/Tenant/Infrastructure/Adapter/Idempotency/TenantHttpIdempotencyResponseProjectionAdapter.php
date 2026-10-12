<?php

declare(strict_types=1);

namespace Tenant\Infrastructure\Adapter\Idempotency;

use LogicException;
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyResponseProjectionPort;
use Tenant\Application\UseCase\Command\Tenant\ActivateTenant\ActivateTenantCommand;
use Tenant\Application\UseCase\Command\Tenant\DeactivateTenant\DeactivateTenantCommand;
use Tenant\Application\UseCase\Command\Tenant\UpdateTenant\UpdateTenantCommand;
use Tenant\Application\UseCase\Query\Tenant\GetTenant\GetTenantQuery;

use function in_array;

/**
 * Class TenantHttpIdempotencyResponseProjectionAdapter
 *
 * Captures current settings and activation output before the auth mutation and receipt commit together.
 *
 * @category Adapter
 */
final readonly class TenantHttpIdempotencyResponseProjectionAdapter implements HttpIdempotencyResponseProjectionPort
{
  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function supports(string $operation): bool
  {
    return in_array($operation, ['tenant_update', 'tenant_replace', 'tenant_activate', 'tenant_deactivate'], true);
  }

  /**
   * {@inheritdoc}
   */
  public function queries(string $operation, CommandMessage $command, ResultMessage $result): array
  {
    if ($this->supports($operation) && ($command instanceof UpdateTenantCommand || $command instanceof ActivateTenantCommand || $command instanceof DeactivateTenantCommand)) {
      return [new GetTenantQuery($command->tenantId)];
    }

    throw new LogicException('Tenant response projection command is not declared.');
  }
  // #endregion
}
