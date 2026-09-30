<?php

declare(strict_types=1);

namespace TrustedDevice\Presentation\Api\Processor\TrustedDevice;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use TrustedDevice\Application\UseCase\Command\TrustedDevice\RevokeDevice\RevokeDeviceCommand;

use function is_string;

/**
 * Processor RevokeDeviceProcessor.
 *
 * @implements ProcessorInterface<mixed, null>
 */
final readonly class RevokeDeviceProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Provides authenticated device-revocation requests to the application command bus.
   *
   * @access public
   *
   * @param CommandBusPort $commandBus dispatches device revocation commands
   * @param Security $security resolves the authenticated user
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
   * Revokes the requested trusted device for the authenticated user.
   *
   * @access public
   *
   * @param mixed $data the processor input
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables the route variables containing the device identifier
   * @param array<string, mixed> $context the processor context
   *
   * @return null no response body
   *
   * @throws BadRequestHttpException when the current user or device identifier is invalid
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
  {
    $user = $this->security->getUser();
    if (null === $user) {
      throw new BadRequestHttpException('User must be authenticated.');
    }

    if (!$user instanceof SecurityUser) {
      throw new BadRequestHttpException('Authenticated user type is not supported.');
    }

    $deviceId = $uriVariables['id'] ?? null;
    if (!is_string($deviceId)) {
      throw new BadRequestHttpException('Device ID is required.');
    }

    $command = new RevokeDeviceCommand(
      deviceId: $deviceId,
      userId: $user->getId(),
    );

    $this->commandBus->dispatch($command);

    return null;
  }
  // #endregion
}
