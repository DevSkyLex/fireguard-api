<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Infrastructure\EventSubscriber;

use Audit\Application\UseCase\Command\RecordAuditEvent\{RecordAuditEventCommand, RecordAuditEventResult};
use Audit\Infrastructure\EventSubscriber\AuditEventSubscriber;
use Audit\Infrastructure\Service\AuditPiiSanitizer;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Domain\ValueObject\Uuid;
use Shared\Infrastructure\EventDispatcher\SymfonyEventDispatcherAdapter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use User\Domain\Event\{UserEmailChangeCancelledEvent, UserEmailChangeConfirmedEvent, UserEmailChangeRequestedEvent};

/**
 * Test UserAuditWiringTest.
 *
 * Wiring proof for the User email-change slice: each of the three
 * domain events, dispatched through the real event-name derivation of
 * SymfonyEventDispatcherAdapter, reaches AuditEventSubscriber and
 * produces a ledger entry whose metadata carries the sanitized
 * addresses and their hashes — never a raw address without its hash.
 *
 * @category Event Subscriber Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: AuditEventSubscriber::class)]
final class UserAuditWiringTest extends TestCase
{
  // #region Constants
  private const string USER_ID = 'user-1';

  private const string CURRENT_EMAIL = 'old-address@example.com';

  private const string NEW_EMAIL = 'new-address@example.com';
  // #endregion

  // #region Tests
  #[Test]
  public function testEmailChangeRequestedIsRecordedWithHashedAddresses(): void
  {
    $recorded = $this->dispatchAll([
      $this->event(UserEmailChangeRequestedEvent::class),
    ]);

    self::assertCount(1, $recorded);
    self::assertSame('user.email_change_requested', $recorded[0]->action);
    self::assertSame('user', $recorded[0]->actorType);
    self::assertSame(self::USER_ID, $recorded[0]->actorId);
    self::assertSame(self::USER_ID, $recorded[0]->subjectId);
    self::assertSame('user', $recorded[0]->subjectType);
    // The sanitizer runs on both addresses, value AND hash present.
    self::assertArrayHasKey('current_email', $recorded[0]->metadata);
    self::assertArrayHasKey('current_email_hash', $recorded[0]->metadata);
    self::assertArrayHasKey('new_email', $recorded[0]->metadata);
    self::assertArrayHasKey('new_email_hash', $recorded[0]->metadata);
    self::assertNotSame(self::NEW_EMAIL, $recorded[0]->metadata['new_email_hash']);
  }

  #[Test]
  public function testEmailChangeConfirmedIsRecorded(): void
  {
    $recorded = $this->dispatchAll([
      $this->event(UserEmailChangeConfirmedEvent::class),
    ]);

    self::assertCount(1, $recorded);
    self::assertSame('user.email_change_confirmed', $recorded[0]->action);
    self::assertSame(self::USER_ID, $recorded[0]->actorId);
  }

  #[Test]
  public function testEmailChangeCancelledIsRecorded(): void
  {
    $recorded = $this->dispatchAll([
      $this->event(UserEmailChangeCancelledEvent::class),
    ]);

    self::assertCount(1, $recorded);
    self::assertSame('user.email_change_cancelled', $recorded[0]->action);
    self::assertSame(self::USER_ID, $recorded[0]->actorId);
  }
  // #endregion

  // #region Helpers
  /**
   * Builds one of the three email-change events.
   *
   * @template T of object
   *
   * @param class-string<T> $class the event class
   *
   * @return T the event
   */
  private function event(string $class): object
  {
    return new $class(
      eventId: new Uuid('00000000-0000-4000-a000-000000000042'),
      userId: self::USER_ID,
      currentEmail: self::CURRENT_EMAIL,
      newEmail: self::NEW_EMAIL,
      occurredAt: new DateTimeImmutable('2026-08-28 10:00:00'),
    );
  }

  /**
   * Dispatches events through the real name-derivation path.
   *
   * @param list<object> $events the domain events
   *
   * @return list<RecordAuditEventCommand> the recorded audit commands
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
      sanitizer: new AuditPiiSanitizer(includePii: true, piiSalt: 'salt-for-tests'),
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
