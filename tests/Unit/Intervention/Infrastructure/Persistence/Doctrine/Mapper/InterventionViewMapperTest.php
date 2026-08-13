<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Infrastructure\Persistence\Doctrine\Mapper;

use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Port\Outbound\{InterventionAttachmentRepositoryPort, InterventionResourceGatewayPort};
use Intervention\Application\Service\InterventionIssueFinder;
use Intervention\Domain\Exception\{InterventionConflictException, InterventionNotFoundException};
use Intervention\Infrastructure\Persistence\Doctrine\Mapper\InterventionViewMapper;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{
  InterventionActivityRecord,
  InterventionChangeRecord,
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

  private function mapper(): InterventionViewMapper
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);

    return new InterventionViewMapper(
      $this->createStub(EntityManagerInterface::class),
      $resources,
      new InterventionIssueFinder($resources, $this->createStub(InterventionAttachmentRepositoryPort::class)),
    );
  }
}
