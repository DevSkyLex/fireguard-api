<?php

declare(strict_types=1);

namespace User\Application\EventHandler;

use Shared\Application\Port\Outbound\LoggerPort;
use User\Domain\Event\UserCreatedEvent;

/**
 * Handler UserCreatedEventHandler
 * @final
 *
 * Handles the UserCreatedEvent.
 *
 * @category EventHandler
 * @package User\Application\EventHandler
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserCreatedEventHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the UserCreatedEventHandler class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param LoggerPort $logger The logger.
   */
  public function __construct(
    private LoggerPort $logger
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the event.
   *
   * @access public
   * @since 1.0.0
   *
   * @param UserCreatedEvent $event The event.
   *
   * @return void
   */
  public function __invoke(UserCreatedEvent $event): void
  {
    $this->logger->info(
      message: 'User created',
      context: [
        'user_id' => $event->userId,
        'username' => $event->username,
        'email' => $event->email,
      ]
    );
  }
  //#endregion
}
