<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Infrastructure\EventSubscriber;

use Equipment\Application\UseCase\Command\Equipment\RecordInterventionServiceHistory\RecordInterventionServiceHistoryCommand;
use Equipment\Infrastructure\EventSubscriber\InterventionServiceHistorySubscriber;
use Intervention\Domain\Event\Publication\InterventionPublishedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\{LoggerInterface, NullLogger};
use RuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[CoversClass(InterventionServiceHistorySubscriber::class)]
final class InterventionServiceHistorySubscriberTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655483001';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655483002';

  private const string PUBLICATION_ID = '550e8400-e29b-41d4-a716-446655483003';

  #[Test]
  public function itSubscribesToTheExactInterventionPublishedEventName(): void
  {
    self::assertInstanceOf(EventSubscriberInterface::class, new InterventionServiceHistorySubscriber(
      $this->createStub(CommandBusPort::class),
      new NullLogger(),
    ));

    $subscribed = InterventionServiceHistorySubscriber::getSubscribedEvents();

    self::assertArrayHasKey('intervention.intervention_published_event', $subscribed);
    self::assertSame('onInterventionPublished', $subscribed['intervention.intervention_published_event']);
  }

  #[Test]
  public function testOnInterventionPublishedDispatchesTheRecordCommand(): void
  {
    $event = new InterventionPublishedEvent(
      organizationId: self::ORGANIZATION_ID,
      interventionId: self::INTERVENTION_ID,
      publicationId: self::PUBLICATION_ID,
    );

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(function (RecordInterventionServiceHistoryCommand $command) use ($event): bool {
        self::assertSame(self::ORGANIZATION_ID, $command->organizationId);
        self::assertSame(self::INTERVENTION_ID, $command->interventionId);
        self::assertSame(self::PUBLICATION_ID, $command->publicationId);
        self::assertSame($event->occurredAt, $command->occurredAt);

        return true;
      }));

    $subscriber = new InterventionServiceHistorySubscriber($commandBus, new NullLogger());

    $subscriber->onInterventionPublished($event);
  }

  #[Test]
  public function testOnInterventionPublishedSwallowsAndLogsACommandBusFailureRatherThanPropagating(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new RuntimeException('bus unavailable'));

    /** @var LoggerInterface&MockObject $logger */
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())->method('error');

    $subscriber = new InterventionServiceHistorySubscriber($commandBus, $logger);

    // Must not throw: an uncaught exception here would abort the whole
    // published-event fan-out, breaking the audit ledger entry emitted by
    // the same event.
    $subscriber->onInterventionPublished(new InterventionPublishedEvent(
      organizationId: self::ORGANIZATION_ID,
      interventionId: self::INTERVENTION_ID,
      publicationId: self::PUBLICATION_ID,
    ));
  }
}
