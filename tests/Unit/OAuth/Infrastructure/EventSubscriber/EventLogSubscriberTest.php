<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\EventSubscriber;

use OAuth\Domain\Event\Token\{TokenIssueFailedEvent, TokenIssuedEvent, TokenRefreshFailedEvent, TokenRefreshedEvent};
use OAuth\Infrastructure\EventSubscriber\EventLogSubscriber;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Test EventLogSubscriberTest.
 *
 * @category Event Subscriber Tests
 */
#[CoversClass(className: EventLogSubscriber::class)]
final class EventLogSubscriberTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testGetSubscribedEvents(): void
  {
    self::assertSame([
      'oauth.token_issued_event' => 'onTokenIssued',
      'oauth.token_issue_failed_event' => 'onTokenIssueFailed',
      'oauth.token_refreshed_event' => 'onTokenRefreshed',
      'oauth.token_refresh_failed_event' => 'onTokenRefreshFailed',
    ], EventLogSubscriber::getSubscribedEvents());
  }

  #[Test]
  public function testOnTokenIssuedLogsInfo(): void
  {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())
      ->method('info')
      ->with(
        'OAuth2 token issued',
        self::callback(
          fn (array $context): bool => 'authorization_code' === $context['grant_type']
          && 'client-123' === $context['client_id']
          && 'user-123' === $context['user_id']
          && '127.0.0.1' === $context['ip'],
        ),
      );

    $subscriber = new EventLogSubscriber($logger);

    $subscriber->onTokenIssued(new TokenIssuedEvent(
      tokenId: 'token-123',
      grantType: 'authorization_code',
      clientId: 'client-123',
      userId: 'user-123',
      scopes: ['openid'],
      expiresIn: 3600,
      ipAddress: '127.0.0.1',
    ));
  }

  #[Test]
  public function testOnTokenIssueFailedLogsWarning(): void
  {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())
      ->method('warning')
      ->with(
        'OAuth2 token issuance failed',
        self::callback(
          fn (array $context): bool => 'password' === $context['grant_type']
          && 'client-123' === $context['client_id']
          && '127.0.0.1' === $context['ip']
          && 'invalid_client' === $context['reason'],
        ),
      );

    $subscriber = new EventLogSubscriber($logger);

    $subscriber->onTokenIssueFailed(new TokenIssueFailedEvent(
      grantType: 'password',
      clientId: 'client-123',
      ipAddress: '127.0.0.1',
      reason: 'invalid_client',
    ));
  }

  #[Test]
  public function testOnTokenRefreshedLogsInfo(): void
  {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())
      ->method('info')
      ->with(
        'Token refreshed successfully',
        self::callback(
          fn (array $context): bool => 'user-123' === $context['user_id']
          && '127.0.0.1' === $context['ip'],
        ),
      );

    $subscriber = new EventLogSubscriber($logger);

    $subscriber->onTokenRefreshed(new TokenRefreshedEvent(
      userId: 'user-123',
      ipAddress: '127.0.0.1',
    ));
  }

  #[Test]
  public function testOnTokenRefreshFailedLogsWarning(): void
  {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())
      ->method('warning')
      ->with(
        'Token refresh failed',
        self::callback(
          fn (array $context): bool => 'user-123' === $context['user_id']
          && '127.0.0.1' === $context['ip']
          && 'revoked' === $context['reason'],
        ),
      );

    $subscriber = new EventLogSubscriber($logger);

    $subscriber->onTokenRefreshFailed(new TokenRefreshFailedEvent(
      userId: 'user-123',
      ipAddress: '127.0.0.1',
      reason: 'revoked',
    ));
  }
  // #endregion
}
