<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Infrastructure\Adapter\Maintenance;

use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository, Query, QueryBuilder};
use Equipment\Infrastructure\Adapter\Maintenance\EquipmentMaintenanceDirectoryAdapter;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Maintenance\Application\Contract\Directory\TrackableEquipment;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test EquipmentMaintenanceDirectoryAdapterTest.
 *
 * The maintenance sweep reads through this adapter, so the visibility rule
 * it enforces is load-bearing: draft intervention scratchpads
 * (`recordStatus !== 'published'`) must never enter a maintenance schedule.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentMaintenanceDirectoryAdapter::class)]
final class EquipmentMaintenanceDirectoryAdapterTest extends TestCase
{
  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655495001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655495002';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655495003';

  #[Test]
  public function testFindEquipmentOnlyLooksUpPublishedRecords(): void
  {
    $repository = $this->createMock(EntityRepository::class);
    $repository->expects(self::once())
      ->method('findOneBy')
      ->with(['id' => self::EQUIPMENT_ID, 'recordStatus' => 'published'])
      ->willReturn($this->record());

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('getRepository')->willReturn($repository);

    $view = new EquipmentMaintenanceDirectoryAdapter($entityManager)->findEquipment(self::EQUIPMENT_ID);

    self::assertInstanceOf(TrackableEquipment::class, $view);
    self::assertSame(self::EQUIPMENT_ID, $view->equipmentId);
    self::assertSame(self::ORGANIZATION_ID, $view->organizationId);
    self::assertSame(self::FACILITY_ID, $view->facilityId);
    self::assertSame('fire_extinguisher', $view->equipmentType);
    self::assertSame('operational', $view->status);
  }

  #[Test]
  public function testFindEquipmentReturnsNullWhenNothingMatches(): void
  {
    $repository = $this->createStub(EntityRepository::class);
    $repository->method('findOneBy')->willReturn(null);

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('getRepository')->willReturn($repository);

    self::assertNull(new EquipmentMaintenanceDirectoryAdapter($entityManager)->findEquipment(self::EQUIPMENT_ID));
  }

  #[Test]
  public function testFindEquipmentRefusesARecordWithoutAnOrganization(): void
  {
    $record = $this->record();
    $record->organization = null;

    $repository = $this->createStub(EntityRepository::class);
    $repository->method('findOneBy')->willReturn($record);

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('getRepository')->willReturn($repository);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Equipment organization is missing.');

    new EquipmentMaintenanceDirectoryAdapter($entityManager)->findEquipment(self::EQUIPMENT_ID);
  }

  #[Test]
  public function testListEquipmentPageMapsEveryRecordToATrackableView(): void
  {
    $second = $this->record();
    $second->id = '550e8400-e29b-41d4-a716-446655495004';
    $second->facilityId = null;
    $second->type = 'smoke_detector';

    $queryBuilder = $this->queryBuilder([$this->record(), $second]);

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('createQueryBuilder')->willReturn($queryBuilder);

    $views = new EquipmentMaintenanceDirectoryAdapter($entityManager)->listEquipmentPage(50, 0);

    self::assertCount(2, $views);
    self::assertSame(self::EQUIPMENT_ID, $views[0]->equipmentId);
    self::assertNull($views[1]->facilityId);
    self::assertSame('smoke_detector', $views[1]->equipmentType);
  }

  #[Test]
  public function testListEquipmentPageClampsANegativeOffsetAndAZeroLimit(): void
  {
    $paging = [];
    $queryBuilder = $this->queryBuilder([], $paging);

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('createQueryBuilder')->willReturn($queryBuilder);

    self::assertSame([], new EquipmentMaintenanceDirectoryAdapter($entityManager)->listEquipmentPage(0, -10));
    self::assertSame(['firstResult' => 0, 'maxResults' => 1], $paging);
  }

  #[Test]
  public function testListEquipmentPageForwardsTheRequestedWindow(): void
  {
    $paging = [];
    $queryBuilder = $this->queryBuilder([], $paging);

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('createQueryBuilder')->willReturn($queryBuilder);

    new EquipmentMaintenanceDirectoryAdapter($entityManager)->listEquipmentPage(200, 400);

    self::assertSame(['firstResult' => 400, 'maxResults' => 200], $paging);
  }

  /**
   * @param list<EquipmentRecord> $records
   * @param array<string, int> $paging captures the pagination window the adapter applied
   */
  private function queryBuilder(array $records, array &$paging = []): QueryBuilder
  {
    $query = $this->createStub(Query::class);
    $query->method('getResult')->willReturn($records);

    $queryBuilder = $this->createStub(QueryBuilder::class);
    $queryBuilder->method('select')->willReturnSelf();
    $queryBuilder->method('from')->willReturnSelf();
    $queryBuilder->method('where')->willReturnSelf();
    $queryBuilder->method('setParameter')->willReturnSelf();
    $queryBuilder->method('orderBy')->willReturnSelf();
    $queryBuilder->method('setFirstResult')->willReturnCallback(
      static function (int $offset) use ($queryBuilder, &$paging): QueryBuilder {
        $paging['firstResult'] = $offset;

        return $queryBuilder;
      },
    );
    $queryBuilder->method('setMaxResults')->willReturnCallback(
      static function (int $limit) use ($queryBuilder, &$paging): QueryBuilder {
        $paging['maxResults'] = $limit;

        return $queryBuilder;
      },
    );
    $queryBuilder->method('getQuery')->willReturn($query);

    return $queryBuilder;
  }

  private function record(): EquipmentRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;

    $record = new EquipmentRecord();
    $record->id = self::EQUIPMENT_ID;
    $record->organization = $organization;
    $record->facilityId = self::FACILITY_ID;
    $record->type = 'fire_extinguisher';
    $record->status = 'operational';
    $record->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $record->updatedAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return $record;
  }
}
