<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\EventDispatcher;

use Audit\Application\UseCase\Command\RecordAuditEvent\{RecordAuditEventCommand, RecordAuditEventResult};
use Audit\Infrastructure\EventSubscriber\OrganizationLifecycleAuditEventSubscriber;
use Audit\Infrastructure\Service\AuditPiiSanitizer;
use Organization\Application\Contract\Event\Plan\OrganizationPlanChangedEvent;
use Organization\Infrastructure\Adapter\Event\IntegrationEventConverterAdapter as OrganizationEventConverter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\IdempotentConsumerPort;
use Shared\Infrastructure\EventDispatcher\{CompositeIntegrationEventDecoder, ReflectionIntegrationEventSnapshotAdapter, SymfonyEventDispatcherAdapter};
use Shared\Infrastructure\Messaging\IntegrationEventCompatibilityMiddleware;
use Shared\Infrastructure\Messaging\Outbox\{DeliverOutboxEventHandler, DurableEventContext, OutboxEvent};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\{MiddlewareInterface, StackInterface};
use Symfony\Component\Messenger\Stamp\{DelayStamp, RedeliveryStamp};
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use User\Application\Contract\Event\UserCreatedEvent;
use User\Infrastructure\Adapter\Event\IntegrationEventConverterAdapter as UserEventConverter;

use function file_get_contents;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Class IntegrationEventCompatibilityTest
 *
 * Exercises frozen historical envelopes, immutable snapshot conversion and durable consumer retries.
 *
 * @category Unit Test
 */
#[CoversClass(CompositeIntegrationEventDecoder::class)]
#[CoversClass(ReflectionIntegrationEventSnapshotAdapter::class)]
#[CoversClass(IntegrationEventCompatibilityMiddleware::class)]
final class IntegrationEventCompatibilityTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testHistoricalOutboxReplaysWithItsActorTimestampAndReceiptIdentity(): void
  {
    $envelope = $this->fixture('organization_plan_changed');
    self::assertInstanceOf(DelayStamp::class, $envelope->last(DelayStamp::class));
    self::assertSame(2, $envelope->last(RedeliveryStamp::class)?->getRetryCount());
    $message = $envelope->getMessage();
    self::assertInstanceOf(OutboxEvent::class, $message);
    self::assertInstanceOf(\Organization\Domain\Event\Plan\OrganizationPlanChangedEvent::class, $message->event);

    $context = new DurableEventContext();
    $calls = 0;
    $commands = [];
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::exactly(2))->method('dispatch')
      ->willReturnCallback(static function (RecordAuditEventCommand $command) use (&$calls, &$commands): RecordAuditEventResult {
        ++$calls;
        if (1 === $calls) {
          throw new RuntimeException('auth append failed');
        }
        $commands[] = $command;

        return new RecordAuditEventResult('audit-record');
      });
    $receipts = new class () implements IdempotentConsumerPort {
      /**
       * @var array<string, true>
       */
      public array $completed = [];

      public function consume(string $eventId, string $consumer, callable $operation): bool
      {
        $identity = $eventId . ':' . $consumer;
        if (isset($this->completed[$identity])) {
          return false;
        }
        $operation();
        $this->completed[$identity] = true;

        return true;
      }
    };
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);
    $subscriber = new OrganizationLifecycleAuditEventSubscriber(
      $commandBus,
      new AuditPiiSanitizer(false, 'test-only-salt'),
      new RequestStack(),
      $security,
      new NullLogger(),
      $context,
      $receipts,
    );
    $dispatcher = new EventDispatcher();
    $dispatcher->addSubscriber($subscriber);
    $delivered = [];
    $dispatcher->addListener('organization.organization_plan_changed_event', static function (OrganizationPlanChangedEvent $event) use (&$delivered): void {
      $delivered[] = $event;
    }, 100);
    $handler = new DeliverOutboxEventHandler($context, new SymfonyEventDispatcherAdapter($dispatcher, new NullLogger(), $this->decoder()));

    try {
      $handler($message);
      self::fail('Durable audit errors must propagate for retry.');
    } catch (RuntimeException $failure) {
      self::assertSame('auth append failed', $failure->getMessage());
      self::assertSame([], $receipts->completed);
    }
    self::assertNull($context->eventId());
    self::assertNull($context->actorUserId());
    $handler($message);
    $handler($message);

    self::assertCount(1, $commands);
    self::assertCount(1, $receipts->completed);
    self::assertArrayHasKey('legacy-outbox-id:audit:organization.plan_changed:organization:legacy-org', $receipts->completed);
    self::assertSame('legacy-actor', $commands[0]->actorId);
    self::assertNotNull($commands[0]->occurredAt);
    self::assertSame('2024-01-02T03:04:05+00:00', $commands[0]->occurredAt->format('c'));
    self::assertSame('legacy-org', $commands[0]->organizationId);
    self::assertSame(['members'], $commands[0]->metadata['over_quota_resources']);
    self::assertCount(3, $delivered);
    self::assertSame($message->event->occurredAt, $delivered[0]->occurredAt);
    self::assertNull($context->eventId());
    self::assertNull($context->actorUserId());
  }

  #[Test]
  public function testLegacyPrivateIdentifierAndTransportStampsSurviveMiddlewareConversion(): void
  {
    $envelope = $this->fixture('user_created');
    self::assertInstanceOf(\User\Domain\Event\UserCreatedEvent::class, $envelope->getMessage());
    $downstream = $this->createMock(MiddlewareInterface::class);
    $downstream->expects(self::once())->method('handle')->willReturnCallback(static function (Envelope $converted) use ($envelope): Envelope {
      $event = $converted->getMessage();
      self::assertInstanceOf(UserCreatedEvent::class, $event);
      self::assertSame('00000000-0000-4000-a000-000000000042', $event->eventId());
      self::assertSame('legacy-user', $event->userId);
      self::assertSame('2024-01-02T03:04:05+00:00', $event->occurredAt->format('c'));
      self::assertSame($envelope->all(), $converted->all());

      return $converted;
    });
    $stack = $this->createStub(StackInterface::class);
    $stack->method('next')->willReturn($downstream);
    $result = new IntegrationEventCompatibilityMiddleware($this->decoder())->handle($envelope, $stack);
    self::assertInstanceOf(UserCreatedEvent::class, $result->getMessage());
    self::assertSame(500, $result->last(DelayStamp::class)?->getDelay());
  }

  #[Test]
  public function testCurrentContractsPassThroughWithoutReconstruction(): void
  {
    $event = new OrganizationPlanChangedEvent('org', 'plan', null, []);
    self::assertSame($event, $this->decoder()->decode($event));
    $message = new OutboxEvent('id', $event);
    self::assertSame($message, $this->decoder()->decode($message));
  }
  // #endregion

  // #region Methods
  /**
   * Method decoder
   *
   * Composes the real owner converters exercised by the historical fixtures.
   *
   * @access private
   *
   * @return CompositeIntegrationEventDecoder the compatibility decoder
   */
  private function decoder(): CompositeIntegrationEventDecoder
  {
    $snapshots = new ReflectionIntegrationEventSnapshotAdapter();

    return new CompositeIntegrationEventDecoder([new OrganizationEventConverter($snapshots), new UserEventConverter($snapshots)]);
  }

  /**
   * Method fixture
   *
   * Decodes frozen PhpSerializer bodies whose class names and property layout predate public contracts.
   *
   * @access private
   *
   * @param string $name fixture identity
   *
   * @return Envelope the historical deserialized message
   */
  private function fixture(string $name): Envelope
  {
    $encoded = json_decode((string) file_get_contents(__DIR__ . '/../../../../Fixtures/Shared/IntegrationEvent/legacy-envelopes.json'), true, 512, JSON_THROW_ON_ERROR);
    self::assertIsArray($encoded);
    self::assertIsArray($encoded[$name]);
    self::assertArrayHasKey('body', $encoded[$name]);
    self::assertIsString($encoded[$name]['body']);

    return new PhpSerializer()->decode(['body' => $encoded[$name]['body']]);
  }
  // #endregion
}
