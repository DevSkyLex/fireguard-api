<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\{EntityManagerInterface, PersistentCollection};
use Doctrine\ORM\Mapping\ClassMetadata;
use Intervention\Application\Contract\Resource\InterventionListMetrics;
use Intervention\Application\Port\Outbound\Internal\{InterventionAttachmentRepositoryPort, InterventionResourceGatewayPort};
use Intervention\Application\Port\Outbound\InterventionPublicationResourcesIssuesPort;
use Intervention\Application\Service\InterventionIssueFinder;
use Intervention\Domain\Exception\{InterventionConflictException, InterventionNotFoundException};
use Intervention\Infrastructure\Persistence\Doctrine\Mapper\InterventionViewMapper;
use Intervention\Infrastructure\Persistence\Doctrine\Projection\InterventionWorkItemReadMetrics;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{
  InterventionActivityRecord,
  InterventionChangeRecord,
  InterventionLabelRecord,
  InterventionRecord,
  InterventionWorkItemRecord
};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionViewMapperTest.
 *
 * Covers the guard clauses that reject records detached from their owning
 * intervention or organization; the happy paths need a live Doctrine
 * repository and are exercised by the integration suite.
 *
 * @category Mapper Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionViewMapper::class)]
final class InterventionViewMapperTest extends TestCase
{
  #[Test]
  public function testInterventionPageUsesItsSnapshotWithoutQueriesOrInitializingLabels(): void
  {
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::never())->method('getRepository');
    $entityManager->expects(self::never())->method('createQueryBuilder');
    $record = $this->intervention();
    $labels = new PersistentCollection($entityManager, new ClassMetadata(InterventionLabelRecord::class), new ArrayCollection());
    $labels->setInitialized(false);
    $record->labels = $labels;
    $snapshot = [['id' => 'label-1', 'name' => 'Safety', 'color' => '#ff0000']];

    $view = $this->mapper($entityManager)->interventionView($record, new InterventionListMetrics(commentsCount: 3, hasSignature: true, labels: $snapshot));

    self::assertSame(3, $view->data['commentsCount']);
    self::assertTrue($view->data['hasSignature']);
    self::assertSame($snapshot, $view->data['labels']);
    self::assertFalse($labels->isInitialized());
  }

  #[Test]
  public function testWorkItemPageUsesGroupedEvidenceAndTimeWithoutQueries(): void
  {
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::never())->method('getRepository');
    $entityManager->expects(self::never())->method('createQueryBuilder');
    $record = new InterventionWorkItemRecord();
    $record->id = 'work-item-1';
    $record->intervention = $this->intervention();
    $record->action = 'inspect';
    $record->createdAt = $record->updatedAt = new DateTimeImmutable('2026-10-01');

    $view = $this->mapper($entityManager)->workItemView($record, new InterventionWorkItemReadMetrics(2, 45));

    self::assertSame(2, $view->data['evidenceCount']);
    self::assertSame(45, $view->data['spentMinutes']);
  }

  #[Test]
  public function testInterventionViewRejectsARecordWithoutAnOrganization(): void
  {
    $intervention = new InterventionRecord();
    $intervention->id = 'intervention-1';

    $this->expectException(InterventionConflictException::class);
    $this->expectExceptionMessage('Intervention organization is missing.');

    $this->mapper()->interventionView($intervention);
  }

  #[Test]
  public function testWorkItemViewRejectsARecordWithoutAnIntervention(): void
  {
    $record = new InterventionWorkItemRecord();
    $record->id = 'work-item-1';

    $this->expectException(InterventionNotFoundException::class);

    $this->mapper()->workItemView($record);
  }

  #[Test]
  public function testChangeViewRejectsARecordWithoutAnIntervention(): void
  {
    $record = new InterventionChangeRecord();
    $record->id = 'change-1';

    $this->expectException(InterventionNotFoundException::class);

    $this->mapper()->changeView($record);
  }

  #[Test]
  public function testActivityViewRejectsARecordWithoutAnIntervention(): void
  {
    $record = new InterventionActivityRecord();
    $record->id = 'activity-1';

    $this->expectException(InterventionNotFoundException::class);

    $this->mapper()->activityView($record);
  }

  private function mapper(?EntityManagerInterface $entityManager = null): InterventionViewMapper
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);

    return new InterventionViewMapper(
      $entityManager ?? $this->createStub(EntityManagerInterface::class),
      $resources,
      new InterventionIssueFinder($resources, $this->createStub(InterventionAttachmentRepositoryPort::class), $this->createStub(InterventionPublicationResourcesIssuesPort::class)),
    );
  }

  private function intervention(): InterventionRecord
  {
    $record = new InterventionRecord();
    $record->id = 'intervention-1';
    $record->organizationId = 'organization-1';
    $record->name = 'Safety inspection';
    $record->createdAt = $record->updatedAt = new DateTimeImmutable('2026-10-01');

    return $record;
  }
}
