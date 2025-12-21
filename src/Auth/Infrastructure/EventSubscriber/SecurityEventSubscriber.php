<?php

declare(strict_types=1);

namespace Auth\Infrastructure\EventSubscriber;

use Auth\Domain\Event\LoginFailedEvent;
use Auth\Domain\Event\UserLoggedInEvent;
use OAuth\Domain\Event\TokenRefreshedEvent;
use OAuth\Domain\Event\TokenRefreshFailedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber SecurityEventSubscriber.
 *
 * @category Subscriber
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SecurityEventSubscriber implements EventSubscriberInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param LoggerInterface $logger the security logger
   */
  public function __construct(
    #[Autowire(service: 'monolog.logger.security')]
    private LoggerInterface $logger,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * @return array<class-string, string>
   */
  public static function getSubscribedEvents(): array
  {
    return [
      UserLoggedInEvent::class => 'onUserLoggedIn',
      LoginFailedEvent::class => 'onLoginFailed',
      TokenRefreshedEvent::class => 'onTokenRefreshed',
      TokenRefreshFailedEvent::class => 'onTokenRefreshFailed',
    ];
  }

  /**
   * Handles UserLoggedInEvent.
   *
   * @param UserLoggedInEvent $event the event
   */
  public function onUserLoggedIn(UserLoggedInEvent $event): void
  {
    $this->logger->info('User authenticated successfully', [
      'user_id' => $event->userId,
      'email' => $event->email,
      'ip' => $event->ipAddress,
    ]);
  }

  /**
   * Handles LoginFailedEvent.
   *
   * @param LoginFailedEvent $event the event
   */
  public function onLoginFailed(LoginFailedEvent $event): void
  {
    $this->logger->warning('Login attempt failed', [
      'email' => $event->email,
      'ip' => $event->ipAddress,
      'reason' => $event->reason,
    ]);
  }

  /**
   * Handles TokenRefreshedEvent.
   *
   * @param TokenRefreshedEvent $event the event
   */
  public function onTokenRefreshed(TokenRefreshedEvent $event): void
  {
    $this->logger->info('Token refreshed successfully', [
      'user_id' => $event->userId,
      'ip' => $event->ipAddress,
    ]);
  }

  /**
   * Handles TokenRefreshFailedEvent.
   *
   * @param TokenRefreshFailedEvent $event the event
   */
  public function onTokenRefreshFailed(TokenRefreshFailedEvent $event): void
  {
    $this->logger->warning('Token refresh failed', [
      'user_id' => $event->userId,
      'ip' => $event->ipAddress,
      'reason' => $event->reason,
    ]);
  }
  // #endregion
}
