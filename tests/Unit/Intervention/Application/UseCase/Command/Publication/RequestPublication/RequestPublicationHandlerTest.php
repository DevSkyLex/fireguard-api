<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Command\Publication\RequestPublication;

use DateTimeImmutable;
use Intervention\Application\Contract\Publication\{InterventionPublicationContext, PublicationView};
use Intervention\Application\Contract\Resource\{InterventionResourceSummary, InterventionWorkItemSummary};
use Intervention\Application\Port\Outbound\{InterventionResourceGatewayPort, PublicationQueuePort, PublicationRepositoryPort};
use Intervention\Application\Service\InterventionIssueFinder;
use Intervention\Application\UseCase\Command\Publication\RequestPublication\{RequestPublicationCommand, RequestPublicationHandler};
use Intervention\Domain\Exception\InterventionBlockedException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;

#[CoversClass(RequestPublicationHandler::class)]
final class RequestPublicationHandlerTest extends TestCase
{
  #[Test]
  public function testCreatesAndQueuesPendingPublication(): void
  {
    $publication = $this->publication();
    $repository = $this->repository();
    $repository->expects(self::once())->method('interventionContext')->willReturn($this->context());
    $repository->expects(self::once())->method('findByInterventionRevision')->willReturn(null);
    $repository->expects(self::once())->method('createOrGetPending')->with('publication-1', 'intervention-1', 42)->willReturn($publication);

    $queue = $this->queue();
    $queue->expects(self::once())->method('dispatch')->with('publication-1');

    $result = $this->handler($repository, $queue, $this->resources(1), true)->__invoke($this->command());

    self::assertSame($publication, $result->publication);
  }

  #[Test]
  public function testIdempotentPendingPublicationIsQueuedAgain(): void
  {
    $publication = $this->publication();
    $repository = $this->repository();
    $repository->expects(self::once())->method('interventionContext')->willReturn($this->context());
    $repository->expects(self::once())->method('findByInterventionRevision')->willReturn($publication);
    $repository->expects(self::never())->method('createOrGetPending');

    $queue = $this->queue();
    $queue->expects(self::once())->method('dispatch')->with('publication-1');

    $result = $this->handler($repository, $queue, $this->resources(1), true)->__invoke($this->command());

    self::assertSame($publication, $result->publication);
  }

  #[Test]
  public function testBlockerPreventsPublicationCreation(): void
  {
    $repository = $this->repository();
    $repository->expects(self::once())->method('interventionContext')->willReturn($this->context());
    $repository->expects(self::once())->method('findByInterventionRevision')->willReturn(null);
    $repository->expects(self::never())->method('createOrGetPending');

    $queue = $this->queue();
    $queue->expects(self::never())->method('dispatch');

    $this->expectException(InterventionBlockedException::class);

    $this->handler($repository, $queue, $this->resources(0), true)->__invoke($this->command());
  }

  #[Test]
  public function testFailedPublicationIsResetAndQueuedForRetry(): void
  {
    $failed = new PublicationView(
      'publication-1',
      'intervention-1',
      42,
      'failed',
      'Temporary failure',
      new DateTimeImmutable(),
      new DateTimeImmutable(),
    );
    $pending = $this->publication();
    $repository = $this->repository();
    $repository->expects(self::once())->method('interventionContext')->willReturn($this->context());
    $repository->expects(self::once())->method('findByInterventionRevision')->willReturn($failed);
    $repository->expects(self::once())->method('retryFailed')->with('publication-1')->willReturn($pending);
    $repository->expects(self::never())->method('createOrGetPending');

    $queue = $this->queue();
    $queue->expects(self::once())->method('dispatch')->with('publication-1');

    $result = $this->handler($repository, $queue, $this->resources(1), true)->__invoke($this->command());

    self::assertSame($pending, $result->publication);
  }

  private function command(): RequestPublicationCommand
  {
    return new RequestPublicationCommand('user-1', 'intervention-1', 42);
  }

  private function context(): InterventionPublicationContext
  {
    return new InterventionPublicationContext('intervention-1', 'organization-1', 'submitted', 42);
  }

  private function publication(): PublicationView
  {
    return new PublicationView('publication-1', 'intervention-1', 42, 'pending', null, new DateTimeImmutable(), null);
  }

  /**
   * @return PublicationRepositoryPort&MockObject
   */
  private function repository(): PublicationRepositoryPort
  {
    return $this->createMock(PublicationRepositoryPort::class);
  }

  /**
   * @return PublicationQueuePort&MockObject
   */
  private function queue(): PublicationQueuePort
  {
    return $this->createMock(PublicationQueuePort::class);
  }

  private function resources(int $facilities): InterventionResourceGatewayPort
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('summary')->willReturn(new InterventionResourceSummary($facilities, 0, 0));
    $resources->method('equipmentDrafts')->willReturn([]);
    $resources->method('workItemSummary')->willReturn(new InterventionWorkItemSummary(0, 0, 0, 0));

    return $resources;
  }

  private function handler(
    PublicationRepositoryPort $repository,
    PublicationQueuePort $queue,
    InterventionResourceGatewayPort $resources,
    bool $authorized,
  ): RequestPublicationHandler {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn($authorized);
    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('generateRaw')->willReturn('publication-1');

    return new RequestPublicationHandler(
      $repository,
      $queue,
      new InterventionIssueFinder($resources),
      $authorization,
      $uuidFactory,
    );
  }
}
