<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Infrastructure\EventSubscriber;

use Audit\Application\UseCase\Command\RecordAuditEvent\{RecordAuditEventCommand, RecordAuditEventResult};
use Audit\Infrastructure\EventSubscriber\AuditEventSubscriber;
use Audit\Infrastructure\Service\AuditPiiSanitizer;
use Auth\Domain\Event\Session\{LoginFailedEvent, UserLoggedInEvent};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\HttpFoundation\{Request, RequestStack};

use function hash;

/**
 * Test AuditEventSubscriberTest.
 *
 * @category Event Subscriber Tests
 */
#[CoversClass(className: AuditEventSubscriber::class)]
final class AuditEventSubscriberTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testGetSubscribedEvents(): void
  {
    self::assertSame([
      'auth.user_logged_in_event' => 'onUserLoggedIn',
      'auth.login_failed_event' => 'onLoginFailed',
      'auth.user_logged_out_event' => 'onUserLoggedOut',
      'auth.token_issued_event' => 'onAuthTokenIssued',
      'oauth.token_issued_event' => 'onOAuthTokenIssued',
      'oauth.token_issue_failed_event' => 'onOAuthTokenIssueFailed',
      'oauth.token_refreshed_event' => 'onOAuthTokenRefreshed',
      'oauth.token_refresh_failed_event' => 'onOAuthTokenRefreshFailed',
      'oauth.token_revoked_event' => 'onOAuthTokenRevoked',
      'oauth.consent_granted_event' => 'onConsentGranted',
    ], AuditEventSubscriber::getSubscribedEvents());
  }

  #[Test]
  public function testOnUserLoggedInDispatchesAuditCommand(): void
  {
    $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.10']);
    $request->headers->set('User-Agent', 'Mozilla/5.0');
    $request->headers->set('X-Request-Id', 'req-123');

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $sanitizer = new AuditPiiSanitizer(includePii: true, piiSalt: null);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(function (RecordAuditEventCommand $command): bool {
        return 'auth.login_success' === $command->action
          && 'user' === $command->actorType
          && 'user-123' === $command->actorId
          && 'user@example.com' === $command->actorEmail
          && hash('sha256', 'user@example.com') === $command->actorEmailHash
          && '203.0.113.10' === $command->ipAddress
          && hash('sha256', '203.0.113.10') === $command->ipHash
          && 'Mozilla/5.0' === $command->userAgent
          && ['request_id' => 'req-123'] === $command->metadata;
      }))
      ->willReturn(new RecordAuditEventResult(eventId: 'event-123'));

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::never())
      ->method('error');

    $subscriber = new AuditEventSubscriber(
      commandBus: $commandBus,
      sanitizer: $sanitizer,
      requestStack: $requestStack,
      logger: $logger,
    );

    $subscriber->onUserLoggedIn(new UserLoggedInEvent(
      userId: 'user-123',
      email: 'user@example.com',
      ipAddress: null,
    ));
  }

  #[Test]
  public function testDispatchAuditEventLogsWhenDispatchFails(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(new RuntimeException('boom'));

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())
      ->method('error')
      ->with(
        'Failed to record audit event',
        self::callback(fn (array $context): bool => (
          ($context['error'] ?? null) === 'boom'
          && ($context['action'] ?? null) === 'auth.login_failed'
        )),
      );

    $subscriber = new AuditEventSubscriber(
      commandBus: $commandBus,
      sanitizer: new AuditPiiSanitizer(includePii: false, piiSalt: null),
      requestStack: new RequestStack(),
      logger: $logger,
    );

    $subscriber->onLoginFailed(new LoginFailedEvent(
      email: 'user@example.com',
      ipAddress: '127.0.0.1',
      reason: 'invalid_password',
    ));
  }
  // #endregion
}
