<?php

declare(strict_types=1);

namespace Tests\Integration\Automation\Infrastructure\Adapter\Messenger;

use Automation\Application\Port\Outbound\AutomationRuleQueuePort;
use Automation\Application\UseCase\Command\Rule\ExecuteAutomationRule\ExecuteAutomationRuleCommand;
use Inspection\Application\Contract\Event\NonConformity\NonConformityRecordedEvent;
use Inspection\Domain\Event\NonConformity\NonConformityRecordedEvent as LegacyNonConformityRecordedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Application\Port\Outbound\{DurableEventContextPort, EventDispatcherPort};
use Shared\Infrastructure\Messaging\Outbox\{DeliverOutboxEventHandler, OutboxEvent};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

use function count;

/**
 * Test MessengerAutomationRuleQueueAdapterTest.
 *
 * Guards the enqueue path for `auto_create_intervention_on_critical_nc`.
 *
 * Background: the trigger subscriber used to enqueue through `CommandBusPort`,
 * whose adapter demands a `HandledStamp` and throws `NoHandlerResultException`
 * for an async-routed message (handed to the transport, never handled inline).
 * Measured behaviour of that old path: the message DID reach the transport —
 * the throw happens after `dispatch()` returns, at stamp extraction — so the
 * rule still executed, but every trigger raised an exception that the
 * subscriber swallowed into a false `error` log, masking genuine dispatch
 * failures. These tests pin the corrected path: enqueue must reach the async
 * transport AND must not throw.
 *
 * @category Integration Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(\Automation\Infrastructure\Adapter\Messenger\MessengerAutomationRuleQueueAdapter::class)]
final class MessengerAutomationRuleQueueAdapterTest extends KernelTestCase
{
  #[Test]
  public function itEnqueuesTheRuleOnTheAsyncTransportWithoutThrowing(): void
  {
    self::bootKernel();
    $container = self::getContainer();

    /** @var AutomationRuleQueuePort $queue */
    $queue = $container->get(AutomationRuleQueuePort::class);

    /** @var InMemoryTransport $transport */
    $transport = $container->get('messenger.transport.main_outbox');
    $before = count($transport->getSent());

    // Must not throw: this is the exact call the trigger subscriber makes.
    $queue->enqueue(
      ruleKey: 'auto_create_intervention_on_critical_nc',
      organizationId: '018f0b68-6758-7a12-8a1d-3f0d97f64a01',
      subjectId: '018f0b68-6758-7a12-8a1d-3f0d97f64a03',
      triggerPayload: ['severity' => 'critical'],
    );

    $sent = $transport->getSent();
    self::assertCount($before + 1, $sent, 'the rule execution must reach the async transport');

    $message = $sent[count($sent) - 1]->getMessage();
    self::assertInstanceOf(ExecuteAutomationRuleCommand::class, $message);
    self::assertSame('auto_create_intervention_on_critical_nc', $message->ruleKey);
    self::assertSame('018f0b68-6758-7a12-8a1d-3f0d97f64a03', $message->subjectId);
  }

  /**
   * Drives the real subscriber through the application's event port, so the
   * wiring (subscriber -> queue port -> transport) is covered end to end and
   * not just at the adapter seam.
   */
  #[Test]
  public function aCriticalNonConformityEventReachesTheAsyncTransportEndToEnd(): void
  {
    self::bootKernel();
    $container = self::getContainer();

    /** @var InMemoryTransport $transport */
    $transport = $container->get('messenger.transport.main_outbox');
    $before = count($transport->getSent());

    $dispatcher = $container->get(EventDispatcherPort::class);
    self::assertInstanceOf(EventDispatcherPort::class, $dispatcher);

    $dispatcher->dispatch(
      new NonConformityRecordedEvent(
        organizationId: '018f0b68-6758-7a12-8a1d-3f0d97f64a01',
        inspectionId: '018f0b68-6758-7a12-8a1d-3f0d97f64a02',
        nonConformityId: '018f0b68-6758-7a12-8a1d-3f0d97f64a03',
        severity: 'critical',
      ),
    );

    $sent = $transport->getSent();
    self::assertCount(
      $before + 1,
      $sent,
      'a critical non-conformity must enqueue the automation rule; zero messages means the dispatch is silently failing again',
    );
    self::assertInstanceOf(ExecuteAutomationRuleCommand::class, $sent[count($sent) - 1]->getMessage());
  }

  #[Test]
  public function aHistoricalDomainEnvelopeConvertsBeforeSubscribersAndReplaysWithoutReenqueuing(): void
  {
    self::bootKernel();
    $container = self::getContainer();
    $transport = $container->get('messenger.transport.main_outbox');
    self::assertInstanceOf(InMemoryTransport::class, $transport);
    $before = count($transport->getSent());
    $source = new LegacyNonConformityRecordedEvent(
      organizationId: '018f0b68-6758-7a12-8a1d-3f0d97f64a01',
      inspectionId: '018f0b68-6758-7a12-8a1d-3f0d97f64a02',
      nonConformityId: '018f0b68-6758-7a12-8a1d-3f0d97f64a03',
      severity: 'critical',
    );
    $serializer = new PhpSerializer();
    $envelope = $serializer->decode($serializer->encode(new Envelope(new OutboxEvent('018f0b68-6758-7a12-8a1d-3f0d97f64a04', $source))));
    $message = $envelope->getMessage();
    self::assertInstanceOf(OutboxEvent::class, $message);
    self::assertInstanceOf(LegacyNonConformityRecordedEvent::class, $message->event);
    $context = $container->get(DurableEventContextPort::class);
    self::assertInstanceOf(DurableEventContextPort::class, $context);
    $dispatcher = $container->get('event_dispatcher');
    self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
    $observed = [];
    $dispatcher->addListener('inspection.non_conformity_recorded_event', static function (NonConformityRecordedEvent $event) use ($source, $message, $context, &$observed): void {
      self::assertSame($source->organizationId, $event->organizationId);
      self::assertSame($source->inspectionId, $event->inspectionId);
      self::assertSame($source->nonConformityId, $event->nonConformityId);
      self::assertSame($source->severity, $event->severity);
      self::assertSame($source->occurredAt->format('c'), $event->occurredAt->format('c'));
      self::assertSame($message->id, $context->eventId());
      $observed[] = $event;
    }, 100);
    $handler = $container->get(DeliverOutboxEventHandler::class);
    self::assertInstanceOf(DeliverOutboxEventHandler::class, $handler);
    $handler($message);
    $handler($message);
    self::assertCount(2, $observed);
    self::assertCount($before + 1, $transport->getSent(), 'A replay must preserve the automation receipt and enqueue only once.');
    $sent = $transport->getSent();
    $command = $sent[count($sent) - 1]->getMessage();
    self::assertInstanceOf(ExecuteAutomationRuleCommand::class, $command);
    self::assertSame($source->nonConformityId, $command->subjectId);
    self::assertSame('critical', $command->triggerPayload['severity']);
    self::assertNull($context->eventId());
    self::assertNull($context->actorUserId());
  }
}
