<?php

declare(strict_types=1);

namespace Session\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Session\Application\UseCase\Command\RevokeSession\RevokeSessionCommand;
use Session\Application\UseCase\Command\RevokeSession\RevokeSessionHandler;
use Session\Domain\Exception\SessionNotFoundException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
   * @param RevokeSessionHandler $handler  the command handler
   * @param Security             $security the security service
   */
  public function __construct(
    private RevokeSessionHandler $handler,
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
        reason: 'User revoked session via API'
      );
      ($this->handler)($command);
    } catch (SessionNotFoundException $e) {
      throw new NotFoundHttpException($e->getMessage(), $e);
    }
  }
  // #endregion
}
