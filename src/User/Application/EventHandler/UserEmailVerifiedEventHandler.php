<?php

declare(strict_types=1);

namespace User\Application\EventHandler;

use Shared\Application\Port\Outbound\LoggerPort;
use User\Domain\Event\UserEmailVerifiedEvent;

/**
 * Handler UserEmailVerifiedEventHandler
 * @final
 *
 * Handles the UserEmailVerifiedEvent.
 *
 * @category EventHandler
 * @package User\Application\EventHandler
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserEmailVerifiedEventHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the UserEmailVerifiedEventHandler class.
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
   * @param UserEmailVerifiedEvent $event The event.
   *
   * @return void
   */
  public function __invoke(UserEmailVerifiedEvent $event): void
  {
    $this->logger->info(
      message: 'User email verified',
      context: [
        'user_id' => $event->userId,
        'email' => $event->email,
      ]
    );
  }
  //#endregion
}
