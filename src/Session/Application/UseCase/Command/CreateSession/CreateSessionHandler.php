<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\CreateSession;

use Session\Application\Port\Outbound\SessionRepositoryPort;
use Session\Domain\Model\Session;
use Session\Domain\ValueObject\SessionId;
use Shared\Application\Factory\UuidFactory;


/**
 * Handler CreateSessionHandler
 * @final
 *
 * Handles session creation.
 *
 * @category Handler
 * @package Session\Application\UseCase\Command\CreateSession
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateSessionHandler implements \Shared\Application\Message\CommandHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param SessionRepositoryPort $sessionRepository The session repository.
   * @param UuidFactory $uuidFactory The UUID factory.
   */
  public function __construct(
    private SessionRepositoryPort $sessionRepository,
    private UuidFactory $uuidFactory,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the CreateSessionCommand.
   *
   * @access public
   * @since 1.0.0
   *
   * @param CreateSessionCommand $command The command to handle.
   *
   * @return CreateSessionResult The result.
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
  //#endregion
}
