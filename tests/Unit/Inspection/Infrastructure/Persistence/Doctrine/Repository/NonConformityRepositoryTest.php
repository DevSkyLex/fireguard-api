<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\{Connection, Result};
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository, Query, QueryBuilder};
use Inspection\Domain\Model\NonConformity\NonConformity;
use Inspection\Domain\ValueObject\{InspectionOrganizationId, NonConformityId, NonConformityInspectionId, NonConformitySeverity, NonConformityStatus};
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, NonConformityRecord};
use Inspection\Infrastructure\Persistence\Doctrine\Repository\NonConformityRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(NonConformityRepository::class)]
final class NonConformityRepositoryTest extends TestCase
{
  #[Test]
  public function testCountOverdueByOrganizationIdNormalizesDateFilterToConfiguredStorageTimezone(): void
  {
    $organizationId = InspectionOrganizationId::fromString('550e8400-e29b-41d4-a716-446655440001');
    $doctrineRepository = $this->createStub(EntityRepository::class);
    $query = $this->createMock(Query::class);
    $query->expects(self::once())->method('getSingleScalarResult')->willReturn('0');

    $capturedParameters = [];
    $capturedTypes = [];
    $queryBuilder = $this->createMock(QueryBuilder::class);
    $queryBuilder->method('select')->willReturnSelf();
    $queryBuilder->method('from')->willReturnSelf();
    $queryBuilder->method('innerJoin')->willReturnSelf();
    $queryBuilder->method('andWhere')->willReturnSelf();
    $queryBuilder->method('setParameter')->willReturnCallback(function (string $name, mixed $value, mixed $type = null) use (&$capturedParameters, &$capturedTypes, $queryBuilder): QueryBuilder {
      $capturedParameters[$name] = $value;
      $capturedTypes[$name] = $type;

      return $queryBuilder;
    });
    $queryBuilder->expects(self::once())->method('getQuery')->willReturn($query);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())->method('getRepository')->with(NonConformityRecord::class)->willReturn($doctrineRepository);
    $entityManager->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);

    $repository = new NonConformityRepository(
      entityManager: $entityManager,
      storageTimeZone: 'UTC',
    );

    $repository->countOverdueByOrganizationId(
      organizationId: $organizationId,
      dueAtBefore: '2026-03-30T10:00:00+02:00',
    );

    self::assertInstanceOf(DateTimeImmutable::class, $capturedParameters['dueAtBefore']);
    self::assertSame('2026-03-30T08:00:00+00:00', $capturedParameters['dueAtBefore']->format('c'));
    self::assertSame(Types::DATETIME_IMMUTABLE, $capturedTypes['dueAtBefore']);
  }

  #[Test]
  public function testFindByIdReinterpretsStoredTimestampsUsingConfiguredStorageTimezone(): void
  {
    $inspection = new InspectionRecord();
    $inspection->id = '550e8400-e29b-41d4-a716-446655440041';

    $record = new NonConformityRecord();
    $record->id = '550e8400-e29b-41d4-a716-446655440042';
    $record->inspection = $inspection;
    $record->description = 'Missing extinguisher';
    $record->severity = 'high';
    $record->status = 'open';
    $record->dueAt = new DateTimeImmutable('2026-03-31T08:00:00+02:00');
    $record->resolvedAt = new DateTimeImmutable('2026-04-01T09:00:00+02:00');
    $record->notes = null;
    $record->createdAt = new DateTimeImmutable('2026-03-30T06:30:00+02:00');
    $record->updatedAt = new DateTimeImmutable('2026-03-30T07:45:00+02:00');

    $doctrineRepository = $this->createMock(EntityRepository::class);
    $doctrineRepository->expects(self::once())
      ->method('find')
      ->with('550e8400-e29b-41d4-a716-446655440042')
      ->willReturn($record);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())->method('getRepository')->with(NonConformityRecord::class)->willReturn($doctrineRepository);

    $repository = new NonConformityRepository(
      entityManager: $entityManager,
      storageTimeZone: 'UTC',
    );

    $nonConformity = $repository->findById(NonConformityId::fromString('550e8400-e29b-41d4-a716-446655440042'));

    self::assertInstanceOf(NonConformity::class, $nonConformity);
    self::assertSame('2026-03-31T08:00:00+00:00', $nonConformity->dueAt()?->format('c'));
    self::assertSame('2026-04-01T09:00:00+00:00', $nonConformity->resolvedAt()?->format('c'));
    self::assertSame('2026-03-30T06:30:00+00:00', $nonConformity->createdAt()->format('c'));
    self::assertSame('2026-03-30T07:45:00+00:00', $nonConformity->updatedAt()->format('c'));
  }

  #[Test]
  public function testSaveNormalizesPersistedTimestampsToConfiguredStorageTimezone(): void
  {
    $nonConformity = NonConformity::reconstitute(
      id: NonConformityId::fromString('550e8400-e29b-41d4-a716-446655440051'),
      inspectionId: NonConformityInspectionId::fromString('550e8400-e29b-41d4-a716-446655440052'),
      description: 'Missing extinguisher',
      severity: NonConformitySeverity::HIGH,
      status: NonConformityStatus::OPEN,
      createdAt: new DateTimeImmutable('2026-03-30T11:00:00+02:00'),
      updatedAt: new DateTimeImmutable('2026-03-30T12:00:00+02:00'),
      dueAt: new DateTimeImmutable('2026-03-31T08:00:00+02:00'),
      resolvedAt: new DateTimeImmutable('2026-04-01T09:00:00+02:00'),
      notes: null,
    );

    $doctrineRepository = $this->createMock(EntityRepository::class);
    $doctrineRepository->expects(self::once())
      ->method('find')
      ->with('550e8400-e29b-41d4-a716-446655440051')
      ->willReturn(null);

    $inspection = new InspectionRecord();
    $inspection->id = '550e8400-e29b-41d4-a716-446655440052';

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())->method('getRepository')->with(NonConformityRecord::class)->willReturn($doctrineRepository);
    $entityManager->expects(self::once())->method('getReference')->with(InspectionRecord::class, '550e8400-e29b-41d4-a716-446655440052')->willReturn($inspection);
    $entityManager->expects(self::once())
      ->method('persist')
      ->with(self::callback(static function (NonConformityRecord $record): bool {
        return '2026-03-30T09:00:00+00:00' === $record->createdAt->format('c')
          && '2026-03-30T10:00:00+00:00' === $record->updatedAt->format('c')
          && '2026-03-31T06:00:00+00:00' === $record->dueAt?->format('c')
          && '2026-04-01T07:00:00+00:00' === $record->resolvedAt?->format('c');
      }));
    $entityManager->expects(self::once())->method('flush');

    $repository = new NonConformityRepository(
      entityManager: $entityManager,
      storageTimeZone: 'UTC',
    );

    $repository->save($nonConformity);
  }

  #[Test]
  public function testCountByCreatedDayForOrganizationIdPreservesMicrosecondsOnPostgreSqlBounds(): void
  {
    $organizationId = InspectionOrganizationId::fromString('550e8400-e29b-41d4-a716-446655440062');
    $doctrineRepository = $this->createStub(EntityRepository::class);

    $result = $this->createMock(Result::class);
    $result->expects(self::once())->method('fetchAllAssociative')->willReturn([]);

    $connection = $this->createMock(Connection::class);
    $connection->expects(self::once())
      ->method('executeQuery')
      ->with(
        self::isString(),
        self::callback(static function (array $params): bool {
          self::assertSame('2026-03-28 23:00:00.500000', $params['createdAtFrom']);
          self::assertSame('2026-03-30 21:59:59.250000', $params['createdAtTo']);

          return true;
        }),
      )
      ->willReturn($result);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())->method('getRepository')->with(NonConformityRecord::class)->willReturn($doctrineRepository);
    $entityManager->expects(self::once())->method('getConnection')->willReturn($connection);

    $repository = new NonConformityRepository(
      entityManager: $entityManager,
      storageTimeZone: 'UTC',
    );

    self::assertSame(
      [],
      $repository->countByCreatedDayForOrganizationId(
        organizationId: $organizationId,
        createdAtFrom: '2026-03-29T00:00:00.500000+01:00',
        createdAtTo: '2026-03-30T23:59:59.250000+02:00',
        timeZone: 'Europe/Paris',
      ),
    );
  }

  #[Test]
  public function testCountByCreatedDayForOrganizationIdPostgreSqlAppliesOptionalFiltersInSql(): void
  {
    $organizationId = InspectionOrganizationId::fromString('550e8400-e29b-41d4-a716-446655440063');
    $doctrineRepository = $this->createStub(EntityRepository::class);

    $result = $this->createMock(Result::class);
    $result->expects(self::once())->method('fetchAllAssociative')->willReturn([]);

    $connection = $this->createMock(Connection::class);
    $connection->expects(self::once())
      ->method('executeQuery')
      ->with(
        self::callback(static function (string $sql): bool {
          self::assertStringContainsString('AND r.severity = :severity', $sql);
          self::assertStringContainsString('AND r.status = :status', $sql);

          return true;
        }),
        self::callback(static function (array $params): bool {
          self::assertSame('critical', $params['severity']);
          self::assertSame('open', $params['status']);

          return true;
        }),
      )
      ->willReturn($result);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())->method('getRepository')->with(NonConformityRecord::class)->willReturn($doctrineRepository);
    $entityManager->expects(self::once())->method('getConnection')->willReturn($connection);

    $repository = new NonConformityRepository(
      entityManager: $entityManager,
      storageTimeZone: 'UTC',
    );

    self::assertSame(
      [],
      $repository->countByCreatedDayForOrganizationId(
        organizationId: $organizationId,
        createdAtFrom: '2026-03-29T00:00:00+00:00',
        createdAtTo: '2026-03-30T23:59:59+00:00',
        timeZone: 'UTC',
        severity: 'critical',
        status: 'open',
      ),
    );
  }

  #[Test]
  public function testCountByResolvedDayForOrganizationIdPostgreSqlAppliesOptionalFiltersInSql(): void
  {
    $organizationId = InspectionOrganizationId::fromString('550e8400-e29b-41d4-a716-446655440064');
    $doctrineRepository = $this->createStub(EntityRepository::class);

    $result = $this->createMock(Result::class);
    $result->expects(self::once())->method('fetchAllAssociative')->willReturn([]);

    $connection = $this->createMock(Connection::class);
    $connection->expects(self::once())
      ->method('executeQuery')
      ->with(
        self::callback(static function (string $sql): bool {
          self::assertStringContainsString('AND r.severity = :severity', $sql);
          self::assertStringContainsString('AND r.status = :status', $sql);

          return true;
        }),
        self::callback(static function (array $params): bool {
          self::assertSame('high', $params['severity']);
          self::assertSame('done', $params['status']);

          return true;
        }),
      )
      ->willReturn($result);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())->method('getRepository')->with(NonConformityRecord::class)->willReturn($doctrineRepository);
    $entityManager->expects(self::once())->method('getConnection')->willReturn($connection);

    $repository = new NonConformityRepository(
      entityManager: $entityManager,
      storageTimeZone: 'UTC',
    );

    self::assertSame(
      [],
      $repository->countByResolvedDayForOrganizationId(
        organizationId: $organizationId,
        resolvedAtFrom: '2026-03-29T00:00:00+00:00',
        resolvedAtTo: '2026-03-30T23:59:59+00:00',
        timeZone: 'UTC',
        severity: 'high',
        status: 'done',
      ),
    );
  }
}
