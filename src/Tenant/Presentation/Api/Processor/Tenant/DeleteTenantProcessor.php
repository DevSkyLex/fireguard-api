<?php

declare(strict_types=1);

namespace Tenant\Presentation\Api\Processor\Tenant;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use InvalidArgumentException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Tenant\Application\UseCase\Command\Tenant\DeleteTenant\DeleteTenantCommand;

use function is_string;

/**
 * Processor DeleteTenantProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class DeleteTenantProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * DeleteTenantProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   */
  public function __construct(
    private readonly CommandBusPort $commandBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Processes the delete tenant request.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return void no return value
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
  {
    $tenantId = $uriVariables['id'] ?? null;

    if (!is_string($tenantId)) {
      throw new InvalidArgumentException('Tenant ID must be a string');
    }

    $command = new DeleteTenantCommand(tenantId: $tenantId);
    $this->commandBus->dispatch(command: $command);
  }
  // #endregion
}
