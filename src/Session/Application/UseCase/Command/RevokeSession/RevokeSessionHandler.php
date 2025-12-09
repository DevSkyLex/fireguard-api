<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\RevokeSession;

use Session\Application\Port\Outbound\SessionRepositoryPort;
use Session\Domain\Exception\SessionNotFoundException;
use Session\Domain\ValueObject\SessionId;

/**
 * Handler RevokeSessionHandler
 * @final
 *
 * Handles session revocation.
 *
 * @category Handler
 * @package Session\Application\UseCase\Command\RevokeSession
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeSessionHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param SessionRepositoryPort $sessionRepository The session repository.
   */
  public function __construct(
    private SessionRepositoryPort $sessionRepository,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the RevokeSessionCommand.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RevokeSessionCommand $command The command to handle.
   *
   * @return void
   *
   * @throws SessionNotFoundException If session is not found.
   */
  public function __invoke(RevokeSessionCommand $command): void
  {
    $sessionId = new SessionId(value: $command->sessionId);
    $session = $this->sessionRepository->findById(id: $sessionId);

    if ($session === null) {
      throw SessionNotFoundException::withId(id: $command->sessionId);
    }

    $session->revoke();

    $this->sessionRepository->save(session: $session);
  }
  //#endregion
}
