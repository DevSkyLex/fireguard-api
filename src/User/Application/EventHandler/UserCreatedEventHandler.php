<?php

declare(strict_types=1);

namespace User\Application\EventHandler;

use Shared\Application\Port\Outbound\LoggerPort;
use User\Domain\Event\UserCreatedEvent;

/**
 * Handler UserCreatedEventHandler.
 *
 * @category EventHandler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserCreatedEventHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the UserCreatedEventHandler class.
   *
   * @since 1.0.0
   *
   * @param LoggerPort $logger the logger
   */
  public function __construct(
    private LoggerPort $logger,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the event.
   *
   * @since 1.0.0
   *
   * @param UserCreatedEvent $event the event
   */
  public function __invoke(UserCreatedEvent $event): void
  {
    $this->logger->info(
      message: 'User created',
      context: [
        'user_id' => $event->userId,
        'username' => $event->username,
        'email' => $event->email,
      ],
    );
  }
  // #endregion
}
