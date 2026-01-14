<?php

declare(strict_types=1);

namespace Session\Presentation\Api\Processor\Session;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Session\Application\UseCase\Command\Session\RevokeSession\RevokeSessionCommand;
use Session\Domain\Exception\SessionNotFoundException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};
use Throwable;

use function is_string;

/**
 * Processor RevokeSessionProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class RevokeSessionProcessor implements ProcessorInterface
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
    if (null === $this->security->getUser()) {
      throw new AccessDeniedHttpException('Authentication required');
    }

    $sessionId = $uriVariables['id'] ?? null;

    if (!is_string($sessionId)) {
      throw new NotFoundHttpException('Session ID is required.');
    }

    try {
      $command = new RevokeSessionCommand(
        sessionId: $sessionId,
        reason: 'User revoked session via API',
      );
      $this->commandBus->dispatch(command: $command);
    } catch (Throwable $exception) {
      $notFound = $this->findSessionNotFound($exception);
      if (null !== $notFound) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      throw $exception;
    }
  }

  /**
   * Method findSessionNotFound.
   *
   * Extracts a SessionNotFoundException from a nested exception chain.
   *
   * @param Throwable $exception the exception to inspect
   *
   * @return SessionNotFoundException|null the not found exception if present
   */
  private function findSessionNotFound(Throwable $exception): ?SessionNotFoundException
  {
    $current = $exception;
    while (null !== $current) {
      if ($current instanceof SessionNotFoundException) {
        return $current;
      }

      $current = $current->getPrevious();
    }

    return null;
  }
  // #endregion
}
