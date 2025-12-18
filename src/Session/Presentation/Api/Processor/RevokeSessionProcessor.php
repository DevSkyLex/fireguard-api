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

/**
 * Processor RevokeSessionProcessor
 * @final
 *
 * API Platform processor for revoking a session.
 *
 * @category Processor
 * @package Session\Presentation\Api\Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class RevokeSessionProcessor implements ProcessorInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param RevokeSessionHandler $handler The command handler.
   * @param Security $security The security service.
   */
  public function __construct(
    private RevokeSessionHandler $handler,
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
    if ($this->security->getUser() === null) {
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
  //#endregion
}
