<?php

declare(strict_types=1);

namespace Tenant\Presentation\Api\Processor\Tenant;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use InvalidArgumentException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Tenant\Application\UseCase\Command\Tenant\ActivateTenant\ActivateTenantCommand;
use Tenant\Application\UseCase\Query\Tenant\GetTenant\{GetTenantQuery, GetTenantResult};
use Tenant\Presentation\Api\Dto\Output\Tenant\TenantOutput;

use function is_string;

/**
 * Processor ActivateTenantProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, TenantOutput>
 */
final readonly class ActivateTenantProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ActivateTenantProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param QueryBusPort $queryBus the query bus
   */
  public function __construct(
    private readonly CommandBusPort $commandBus,
    private readonly QueryBusPort $queryBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the tenant activation.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data (not used)
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return TenantOutput the processed output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TenantOutput
  {
    $tenantId = $uriVariables['id'] ?? null;

    if (!is_string($tenantId)) {
      throw new InvalidArgumentException('Tenant ID must be a string');
    }

    $command = new ActivateTenantCommand(tenantId: $tenantId);
    $this->commandBus->dispatch(command: $command);

    $query = new GetTenantQuery(tenantId: $tenantId);
    /** @var GetTenantResult $result */
    $result = $this->queryBus->ask(query: $query);

    $output = new TenantOutput();
    $output->id = $result->tenantId;
    $output->name = $result->name;
    $output->isActive = $result->isActive;
    $output->accessTokenTtl = $result->settings->accessTokenTtl;
    $output->refreshTokenTtl = $result->settings->refreshTokenTtl;
    $output->requirePkce = $result->settings->requirePkce;
    $output->allowPublicClients = $result->settings->allowPublicClients;
    $output->allowedScopes = $result->settings->allowedScopes;
    $output->customIssuer = $result->settings->customIssuer;
    $output->createdAt = $result->createdAt->format('c');

    return $output;
  }
  // #endregion
}
