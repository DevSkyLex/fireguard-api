<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Infrastructure\EventSubscriber;

use Audit\Application\UseCase\Command\RecordAuditEvent\{RecordAuditEventCommand, RecordAuditEventResult};
use Audit\Infrastructure\EventSubscriber\AuditEventSubscriber;
use Audit\Infrastructure\Service\AuditPiiSanitizer;
use Auth\Domain\Event\Session\{LoginFailedEvent, UserLoggedOutEvent};
use Auth\Domain\Event\Token\TokenIssuedEvent as AuthTokenIssuedEvent;
use OAuth\Domain\Event\Consent\ConsentGrantedEvent;
use OAuth\Domain\Event\Token\{
  TokenIssueFailedEvent,
  TokenIssuedEvent,
  TokenRefreshFailedEvent,
  TokenRefreshedEvent,
  TokenRevokedEvent
};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Infrastructure\EventDispatcher\SymfonyEventDispatcherAdapter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Test AuthAuditWiringTest.
 *
 * Wiring proof for the authentication / OAuth2 slice: every session
 * and token domain event, dispatched through the real event-name
 * derivation of SymfonyEventDispatcherAdapter, reaches
 * AuditEventSubscriber and produces the expected audit record.
 *
 * @category Event Subscriber Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: AuditEventSubscriber::class)]
final class AuthAuditWiringTest extends TestCase
{
  // #region Constants
  private const string USER_ID = 'user-1';

  private const string CLIENT_ID = 'client-1';

  private const string TOKEN_ID = 'token-1';

  private const string IP = '203.0.113.7';
  // #endregion

  // #region Tests
  #[Test]
  public function testFailedLoginIsRecordedAnonymouslyWithItsReason(): void
  {
    $recorded = $this->dispatchAll([
      new LoginFailedEvent(email: 'user@example.com', ipAddress: self::IP, reason: 'bad_credentials'),
    ]);

    self::assertCount(1, $recorded);
    self::assertSame('auth.login_failed', $recorded[0]->action);
    self::assertSame('anonymous', $recorded[0]->actorType);
    self::assertNull($recorded[0]->actorId);
    self::assertSame('user@example.com', $recorded[0]->actorEmail);
    self::assertSame(self::IP, $recorded[0]->ipAddress);
    self::assertSame('bad_credentials', $recorded[0]->metadata['reason']);
  }

  #[Test]
  public function testLogoutIsRecordedAsAUserActionWithTheRevocationFlags(): void
  {
    $recorded = $this->dispatchAll([
      new UserLoggedOutEvent(
        userId: self::USER_ID,
        ipAddress: self::IP,
        refreshTokenRevoked: true,
        accessTokenRevoked: false,
      ),
    ]);

    self::assertSame('auth.logout', $recorded[0]->action);
    self::assertSame('user', $recorded[0]->actorType);
    self::assertSame(self::USER_ID, $recorded[0]->actorId);
    self::assertTrue($recorded[0]->metadata['refresh_token_revoked']);
    self::assertFalse($recorded[0]->metadata['access_token_revoked']);
  }

  #[Test]
  public function testAnonymousLogoutFallsBackToTheAnonymousActor(): void
  {
    $recorded = $this->dispatchAll([new UserLoggedOutEvent()]);

    self::assertSame('anonymous', $recorded[0]->actorType);
    self::assertNull($recorded[0]->actorId);
  }

  #[Test]
  public function testAuthTokenIssuedAttributesTheTokenToItsUser(): void
  {
    $recorded = $this->dispatchAll([
      new AuthTokenIssuedEvent(
        tokenId: self::TOKEN_ID,
        grantType: 'password',
        clientId: self::CLIENT_ID,
        userId: self::USER_ID,
        scopes: ['openid'],
        expiresIn: 3600,
        ipAddress: self::IP,
      ),
    ]);

    self::assertSame('auth.token_issued', $recorded[0]->action);
    self::assertSame('user', $recorded[0]->actorType);
    self::assertSame(self::USER_ID, $recorded[0]->actorId);
    self::assertSame('token', $recorded[0]->subjectType);
    self::assertSame(self::TOKEN_ID, $recorded[0]->subjectId);
    self::assertSame(self::CLIENT_ID, $recorded[0]->clientId);
    self::assertSame('password', $recorded[0]->metadata['grant_type']);
    self::assertSame(['openid'], $recorded[0]->metadata['scopes']);
    self::assertSame(3600, $recorded[0]->metadata['expires_in']);
  }

  #[Test]
  public function testAuthTokenIssuedWithoutAUserIsAttributedToTheClient(): void
  {
    $recorded = $this->dispatchAll([
      new AuthTokenIssuedEvent(
        tokenId: self::TOKEN_ID,
        grantType: 'client_credentials',
        clientId: self::CLIENT_ID,
      ),
    ]);

    self::assertSame('client', $recorded[0]->actorType);
    self::assertSame(self::CLIENT_ID, $recorded[0]->actorId);
  }

  #[Test]
  public function testOAuthTokenLifecycleEventsAreRecorded(): void
  {
    $recorded = $this->dispatchAll([
      new TokenIssuedEvent(
        tokenId: self::TOKEN_ID,
        grantType: 'authorization_code',
        clientId: self::CLIENT_ID,
        userId: self::USER_ID,
        scopes: ['openid', 'profile'],
        expiresIn: 900,
        ipAddress: self::IP,
      ),
      new TokenIssueFailedEvent(
        grantType: 'authorization_code',
        clientId: self::CLIENT_ID,
        ipAddress: self::IP,
        reason: 'invalid_grant',
      ),
      new TokenRefreshedEvent(userId: self::USER_ID, ipAddress: self::IP),
      new TokenRefreshFailedEvent(userId: self::USER_ID, ipAddress: self::IP, reason: 'expired_token'),
    ]);

    self::assertCount(4, $recorded);

    self::assertSame('oauth.token_issued', $recorded[0]->action);
    self::assertSame('client', $recorded[0]->actorType);
    self::assertSame(self::CLIENT_ID, $recorded[0]->actorId);
    self::assertSame(self::USER_ID, $recorded[0]->metadata['user_id']);

    self::assertSame('oauth.token_issue_failed', $recorded[1]->action);
    self::assertSame('invalid_grant', $recorded[1]->metadata['reason']);

    self::assertSame('oauth.token_refreshed', $recorded[2]->action);
    self::assertSame('user', $recorded[2]->actorType);
    self::assertSame(self::USER_ID, $recorded[2]->actorId);

    self::assertSame('oauth.token_refresh_failed', $recorded[3]->action);
    self::assertSame('expired_token', $recorded[3]->metadata['reason']);
  }

  #[Test]
  public function testTokenRevokedIsAttributedToTheClientWhenKnown(): void
  {
    $recorded = $this->dispatchAll([
      new TokenRevokedEvent(
        tokenId: self::TOKEN_ID,
        tokenType: 'refresh_token',
        reason: 'logout',
        clientId: self::CLIENT_ID,
        userId: self::USER_ID,
        ipAddress: self::IP,
      ),
    ]);

    self::assertSame('oauth.token_revoked', $recorded[0]->action);
    self::assertSame('client', $recorded[0]->actorType);
    self::assertSame(self::CLIENT_ID, $recorded[0]->actorId);
    self::assertSame('refresh_token', $recorded[0]->metadata['token_type']);
    self::assertSame(self::USER_ID, $recorded[0]->metadata['user_id']);
  }

  #[Test]
  public function testTokenRevokedWithoutAClientIsAttributedToTheSystem(): void
  {
    $recorded = $this->dispatchAll([
      new TokenRevokedEvent(tokenId: self::TOKEN_ID, tokenType: 'access_token'),
    ]);

    self::assertSame('system', $recorded[0]->actorType);
    self::assertNull($recorded[0]->actorId);
  }

  #[Test]
  public function testConsentGrantedDistinguishesFirstGrantFromAnUpdate(): void
  {
    $recorded = $this->dispatchAll([
      new ConsentGrantedEvent(userId: self::USER_ID, clientId: self::CLIENT_ID, scopes: ['openid'], isNew: true),
      new ConsentGrantedEvent(userId: self::USER_ID, clientId: self::CLIENT_ID, scopes: ['openid', 'email'], isNew: false),
    ]);

    self::assertSame('oauth.consent_granted', $recorded[0]->action);
    self::assertTrue($recorded[0]->metadata['is_new']);
    self::assertSame('client', $recorded[0]->subjectType);
    self::assertSame(self::CLIENT_ID, $recorded[0]->subjectId);

    self::assertSame('oauth.consent_updated', $recorded[1]->action);
    self::assertFalse($recorded[1]->metadata['is_new']);
  }

  /**
   * @param list<object> $events
   *
   * @return list<RecordAuditEventCommand>
   */
  private function dispatchAll(array $events): array
  {
    /** @var list<RecordAuditEventCommand> $recorded */
    $recorded = [];
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willReturnCallback(static function (RecordAuditEventCommand $command) use (&$recorded): RecordAuditEventResult {
        $recorded[] = $command;

        return new RecordAuditEventResult(eventId: 'event-1');
      });

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $subscriber = new AuditEventSubscriber(
      commandBus: $commandBus,
      sanitizer: new AuditPiiSanitizer(includePii: true, piiSalt: null),
      requestStack: new RequestStack(),
      security: $security,
      logger: new NullLogger(),
    );

    $symfonyDispatcher = new EventDispatcher();
    $symfonyDispatcher->addSubscriber($subscriber);
    $adapter = new SymfonyEventDispatcherAdapter(
      eventDispatcher: $symfonyDispatcher,
      logger: new NullLogger(),
    );

    foreach ($events as $event) {
      $adapter->dispatch($event);
    }

    return $recorded;
  }
  // #endregion
}
