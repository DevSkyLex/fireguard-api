<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\Session\CreateSession;

use Session\Application\Port\Outbound\SessionRepositoryPort;
use Session\Domain\Model\Session\Session;
use Session\Domain\ValueObject\{SessionId, SessionMetadata};
use Shared\Application\Factory\UuidFactory;
use Shared\Domain\ValueObject\{IpAddress, UserAgent};

use function array_merge;
use function trim;

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
    $ipAddress = trim($command->ipAddress);
    if ('' === $ipAddress) {
      $ipAddress = '127.0.0.1';
    }

    $userAgent = trim($command->userAgent);
    if ('' === $userAgent) {
      $userAgent = 'unknown';
    }

    $agent = new UserAgent($userAgent);
    $metadata = [
      'device_type' => $agent->isMobile() ? 'mobile' : 'desktop',
      'browser' => $agent->getBrowser(),
      'operating_system' => $agent->getOS(),
      'remember_me' => $command->rememberMe,
    ];

    if (null !== $command->metadata) {
      $metadata = array_merge($metadata, $command->metadata);
    }

    $session = Session::create(
      id: $sessionId,
      userId: $command->userId,
      ipAddress: new IpAddress(value: $ipAddress),
      userAgent: $agent,
      metadata: SessionMetadata::fromArray(data: $metadata),
      accessTokenId: $command->accessTokenId,
      refreshTokenId: $command->refreshTokenId,
    );

    // Save session
    $this->sessionRepository->save(session: $session);

    return new CreateSessionResult(sessionId: $sessionId->value);
  }
  // #endregion
}
