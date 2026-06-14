<?php

declare(strict_types=1);

namespace Tests\Unit\Mission\Application\UseCase\Command\Publication\ExecutePublication;

use DateTimeImmutable;
use Mission\Application\Contract\Publication\{MissionPublicationContext, PublicationView};
use Mission\Application\Contract\Resource\{MissionResourceSummary, MissionWorkItemSummary};
use Mission\Application\Port\Outbound\{MissionResourceGatewayPort, PublicationRepositoryPort};
use Mission\Application\Service\MissionIssueFinder;
use Mission\Application\UseCase\Command\Publication\ExecutePublication\{ExecutePublicationCommand, ExecutePublicationHandler};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExecutePublicationHandler::class)]
final class ExecutePublicationHandlerTest extends TestCase
{
  #[Test]
  public function testPublishesReadyMission(): void
  {
    $repository = $this->repository();
    $repository->expects(self::once())->method('find')->willReturn($this->publication());
    $repository->expects(self::once())->method('missionContext')->willReturn($this->context(42));
    $repository->expects(self::once())->method('markProcessing')->with('publication-1');
    $repository->expects(self::once())->method('publish')->with('publication-1');
    $repository->expects(self::never())->method('markFailed');

    $this->handler($repository)->__invoke(new ExecutePublicationCommand('publication-1'));
  }

  #[Test]
  public function testChangedMissionMarksPublicationFailedWithoutPublishing(): void
  {
    $repository = $this->repository();
    $repository->expects(self::once())->method('find')->willReturn($this->publication());
    $repository->expects(self::once())->method('missionContext')->willReturn($this->context(43));
    $repository->expects(self::never())->method('markProcessing');
    $repository->expects(self::never())->method('publish');
    $repository->expects(self::once())
      ->method('markFailed')
      ->with('publication-1', 'Mission changed before publication execution.');

    $this->handler($repository)->__invoke(new ExecutePublicationCommand('publication-1'));
  }

  private function publication(): PublicationView
  {
    return new PublicationView('publication-1', 'mission-1', 42, 'pending', null, new DateTimeImmutable(), null);
  }

  private function context(int $revision): MissionPublicationContext
  {
    return new MissionPublicationContext('mission-1', 'organization-1', 'submitted', $revision);
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
    $resources = $this->createStub(MissionResourceGatewayPort::class);
    $resources->method('summary')->willReturn(new MissionResourceSummary(1, 0, 0));
    $resources->method('equipmentDrafts')->willReturn([]);
    $resources->method('workItemSummary')->willReturn(new MissionWorkItemSummary(0, 0, 0, 0));

    return new ExecutePublicationHandler($repository, new MissionIssueFinder($resources));
  }
}
