<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\EventSubscriber;

use OAuth\Domain\Event\Token\TokenIssuedEvent;
use OAuth\Domain\Event\Token\TokenIssueFailedEvent;
use OAuth\Domain\Event\Token\TokenRefreshedEvent;
use OAuth\Domain\Event\Token\TokenRefreshFailedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber EventLogSubscriber.
 *
 * Logs OAuth-related events for audit/tracing.
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
   * Initializes the event subscriber.
   *
   * @since 1.0.0
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
   * Method getSubscribedEvents.
   *
   * Returns the events to subscribe to.
   *
   * @since 1.0.0
   *
   * @return array<string, string>
   */
  public static function getSubscribedEvents(): array
  {
    return [
      'auth.token_issued_event' => 'onTokenIssued',
      'auth.token_issue_failed_event' => 'onTokenIssueFailed',
      'auth.token_refreshed_event' => 'onTokenRefreshed',
      'auth.token_refresh_failed_event' => 'onTokenRefreshFailed',
    ];
  }

  /**
   * Method onTokenIssued.
   *
   * Handles TokenIssuedEvent.
   *
   * @param TokenIssuedEvent $event the event
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  public function onTokenIssued(TokenIssuedEvent $event): void
  {
    $this->logger->info(
      message: 'OAuth2 token issued',
      context: [
        'grant_type' => $event->grantType,
        'client_id' => $event->clientId,
        'user_id' => $event->userId,
        'ip' => $event->ipAddress,
      ],
    );
  }

  /**
   * Method onTokenIssueFailed.
   *
   * Handles TokenIssueFailedEvent.
   *
   * @param TokenIssueFailedEvent $event the event
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  public function onTokenIssueFailed(TokenIssueFailedEvent $event): void
  {
    $this->logger->warning(
      message: 'OAuth2 token issuance failed',
      context: [
        'grant_type' => $event->grantType,
        'client_id' => $event->clientId,
        'ip' => $event->ipAddress,
        'reason' => $event->reason,
      ],
    );
  }

  /**
   * Method onTokenRefreshed.
   *
   * Handles TokenRefreshedEvent.
   *
   * @param TokenRefreshedEvent $event the event
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  public function onTokenRefreshed(TokenRefreshedEvent $event): void
  {
    $this->logger->info(
      message: 'Token refreshed successfully',
      context: [
        'user_id' => $event->userId,
        'ip' => $event->ipAddress,
      ],
    );
  }

  /**
   * Method onTokenRefreshFailed.
   *
   * Handles TokenRefreshFailedEvent.
   *
   * @param TokenRefreshFailedEvent $event the event
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  public function onTokenRefreshFailed(TokenRefreshFailedEvent $event): void
  {
    $this->logger->warning(
      message: 'Token refresh failed',
      context: [
        'user_id' => $event->userId,
        'ip' => $event->ipAddress,
        'reason' => $event->reason,
      ],
    );
  }
  // #endregion
}
