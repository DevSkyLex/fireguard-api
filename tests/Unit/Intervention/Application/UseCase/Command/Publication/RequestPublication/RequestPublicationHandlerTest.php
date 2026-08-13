<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Command\Publication\RequestPublication;

use DateTimeImmutable;
use Intervention\Application\Contract\Publication\{InterventionPublicationContext, PublicationView};
use Intervention\Application\Contract\Resource\{InterventionResourceSummary, InterventionWorkItemSummary};
use Intervention\Application\Port\Outbound\{InterventionResourceGatewayPort, PublicationQueuePort, PublicationRepositoryPort};
use Intervention\Application\Service\InterventionIssueFinder;
use Intervention\Application\UseCase\Command\Publication\RequestPublication\{RequestPublicationCommand, RequestPublicationHandler};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionBlockedException, InterventionConflictException, InterventionNotFoundException};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
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

    $result = $this->handler($repository, $queue, $this->resources(1), OrganizationAccessDecision::GRANTED)->__invoke($this->command());

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

    $result = $this->handler($repository, $queue, $this->resources(1), OrganizationAccessDecision::GRANTED)->__invoke($this->command());

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

    $this->handler($repository, $queue, $this->resources(0), OrganizationAccessDecision::GRANTED)->__invoke($this->command());
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

    $result = $this->handler($repository, $queue, $this->resources(1), OrganizationAccessDecision::GRANTED)->__invoke($this->command());

    self::assertSame($pending, $result->publication);
  }

  #[Test]
  public function testThrowsWhenTheInterventionIsUnknown(): void
  {
    $repository = $this->repository();
    $repository->expects(self::once())->method('interventionContext')->willReturn(null);
    $repository->expects(self::never())->method('findByInterventionRevision');

    $queue = $this->queue();
    $queue->expects(self::never())->method('dispatch');

    $this->expectException(InterventionNotFoundException::class);

    $this->handler($repository, $queue, $this->resources(1), OrganizationAccessDecision::GRANTED)->__invoke($this->command());
  }

  #[Test]
  public function testThrowsWhenThePublishPermissionIsMissing(): void
  {
    $repository = $this->repository();
    $repository->expects(self::once())->method('interventionContext')->willReturn($this->context());
    $repository->expects(self::never())->method('findByInterventionRevision');

    $queue = $this->queue();
    $queue->expects(self::never())->method('dispatch');

    $this->expectException(InterventionAccessDeniedException::class);

    $this->handler($repository, $queue, $this->resources(1), OrganizationAccessDecision::MISSING_PERMISSION)->__invoke($this->command());
  }

  #[Test]
  public function testThrowsNotFoundWhenTheCallerIsOutsideTheOwningOrganization(): void
  {
    $repository = $this->repository();
    $repository->expects(self::once())->method('interventionContext')->willReturn($this->context());
    $repository->expects(self::never())->method('findByInterventionRevision');

    $queue = $this->queue();
    $queue->expects(self::never())->method('dispatch');

    // The same exception testThrowsWhenTheInterventionIsUnknown asserts: an
    // outsider must not learn this intervention id is real.
    $this->expectException(InterventionNotFoundException::class);

    $this->handler($repository, $queue, $this->resources(1), OrganizationAccessDecision::OUTSIDE_SCOPE)->__invoke($this->command());
  }

  #[Test]
  public function testThrowsWhenTheInterventionIsNotSubmitted(): void
  {
    $repository = $this->repository();
    $repository->expects(self::once())->method('interventionContext')->willReturn(
      new InterventionPublicationContext('intervention-1', 'organization-1', 'draft', 42),
    );
    $repository->expects(self::once())->method('findByInterventionRevision')->willReturn(null);

    $queue = $this->queue();
    $queue->expects(self::never())->method('dispatch');

    $this->expectException(InterventionConflictException::class);
    $this->expectExceptionMessage('Only submitted interventions can be published.');

    $this->handler($repository, $queue, $this->resources(1), OrganizationAccessDecision::GRANTED)->__invoke($this->command());
  }

  #[Test]
  public function testThrowsWhenTheRevisionDoesNotMatch(): void
  {
    $repository = $this->repository();
    $repository->expects(self::once())->method('interventionContext')->willReturn(
      new InterventionPublicationContext('intervention-1', 'organization-1', 'submitted', 7),
    );
    $repository->expects(self::once())->method('findByInterventionRevision')->willReturn(null);

    $queue = $this->queue();
    $queue->expects(self::never())->method('dispatch');

    $this->expectException(InterventionConflictException::class);
    $this->expectExceptionMessage('Intervention revision does not match.');

    $this->handler($repository, $queue, $this->resources(1), OrganizationAccessDecision::GRANTED)->__invoke($this->command());
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
    OrganizationAccessDecision $decision,
  ): RequestPublicationHandler {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn($decision);
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
