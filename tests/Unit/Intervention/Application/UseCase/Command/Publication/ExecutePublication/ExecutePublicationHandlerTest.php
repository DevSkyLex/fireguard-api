<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Command\Publication\ExecutePublication;

use DateTimeImmutable;
use Intervention\Application\Contract\Publication\{InterventionPublicationContext, PublicationView};
use Intervention\Application\Contract\Resource\{InterventionResourceSummary, InterventionWorkItemSummary};
use Intervention\Application\Port\Outbound\{InterventionResourceGatewayPort, PublicationRepositoryPort};
use Intervention\Application\Service\InterventionIssueFinder;
use Intervention\Application\UseCase\Command\Publication\ExecutePublication\{ExecutePublicationCommand, ExecutePublicationHandler};
use Intervention\Domain\Event\Publication\{InterventionPublicationFailedEvent, InterventionPublishedEvent};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\EventDispatcherPort;

#[CoversClass(ExecutePublicationHandler::class)]
final class ExecutePublicationHandlerTest extends TestCase
{
  #[Test]
  public function testPublishesReadyIntervention(): void
  {
    $repository = $this->repository();
    $repository->expects(self::once())->method('find')->willReturn($this->publication());
    $repository->expects(self::once())->method('interventionContext')->willReturn($this->context(42));
    $repository->expects(self::once())->method('markProcessing')->with('publication-1');
    $repository->expects(self::once())->method('publish')->with('publication-1')->willReturn(true);
    $repository->expects(self::never())->method('markFailed');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof InterventionPublishedEvent
        && 'organization-1' === $event->organizationId
        && 'intervention-1' === $event->interventionId
        && 'publication-1' === $event->publicationId,
    ));

    $this->handler($repository, $eventDispatcher)->__invoke(new ExecutePublicationCommand('publication-1'));
  }

  #[Test]
  public function testChangedInterventionMarksPublicationFailedWithoutPublishing(): void
  {
    $repository = $this->repository();
    $repository->expects(self::once())->method('find')->willReturn($this->publication());
    $repository->expects(self::once())->method('interventionContext')->willReturn($this->context(43));
    $repository->expects(self::never())->method('markProcessing');
    $repository->expects(self::never())->method('publish');
    $repository->expects(self::once())
      ->method('markFailed')
      ->with('publication-1', 'Intervention changed before publication execution.')
      ->willReturn(true);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof InterventionPublicationFailedEvent
        && 'organization-1' === $event->organizationId
        && 'intervention-1' === $event->interventionId
        && 'publication-1' === $event->publicationId
        && 'Intervention changed before publication execution.' === $event->reason,
    ));

    $this->handler($repository, $eventDispatcher)->__invoke(new ExecutePublicationCommand('publication-1'));
  }

  #[Test]
  public function testPublishFailureMarksFailedAndAuditsTheFailure(): void
  {
    // publish() itself blowing up (after markProcessing) must mark the
    // publication failed AND leave a publication_failed ledger trace.
    $repository = $this->repository();
    $repository->expects(self::once())->method('find')->willReturn($this->publication());
    $repository->expects(self::once())->method('interventionContext')->willReturn($this->context(42));
    $repository->expects(self::once())->method('markProcessing')->with('publication-1');
    $repository->expects(self::once())->method('publish')
      ->willThrowException(new RuntimeException('boom'));
    $repository->expects(self::once())->method('markFailed')->with('publication-1', 'boom')->willReturn(true);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof InterventionPublicationFailedEvent
        && 'boom' === $event->reason,
    ));

    $this->handler($repository, $eventDispatcher)->__invoke(new ExecutePublicationCommand('publication-1'));
  }

  #[Test]
  public function testAlreadyPublishedPublicationDispatchesNothing(): void
  {
    // Idempotent replay of the message: no state change, no ledger row.
    $repository = $this->repository();
    $repository->expects(self::once())->method('find')->willReturn(
      new PublicationView('publication-1', 'intervention-1', 42, 'published', null, new DateTimeImmutable(), null),
    );
    $repository->expects(self::never())->method('publish');
    $repository->expects(self::never())->method('markFailed');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $this->handler($repository, $eventDispatcher)->__invoke(new ExecutePublicationCommand('publication-1'));
  }

  #[Test]
  public function testConcurrentlyCompletedPublicationDispatchesNothing(): void
  {
    // At-least-once redelivery racing another worker: publish() reports that
    // the record was already completed (no durable transition by THIS call),
    // so no duplicate ledger row may be written.
    $repository = $this->repository();
    $repository->expects(self::once())->method('find')->willReturn($this->publication());
    $repository->expects(self::once())->method('interventionContext')->willReturn($this->context(42));
    $repository->expects(self::once())->method('markProcessing')->with('publication-1');
    $repository->expects(self::once())->method('publish')->with('publication-1')->willReturn(false);
    $repository->expects(self::never())->method('markFailed');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $this->handler($repository, $eventDispatcher)->__invoke(new ExecutePublicationCommand('publication-1'));
  }

  #[Test]
  public function testNoOpMarkFailedDispatchesNothing(): void
  {
    // A failure racing a concurrent completion: markFailed() reports no
    // durable transition (the record is already completed/failed), so a
    // false publication_failed row must not contradict the ledger.
    $repository = $this->repository();
    $repository->expects(self::once())->method('find')->willReturn($this->publication());
    $repository->expects(self::once())->method('interventionContext')->willReturn($this->context(43));
    $repository->expects(self::once())->method('markFailed')->willReturn(false);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $this->handler($repository, $eventDispatcher)->__invoke(new ExecutePublicationCommand('publication-1'));
  }

  #[Test]
  public function testPublishedEventIsDispatchedAfterThePublishCall(): void
  {
    // Pins the post-commit ordering: the ledger row may only be written once
    // publish() (which commits internally) has returned.
    $sequence = [];

    $repository = $this->createStub(PublicationRepositoryPort::class);
    $repository->method('find')->willReturn($this->publication());
    $repository->method('interventionContext')->willReturn($this->context(42));
    $repository->method('publish')->willReturnCallback(static function () use (&$sequence): bool {
      $sequence[] = 'publish';

      return true;
    });

    $eventDispatcher = $this->createStub(EventDispatcherPort::class);
    $eventDispatcher->method('dispatch')->willReturnCallback(static function () use (&$sequence): void {
      $sequence[] = 'dispatch';
    });

    $this->handler($repository, $eventDispatcher)->__invoke(new ExecutePublicationCommand('publication-1'));

    self::assertSame(['publish', 'dispatch'], $sequence);
  }

  private function publication(): PublicationView
  {
    return new PublicationView('publication-1', 'intervention-1', 42, 'pending', null, new DateTimeImmutable(), null);
  }

  private function context(int $revision): InterventionPublicationContext
  {
    return new InterventionPublicationContext('intervention-1', 'organization-1', 'submitted', $revision);
  }

  /**
   * @return PublicationRepositoryPort&MockObject
   */
  private function repository(): PublicationRepositoryPort
  {
    return $this->createMock(PublicationRepositoryPort::class);
  }

  private function handler(PublicationRepositoryPort $repository, ?EventDispatcherPort $eventDispatcher = null): ExecutePublicationHandler
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('summary')->willReturn(new InterventionResourceSummary(1, 0, 0));
    $resources->method('equipmentDrafts')->willReturn([]);
    $resources->method('workItemSummary')->willReturn(new InterventionWorkItemSummary(0, 0, 0, 0));

    return new ExecutePublicationHandler(
      $repository,
      new InterventionIssueFinder($resources),
      $eventDispatcher ?? $this->createStub(EventDispatcherPort::class),
    );
  }
}
