<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Infrastructure\Adapter\Doctrine;

use Audit\Application\Contract\AuditRecordRequest;
use Audit\Application\Contract\Event\AuditRecordRequestedEvent;
use Audit\Infrastructure\Adapter\Doctrine\AuditRecordingAdapter;
use Audit\Infrastructure\Service\{AuditActorResolver, AuditPiiSanitizer, AuditRecordPayloadFactory};
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use LogicException;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{EventDispatcherPort, UuidGeneratorPort};
use Shared\Infrastructure\Messaging\Outbox\DurableEventContext;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};

use function hash_hmac;
use function serialize;

/**
 * Class AuditRecordingAdapterTest
 *
 * Verifies producer ownership and privacy before either durable outbox receives a request.
 *
 * @category Unit Test
 */
final class AuditRecordingAdapterTest extends TestCase
{
  // #region Methods
  /**
   * Method routesRequestsToTheOwningTransaction
   *
   * Covers both existing producer transactions and the standalone auth transaction.
   *
   * @access public
   *
   * @param bool $authActive auth transaction state
   * @param bool $mainActive main transaction state
   * @param string $owner expected dispatcher
   *
   * @return void
   */
  #[Test]
  #[DataProvider('transactionOwners')]
  public function routesRequestsToTheOwningTransaction(bool $authActive, bool $mainActive, string $owner): void
  {
    $auth = $this->createMock(Connection::class);
    $main = $this->createMock(Connection::class);
    $auth->expects(self::once())->method('isTransactionActive')->willReturn($authActive);
    $main->expects(self::once())->method('isTransactionActive')->willReturn($mainActive);
    if (!$authActive && !$mainActive) {
      $auth->expects(self::once())->method('transactional')->willReturnCallback(static function (callable $operation): void { $operation(); });
    } else {
      $auth->expects(self::never())->method('transactional');
    }
    $main->expects(self::never())->method('transactional');
    $authEvents = $this->createMock(EventDispatcherPort::class);
    $mainEvents = $this->createMock(EventDispatcherPort::class);
    $selected = 'auth' === $owner ? $authEvents : $mainEvents;
    $other = 'auth' === $owner ? $mainEvents : $authEvents;
    $selected->expects(self::once())->method('dispatch')->with(self::callback(static function (object $event): bool {
      self::assertInstanceOf(AuditRecordRequestedEvent::class, $event);
      self::assertSame('new-request', $event->requestId);
      self::assertSame('facility.archived', $event->record->action);

      return true;
    }));
    $other->expects(self::never())->method('dispatch');
    $ids = $this->createMock(UuidGeneratorPort::class);
    $ids->expects(self::once())->method('generate')->willReturn('new-request');
    $context = new DurableEventContext();

    new AuditRecordingAdapter($auth, $main, $authEvents, $mainEvents, $ids, $context, $this->factory($context))->requestRecord(
      new AuditRecordRequest(action: 'facility.archived', actorType: 'system', subjectType: 'facility', subjectId: 'facility-id'),
    );
  }

  /**
   * Method transactionOwners
   *
   * Supplies unambiguous producer states.
   *
   * @access public
   *
   * @return iterable<string, array{bool, bool, string}> transaction ownership cases
   */
  public static function transactionOwners(): iterable
  {
    yield 'auth producer' => [true, false, 'auth'];
    yield 'main producer' => [false, true, 'main'];
    yield 'standalone request' => [false, false, 'auth'];
  }

  /**
   * Method rejectsAmbiguousOwnershipBeforeGeneratingOrDispatchingARequest
   *
   * Fails closed when both databases hold an active producer transaction.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function rejectsAmbiguousOwnershipBeforeGeneratingOrDispatchingARequest(): void
  {
    $auth = $this->createMock(Connection::class);
    $main = $this->createMock(Connection::class);
    $auth->expects(self::once())->method('isTransactionActive')->willReturn(true);
    $main->expects(self::once())->method('isTransactionActive')->willReturn(true);
    $auth->expects(self::never())->method('transactional');
    $main->expects(self::never())->method('transactional');
    $authEvents = $this->createMock(EventDispatcherPort::class);
    $mainEvents = $this->createMock(EventDispatcherPort::class);
    $authEvents->expects(self::never())->method('dispatch');
    $mainEvents->expects(self::never())->method('dispatch');
    $ids = $this->createMock(UuidGeneratorPort::class);
    $ids->expects(self::never())->method('generate');
    $context = new DurableEventContext();
    $adapter = new AuditRecordingAdapter($auth, $main, $authEvents, $mainEvents, $ids, $context, $this->factory($context));

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Audit request ownership is ambiguous while auth and main transactions are both active.');
    $adapter->requestRecord(new AuditRecordRequest('facility.archived'));
  }

  /**
   * Method sanitizesPersonalDataAndFreezesDeliveryAttributionBeforeDispatch
   *
   * Keeps raw email and IP data out of the serialized durable snapshot.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function sanitizesPersonalDataAndFreezesDeliveryAttributionBeforeDispatch(): void
  {
    $auth = $this->createStub(Connection::class);
    $main = $this->createStub(Connection::class);
    $auth->method('isTransactionActive')->willReturn(false);
    $main->method('isTransactionActive')->willReturn(true);
    $authEvents = $this->createMock(EventDispatcherPort::class);
    $authEvents->expects(self::never())->method('dispatch');
    $mainEvents = $this->createMock(EventDispatcherPort::class);
    $occurredAt = new DateTimeImmutable('2026-10-11T08:00:00+00:00');
    $mainEvents->expects(self::once())->method('dispatch')->with(self::callback(static function (object $event) use ($occurredAt): bool {
      self::assertInstanceOf(AuditRecordRequestedEvent::class, $event);
      self::assertSame('source-delivery', $event->requestId);
      self::assertSame('user', $event->record->actorType);
      self::assertSame('original-actor', $event->record->actorId);
      self::assertNull($event->record->actorEmail);
      self::assertSame(hash_hmac('sha256', 'actor@example.test', 'test-audit-salt'), $event->record->actorEmailHash);
      self::assertNull($event->record->ipAddress);
      self::assertSame(hash_hmac('sha256', '192.0.2.17', 'test-audit-salt'), $event->record->ipHash);
      self::assertSame('test-agent', $event->record->userAgent);
      self::assertSame($occurredAt, $event->record->occurredAt);
      self::assertSame([
        'recipient_email' => null,
        'recipient_email_hash' => hash_hmac('sha256', 'recipient@example.test', 'test-audit-salt'),
        'reason' => 'invitation',
        'organization_id' => 'organization-id',
        'request_id' => 'correlation-id',
      ], $event->record->metadata);
      $serialized = serialize($event);
      self::assertStringNotContainsString('actor@example.test', $serialized);
      self::assertStringNotContainsString('recipient@example.test', $serialized);
      self::assertStringNotContainsString('192.0.2.17', $serialized);

      return true;
    }));
    $ids = $this->createMock(UuidGeneratorPort::class);
    $ids->expects(self::never())->method('generate');
    $context = new DurableEventContext();
    $requests = new RequestStack();
    $http = Request::create('/', server: ['REMOTE_ADDR' => '192.0.2.17', 'HTTP_USER_AGENT' => 'test-agent']);
    $http->headers->set('X-Correlation-Id', 'correlation-id');
    $requests->push($http);
    $adapter = new AuditRecordingAdapter($auth, $main, $authEvents, $mainEvents, $ids, $context, $this->factory($context, $requests));

    $context->deliver('source-delivery', static fn () => $adapter->requestRecord(new AuditRecordRequest(
      action: 'organization.invitation_sent',
      actorEmail: ' Actor@Example.Test ',
      subjectType: 'invitation',
      subjectId: 'invitation-id',
      organizationId: 'organization-id',
      metadata: ['reason' => 'invitation'],
      occurredAt: $occurredAt,
      emailMetadata: ['recipient_email' => ' Recipient@Example.Test '],
    )), 'original-actor');
    self::assertNull($context->eventId());
    self::assertNull($context->actorUserId());
  }

  /**
   * Method factory
   *
   * Uses the real Audit privacy and actor projection services.
   *
   * @access private
   *
   * @param DurableEventContext $context originating delivery context
   * @param ?RequestStack $requests optional HTTP context
   *
   * @return AuditRecordPayloadFactory privacy projection
   */
  private function factory(DurableEventContext $context, ?RequestStack $requests = null): AuditRecordPayloadFactory
  {
    return new AuditRecordPayloadFactory(
      new AuditPiiSanitizer(false, 'test-audit-salt'),
      new AuditActorResolver($this->createStub(Security::class), $context),
      $requests ?? new RequestStack(),
    );
  }
  // #endregion
}
