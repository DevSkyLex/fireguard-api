<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\EventDispatcher;

use Auth\Domain\Event\Session\{LoginFailedEvent, UserLoggedInEvent};
use Auth\Infrastructure\Adapter\Event\IntegrationEventConverterAdapter as AuthEventConverter;
use DateTimeImmutable;
use OAuth\Domain\Event\Consent\ConsentGrantedEvent;
use OAuth\Infrastructure\Adapter\Event\IntegrationEventConverterAdapter as OAuthEventConverter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shared\Domain\ValueObject\Uuid;
use Shared\Infrastructure\EventDispatcher\{CompositeIntegrationEventDecoder, ReflectionIntegrationEventSnapshotAdapter, SymfonyEventDispatcherAdapter};
use Shared\Infrastructure\Symfony\Adapter\Outbound\MessengerEventBusAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\{Envelope, MessageBusInterface};
use User\Domain\Event\UserCreatedEvent;
use User\Infrastructure\Adapter\Event\IntegrationEventConverterAdapter as UserEventConverter;

/**
 * Class LegacyIntegrationEventDispatchTest
 *
 * Exercises the real dispatch adapters against typed public subscribers.
 *
 * @category Unit Test
 */
final class LegacyIntegrationEventDispatchTest extends TestCase
{
  // #region Tests
  #[Test]
  public function legacyLoginEventsReachTypedPublicSubscribersWithTheirOriginalTimestamp(): void
  {
    $success = new UserLoggedInEvent('user-123', 'user@example.com', '127.0.0.1');
    $failure = new LoginFailedEvent('user@example.com', '127.0.0.1', 'invalid_credentials');
    $dispatcher = new EventDispatcher();
    $received = [];
    $dispatcher->addListener('auth.user_logged_in_event', static function (\Auth\Application\Contract\Event\Session\UserLoggedInEvent $event) use ($success, &$received): void {
      self::assertSame($success->userId, $event->userId);
      self::assertSame($success->email, $event->email);
      self::assertSame($success->ipAddress, $event->ipAddress);
      self::assertSame($success->occurredAt, $event->occurredAt);
      $received[] = $event;
    });
    $dispatcher->addListener('auth.login_failed_event', static function (\Auth\Application\Contract\Event\Session\LoginFailedEvent $event) use ($failure, &$received): void {
      self::assertSame($failure->email, $event->email);
      self::assertSame($failure->ipAddress, $event->ipAddress);
      self::assertSame($failure->reason, $event->reason);
      self::assertSame($failure->occurredAt, $event->occurredAt);
      $received[] = $event;
    });
    $decoder = new CompositeIntegrationEventDecoder([new AuthEventConverter(new ReflectionIntegrationEventSnapshotAdapter())]);

    new SymfonyEventDispatcherAdapter($dispatcher, new NullLogger(), $decoder)->dispatchAll([$success, $failure]);

    self::assertCount(2, $received);
  }

  #[Test]
  public function legacyConsentEventReachesItsTypedOwnerContractSubscriber(): void
  {
    $source = new ConsentGrantedEvent('user-123', 'client-123', ['openid', 'email'], true);
    $dispatcher = new EventDispatcher();
    $received = null;
    $dispatcher->addListener('oauth.consent_granted_event', static function (\OAuth\Application\Contract\Event\Consent\ConsentGrantedEvent $event) use ($source, &$received): void {
      self::assertSame($source->userId, $event->userId);
      self::assertSame($source->clientId, $event->clientId);
      self::assertSame($source->scopes, $event->scopes);
      self::assertSame($source->isNew, $event->isNew);
      self::assertSame($source->occurredAt, $event->occurredAt);
      $received = $event;
    });
    $decoder = new CompositeIntegrationEventDecoder([new OAuthEventConverter(new ReflectionIntegrationEventSnapshotAdapter())]);

    new SymfonyEventDispatcherAdapter($dispatcher, new NullLogger(), $decoder)->dispatch($source);

    self::assertInstanceOf(\OAuth\Application\Contract\Event\Consent\ConsentGrantedEvent::class, $received);
  }

  #[Test]
  public function legacyUserEventIsConvertedBeforePublishingWithoutChangingIdentityOrTimestamp(): void
  {
    $id = new Uuid('00000000-0000-4000-a000-000000000042');
    $occurredAt = new DateTimeImmutable('2024-01-02T03:04:05+00:00');
    $event = new UserCreatedEvent($id, 'user-123', 'username', 'user@example.com', $occurredAt);
    $bus = $this->createMock(MessageBusInterface::class);
    $bus->expects(self::once())->method('dispatch')->willReturnCallback(static function (object $message) use ($id, $occurredAt): Envelope {
      self::assertInstanceOf(\User\Application\Contract\Event\UserCreatedEvent::class, $message);
      self::assertSame((string) $id, $message->eventId());
      self::assertSame('user-123', $message->userId);
      self::assertSame('username', $message->username);
      self::assertSame('user@example.com', $message->email);
      self::assertSame($occurredAt, $message->occurredAt);

      return new Envelope($message);
    });
    $decoder = new CompositeIntegrationEventDecoder([new UserEventConverter(new ReflectionIntegrationEventSnapshotAdapter())]);

    new MessengerEventBusAdapter($bus, $decoder)->publish($event);
  }
  // #endregion
}
