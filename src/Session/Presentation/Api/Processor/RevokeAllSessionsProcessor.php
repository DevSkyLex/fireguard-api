<?php

declare(strict_types=1);

namespace Session\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Session\Application\UseCase\Command\RevokeAllUserSessions\RevokeAllUserSessionsCommand;
use Session\Application\UseCase\Command\RevokeAllUserSessions\RevokeAllUserSessionsHandler;
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
   * @param RevokeAllUserSessionsHandler $handler  the command handler
   * @param Security                     $security the security service
   */
  public function __construct(
    private RevokeAllUserSessionsHandler $handler,
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

    $command = new RevokeAllUserSessionsCommand(
      userId: $user->getUserIdentifier(),
      reason: 'User requested logout from all devices'
    );

    ($this->handler)($command);
  }
  // #endregion
}
