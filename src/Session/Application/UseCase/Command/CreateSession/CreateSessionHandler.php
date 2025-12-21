<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\CreateSession;

use Session\Application\Port\Outbound\SessionRepositoryPort;
use Session\Domain\Model\Session;
use Session\Domain\ValueObject\SessionId;
use Shared\Application\Factory\UuidFactory;

/**
 * Handler CreateSessionHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateSessionHandler implements \Shared\Application\Message\CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param SessionRepositoryPort $sessionRepository the session repository
   * @param UuidFactory $uuidFactory the UUID factory
   */
  public function __construct(
    private SessionRepositoryPort $sessionRepository,
    private UuidFactory $uuidFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the CreateSessionCommand.
   *
   * @since 1.0.0
   *
   * @param CreateSessionCommand $command the command to handle
   *
   * @return CreateSessionResult the result
   */
  public function __invoke(CreateSessionCommand $command): CreateSessionResult
  {
    // Generate session ID
    $sessionId = $this->uuidFactory->create(SessionId::class);

    // Create session
    $session = Session::create(
      id: $sessionId,
      userId: $command->userId,
      ipAddress: $command->ipAddress,
      userAgent: $command->userAgent,
    );

    // Save session
    $this->sessionRepository->save(session: $session);

    return new CreateSessionResult(
      sessionId: $sessionId->value,
    );
  }
  // #endregion
}
