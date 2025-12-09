<?php

declare(strict_types=1);

namespace Tenant\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tenant\Application\UseCase\Command\CreateTenant\CreateTenantCommand;
use Tenant\Application\UseCase\Command\CreateTenant\CreateTenantHandler;
use Tenant\Domain\ValueObject\TenantSettings;
use Tenant\Presentation\Api\Dto\TenantInput;
use Tenant\Presentation\Api\Dto\TenantOutput;

/**
 * Processor CreateTenantProcessor
 * @final
 *
 * API Platform processor for tenant creation.
 *
 * @category Processor
 * @package Tenant\Presentation\Api\Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<TenantInput, TenantOutput>
 */
final readonly class CreateTenantProcessor implements ProcessorInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param CreateTenantHandler $handler The command handler.
   * @param Security $security The security service.
   */
  public function __construct(
    private CreateTenantHandler $handler,
    private Security $security,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method process
   * {@inheritDoc}
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TenantOutput
  {
    if ($this->security->getUser() === null) {
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

    $result = ($this->handler)($command);

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
  //#endregion
}
