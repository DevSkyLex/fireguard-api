<?php

declare(strict_types=1);

namespace Session\Presentation\Api\Processor\Session;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Session\Application\UseCase\Command\Session\RevokeAllUserSessions\RevokeAllUserSessionsCommand;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Processor RevokeAllSessionsProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class RevokeAllSessionsProcessor implements ProcessorInterface
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
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
  {
    $user = $this->security->getUser();
    if (null === $user) {
      throw new UnauthorizedHttpException('Bearer', 'Authentication required.');
    }

    if (!$user instanceof SecurityUser) {
      throw new UnauthorizedHttpException('Bearer', 'Authenticated user type is not supported.');
    }

    $command = new RevokeAllUserSessionsCommand(
      userId: $user->getId(),
      reason: 'User requested logout from all devices',
    );

    $this->commandBus->dispatch(command: $command);
  }
  // #endregion
}
