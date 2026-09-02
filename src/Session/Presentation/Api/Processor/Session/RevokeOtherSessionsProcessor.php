<?php

declare(strict_types=1);

namespace Session\Presentation\Api\Processor\Session;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Session\Application\UseCase\Command\Session\RevokeOtherUserSessions\{RevokeOtherUserSessionsCommand, RevokeOtherUserSessionsResult};
use Session\Presentation\Api\Dto\Output\Session\RevokeOtherSessionsOutput;
use Session\Presentation\Api\Support\ResolvesCurrentSessionId;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Processor RevokeOtherSessionsProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, RevokeOtherSessionsOutput>
 */
final readonly class RevokeOtherSessionsProcessor implements ProcessorInterface
{
  use ResolvesCurrentSessionId;

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
   * Method process.
   *
   * Revokes every active session of the authenticated user except the one
   * backing the current request, so the caller stays signed in on this
   * device while every other device is signed out.
   * {@inheritDoc}
   *
   * @return RevokeOtherSessionsOutput the revoke-others output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RevokeOtherSessionsOutput
  {
    $user = $this->security->getUser();
    if (null === $user) {
      throw new UnauthorizedHttpException('Bearer', 'Authentication required.');
    }

    if (!$user instanceof SecurityUser) {
      throw new UnauthorizedHttpException('Bearer', 'Authenticated user type is not supported.');
    }

    $command = new RevokeOtherUserSessionsCommand(
      userId: $user->getId(),
      currentSessionId: $this->resolveCurrentSessionId(context: $context),
      reason: 'User requested logout from other devices',
    );

    /** @var RevokeOtherUserSessionsResult $result */
    $result = $this->commandBus->dispatch(command: $command);

    $output = new RevokeOtherSessionsOutput();
    $output->revokedCount = $result->revokedCount;

    return $output;
  }
  // #endregion
}
