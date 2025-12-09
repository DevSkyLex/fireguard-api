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
 * Processor RevokeAllSessionsProcessor
 * @final
 *
 * API Platform processor for revoking all user sessions.
 *
 * @category Processor
 * @package Session\Presentation\Api\Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class RevokeAllSessionsProcessor implements ProcessorInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param RevokeAllUserSessionsHandler $handler The command handler.
   * @param Security $security The security service.
   */
  public function __construct(
    private RevokeAllUserSessionsHandler $handler,
    private Security $security,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method process
   * {@inheritDoc}
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
  {
    $user = $this->security->getUser();
    if ($user === null) {
      throw new UnauthorizedHttpException('Bearer', 'Authentication required.');
    }

    $command = new RevokeAllUserSessionsCommand(
      userId: $user->getUserIdentifier(),
      reason: 'User requested logout from all devices'
    );

    ($this->handler)($command);
  }
  //#endregion
}
