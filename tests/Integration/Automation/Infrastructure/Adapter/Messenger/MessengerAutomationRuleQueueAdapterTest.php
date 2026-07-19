<?php

declare(strict_types=1);

namespace Tests\Integration\Automation\Infrastructure\Adapter\Messenger;

use Automation\Application\Port\Outbound\AutomationRuleQueuePort;
use Automation\Application\UseCase\Command\Rule\ExecuteAutomationRule\ExecuteAutomationRuleCommand;
use Inspection\Domain\Event\NonConformity\NonConformityRecordedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

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
    $transport = $container->get('messenger.transport.async');
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
   * Drives the REAL subscriber through the REAL event dispatcher, so the
   * wiring (subscriber -> queue port -> transport) is covered end to end and
   * not just at the adapter seam.
   */
  #[Test]
  public function aCriticalNonConformityEventReachesTheAsyncTransportEndToEnd(): void
  {
    self::bootKernel();
    $container = self::getContainer();

    /** @var InMemoryTransport $transport */
    $transport = $container->get('messenger.transport.async');
    $before = count($transport->getSent());

    /** @var EventDispatcherInterface $dispatcher */
    $dispatcher = $container->get('event_dispatcher');

    $dispatcher->dispatch(
      new NonConformityRecordedEvent(
        organizationId: '018f0b68-6758-7a12-8a1d-3f0d97f64a01',
        inspectionId: '018f0b68-6758-7a12-8a1d-3f0d97f64a02',
        nonConformityId: '018f0b68-6758-7a12-8a1d-3f0d97f64a03',
        severity: 'critical',
      ),
      'inspection.non_conformity_recorded_event',
    );

    $sent = $transport->getSent();
    self::assertCount(
      $before + 1,
      $sent,
      'a critical non-conformity must enqueue the automation rule; zero messages means the dispatch is silently failing again',
    );
    self::assertInstanceOf(ExecuteAutomationRuleCommand::class, $sent[count($sent) - 1]->getMessage());
  }
}
