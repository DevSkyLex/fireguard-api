<?php

declare(strict_types=1);

namespace Auth\Infrastructure\EventSubscriber;

use Auth\Domain\Event\Session\{LoginFailedEvent, UserLoggedInEvent};
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber EventLogSubscriber.
 *
 * Handles security events and logs them.
 *
 * @category Subscriber
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EventLogSubscriber implements EventSubscriberInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * EventLogSubscriber class.
   *
   * @since 1.0.0
   *
   * @param LoggerInterface $logger the security logger
   */
  public function __construct(
    #[Autowire(service: 'monolog.logger.security')]
    private readonly LoggerInterface $logger,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method getSubscribedEvents.
   *
   * Returns the events that this subscriber
   * is interested in.
   *
   * @since 1.0.0
   *
   * @return array<string, string> the subscribed events
   */
  public static function getSubscribedEvents(): array
  {
    return [
      'auth.user_logged_in_event' => 'onUserLoggedIn',
      'auth.login_failed_event' => 'onLoginFailed',
    ];
  }

  /**
   * Method onUserLoggedIn.
   *
   * Handles UserLoggedInEvent.
   *
   * @since 1.0.0
   *
   * @param UserLoggedInEvent $event the event
   */
  public function onUserLoggedIn(UserLoggedInEvent $event): void
  {
    $this->logger->info(
      message: 'User authenticated successfully',
      context: [
        'user_id' => $event->userId,
        'email' => $event->email,
        'ip' => $event->ipAddress,
      ],
    );
  }

  /**
   * Method onLoginFailed.
   *
   * Handles LoginFailedEvent.
   *
   * @since 1.0.0
   *
   * @param LoginFailedEvent $event the event
   */
  public function onLoginFailed(LoginFailedEvent $event): void
  {
    $this->logger->warning(
      message: 'Login attempt failed',
      context: [
        'email' => $event->email,
        'ip' => $event->ipAddress,
        'reason' => $event->reason,
      ],
    );
  }
  // #endregion
}
