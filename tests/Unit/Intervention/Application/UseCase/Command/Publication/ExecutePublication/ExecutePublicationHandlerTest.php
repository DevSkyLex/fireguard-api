<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Command\Publication\ExecutePublication;

use DateTimeImmutable;
use Intervention\Application\Contract\Publication\{InterventionPublicationContext, PublicationView};
use Intervention\Application\Contract\Resource\{InterventionResourceSummary, InterventionWorkItemSummary};
use Intervention\Application\Port\Outbound\{InterventionResourceGatewayPort, PublicationRepositoryPort};
use Intervention\Application\Service\InterventionIssueFinder;
use Intervention\Application\UseCase\Command\Publication\ExecutePublication\{ExecutePublicationCommand, ExecutePublicationHandler};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

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
    $repository->expects(self::once())->method('publish')->with('publication-1');
    $repository->expects(self::never())->method('markFailed');

    $this->handler($repository)->__invoke(new ExecutePublicationCommand('publication-1'));
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
      ->with('publication-1', 'Intervention changed before publication execution.');

    $this->handler($repository)->__invoke(new ExecutePublicationCommand('publication-1'));
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

  private function handler(PublicationRepositoryPort $repository): ExecutePublicationHandler
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('summary')->willReturn(new InterventionResourceSummary(1, 0, 0));
    $resources->method('equipmentDrafts')->willReturn([]);
    $resources->method('workItemSummary')->willReturn(new InterventionWorkItemSummary(0, 0, 0, 0));

    return new ExecutePublicationHandler($repository, new InterventionIssueFinder($resources));
  }
}
