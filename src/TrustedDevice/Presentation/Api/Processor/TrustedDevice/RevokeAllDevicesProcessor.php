<?php

declare(strict_types=1);

namespace TrustedDevice\Presentation\Api\Processor\TrustedDevice;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use TrustedDevice\Application\UseCase\Command\TrustedDevice\RevokeAllDevices\RevokeAllDevicesCommand;

/**
 * Processor RevokeAllDevicesProcessor.
 *
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class RevokeAllDevicesProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Initializes the RevokeAllDevicesProcessor dependencies and state.
   *
   * @access public
   *
   * @param CommandBusPort $commandBus the command bus
   * @param Security $security the security
   *
   * @return void
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
   *
   * Processes the API operation by translating the request into the corresponding application action.
   *
   * @access public
   *
   * @param mixed $data the data
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the optional uri variables
   * @param array<string, mixed> $context the optional context
   *
   * @return void
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
  {
    $user = $this->security->getUser();
    if (null === $user) {
      throw new BadRequestHttpException('User must be authenticated.');
    }

    if (!$user instanceof SecurityUser) {
      throw new BadRequestHttpException('Authenticated user type is not supported.');
    }

    $command = new RevokeAllDevicesCommand(
      userId: $user->getId(),
    );

    $this->commandBus->dispatch($command);
  }
  // #endregion
}
