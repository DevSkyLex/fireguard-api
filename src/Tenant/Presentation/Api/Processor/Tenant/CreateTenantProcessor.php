<?php

declare(strict_types=1);

namespace Tenant\Presentation\Api\Processor\Tenant;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tenant\Application\UseCase\Command\Tenant\CreateTenant\{CreateTenantCommand, CreateTenantResult};
use Tenant\Domain\ValueObject\TenantSettings;
use Tenant\Presentation\Api\Dto\Input\Tenant\TenantInput;
use Tenant\Presentation\Api\Dto\Output\Tenant\TenantOutput;

use function date;

/**
 * Processor CreateTenantProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<TenantInput, TenantOutput>
 */
final readonly class CreateTenantProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param Security $security the security service
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process
   * {@inheritDoc}
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TenantOutput
  {
    if (null === $this->security->getUser()) {
      throw new AccessDeniedHttpException('Authentication required');
    }

    /** @var TenantInput $data */
    $settings = new TenantSettings(
      accessTokenTtl: $data->accessTokenTtl,
      refreshTokenTtl: $data->refreshTokenTtl,
      requirePkce: $data->requirePkce,
      allowPublicClients: $data->allowPublicClients,
    );

    $command = new CreateTenantCommand(
      name: $data->name,
      settings: $settings,
    );

    /** @var CreateTenantResult $result */
    $result = $this->commandBus->dispatch(command: $command);

    $output = new TenantOutput();
    $output->id = $result->tenantId;
    $output->name = $data->name;
    $output->isActive = true;
    $output->accessTokenTtl = $data->accessTokenTtl;
    $output->refreshTokenTtl = $data->refreshTokenTtl;
    $output->requirePkce = $data->requirePkce;
    $output->createdAt = date('c');

    return $output;
  }
  // #endregion
}
