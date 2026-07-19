<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\{Connection, Result};
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository, Query, QueryBuilder};
use Inspection\Domain\Model\Inspection\Inspection;
use Inspection\Domain\ValueObject\{InspectionEquipmentId, InspectionId, InspectionOrganizationId, InspectionResult, InspectionStatus, Inspector};
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use Inspection\Infrastructure\Persistence\Doctrine\Repository\InspectionRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Doctrine\Search\TrigramSearchExpression;

use function str_contains;

#[CoversClass(InspectionRepository::class)]
final class InspectionRepositoryTest extends TestCase
{
  #[Test]
  public function testCountByOrganizationIdNormalizesDateFiltersToConfiguredStorageTimezone(): void
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
    $queryBuilder->method('where')->willReturnSelf();
    $queryBuilder->method('andWhere')->willReturnSelf();
    $queryBuilder->method('setParameter')->willReturnCallback(function (string $name, mixed $value, mixed $type = null) use (&$capturedParameters, &$capturedTypes, $queryBuilder): QueryBuilder {
      $capturedParameters[$name] = $value;
      $capturedTypes[$name] = $type;

      return $queryBuilder;
    });
    $queryBuilder->expects(self::once())->method('getQuery')->willReturn($query);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())->method('getRepository')->with(InspectionRecord::class)->willReturn($doctrineRepository);
    $entityManager->expects(self::once())->method('getReference')->with(OrganizationRecord::class, (string) $organizationId)->willReturn(new OrganizationRecord());
    $entityManager->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);

    $repository = new InspectionRepository(
      entityManager: $entityManager,
      storageTimeZone: 'UTC',
    );

    $repository->countByOrganizationId(
      organizationId: $organizationId,
      performedAtFrom: '2026-03-29T00:00:00+01:00',
      performedAtTo: '2026-03-30T23:59:59+02:00',
    );

    self::assertInstanceOf(DateTimeImmutable::class, $capturedParameters['performedAtFrom']);
    self::assertInstanceOf(DateTimeImmutable::class, $capturedParameters['performedAtTo']);
    self::assertSame('2026-03-28T23:00:00+00:00', $capturedParameters['performedAtFrom']->format('c'));
    self::assertSame('2026-03-30T21:59:59+00:00', $capturedParameters['performedAtTo']->format('c'));
    self::assertSame(Types::DATETIME_IMMUTABLE, $capturedTypes['performedAtFrom']);
    self::assertSame(Types::DATETIME_IMMUTABLE, $capturedTypes['performedAtTo']);
  }

  #[Test]
  public function testFindByIdReinterpretsStoredTimestampsUsingConfiguredStorageTimezone(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = '550e8400-e29b-41d4-a716-446655440010';

    $record = new InspectionRecord();
    $record->id = '550e8400-e29b-41d4-a716-446655440011';
    $record->organization = $organization;
    $record->equipmentId = '550e8400-e29b-41d4-a716-446655440012';
    $record->inspectorType = 'user';
    $record->inspectorName = 'Inspector';
    $record->inspectorUserId = '550e8400-e29b-41d4-a716-446655440013';
    $record->inspectorOrganizationName = null;
    $record->result = 'pass';
    $record->status = 'draft';
    $record->performedAt = new DateTimeImmutable('2026-03-30T00:30:00+02:00');
    $record->checklistId = null;
    $record->notes = null;
    $record->signature = null;
    $record->createdAt = new DateTimeImmutable('2026-03-30T09:15:00+02:00');
    $record->updatedAt = new DateTimeImmutable('2026-03-30T10:45:00+02:00');

    $doctrineRepository = $this->createMock(EntityRepository::class);
    $doctrineRepository->expects(self::once())
      ->method('find')
      ->with('550e8400-e29b-41d4-a716-446655440011')
      ->willReturn($record);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())->method('getRepository')->with(InspectionRecord::class)->willReturn($doctrineRepository);

    $repository = new InspectionRepository(
      entityManager: $entityManager,
      storageTimeZone: 'UTC',
    );

    $inspection = $repository->findById(InspectionId::fromString('550e8400-e29b-41d4-a716-446655440011'));

    self::assertInstanceOf(Inspection::class, $inspection);
    self::assertSame('2026-03-30T00:30:00+00:00', $inspection->performedAt()->format('c'));
    self::assertSame('2026-03-30T09:15:00+00:00', $inspection->createdAt()->format('c'));
    self::assertSame('2026-03-30T10:45:00+00:00', $inspection->updatedAt()->format('c'));
  }

  #[Test]
  public function testSaveNormalizesPersistedTimestampsToConfiguredStorageTimezone(): void
  {
    $inspection = Inspection::reconstitute(
      id: InspectionId::fromString('550e8400-e29b-41d4-a716-446655440021'),
      organizationId: InspectionOrganizationId::fromString('550e8400-e29b-41d4-a716-446655440022'),
      equipmentId: InspectionEquipmentId::fromString('550e8400-e29b-41d4-a716-446655440023'),
      inspector: Inspector::forUser('550e8400-e29b-41d4-a716-446655440024', 'Inspector'),
      result: InspectionResult::PASS,
      status: InspectionStatus::DRAFT,
      performedAt: new DateTimeImmutable('2026-03-30T10:30:00+02:00'),
      createdAt: new DateTimeImmutable('2026-03-30T11:00:00+02:00'),
      updatedAt: new DateTimeImmutable('2026-03-30T12:00:00+02:00'),
    );

    $doctrineRepository = $this->createMock(EntityRepository::class);
    $doctrineRepository->expects(self::once())
      ->method('find')
      ->with('550e8400-e29b-41d4-a716-446655440021')
      ->willReturn(null);

    $organization = new OrganizationRecord();
    $organization->id = '550e8400-e29b-41d4-a716-446655440022';

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())->method('getRepository')->with(InspectionRecord::class)->willReturn($doctrineRepository);
    $entityManager->expects(self::once())->method('getReference')->with(OrganizationRecord::class, '550e8400-e29b-41d4-a716-446655440022')->willReturn($organization);
    $entityManager->expects(self::once())
      ->method('persist')
      ->with(self::callback(static function (InspectionRecord $record): bool {
        return '2026-03-30T08:30:00+00:00' === $record->performedAt->format('c')
          && '2026-03-30T09:00:00+00:00' === $record->createdAt->format('c')
          && '2026-03-30T10:00:00+00:00' === $record->updatedAt->format('c');
      }));
    $entityManager->expects(self::once())->method('flush');

    $repository = new InspectionRepository(
      entityManager: $entityManager,
      storageTimeZone: 'UTC',
    );

    $repository->save($inspection);
  }

  #[Test]
  public function testCountByPerformedDayForOrganizationIdReinterpretsHydratedDatetimesUsingConfiguredStorageTimezone(): void
  {
    $organizationId = InspectionOrganizationId::fromString('550e8400-e29b-41d4-a716-446655440031');
    $doctrineRepository = $this->createStub(EntityRepository::class);

    $platform = $this->createMock(AbstractPlatform::class);
    $platform->expects(self::once())->method('getName')->willReturn('sqlite');

    $connection = $this->createMock(Connection::class);
    $connection->expects(self::once())->method('getDatabasePlatform')->willReturn($platform);

    $query = $this->createMock(Query::class);
    $query->expects(self::once())->method('getArrayResult')->willReturn([
      ['performedAt' => new DateTimeImmutable('2026-03-30T00:30:00+02:00')],
    ]);

    $queryBuilder = $this->createMock(QueryBuilder::class);
    $queryBuilder->method('select')->willReturnSelf();
    $queryBuilder->method('from')->willReturnSelf();
    $queryBuilder->method('where')->willReturnSelf();
    $queryBuilder->method('andWhere')->willReturnSelf();
    $queryBuilder->method('setParameter')->willReturnSelf();
    $queryBuilder->method('orderBy')->willReturnSelf();
    $queryBuilder->expects(self::once())->method('getQuery')->willReturn($query);

    $organization = new OrganizationRecord();
    $organization->id = (string) $organizationId;

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())->method('getRepository')->with(InspectionRecord::class)->willReturn($doctrineRepository);
    $entityManager->expects(self::once())->method('getConnection')->willReturn($connection);
    $entityManager->expects(self::once())->method('getReference')->with(OrganizationRecord::class, (string) $organizationId)->willReturn($organization);
    $entityManager->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);

    $repository = new InspectionRepository(
      entityManager: $entityManager,
      storageTimeZone: 'UTC',
    );

    self::assertSame(
      ['2026-03-30' => 1],
      $repository->countByPerformedDayForOrganizationId(
        organizationId: $organizationId,
        performedAtFrom: '2026-03-30T00:00:00+00:00',
        performedAtTo: '2026-03-30T23:59:59+00:00',
        timeZone: 'UTC',
      ),
    );
  }

  #[Test]
  public function testCountByPerformedDayForOrganizationIdPreservesMicrosecondsOnPostgreSqlBounds(): void
  {
    $organizationId = InspectionOrganizationId::fromString('550e8400-e29b-41d4-a716-446655440032');
    $doctrineRepository = $this->createStub(EntityRepository::class);

    $platform = $this->createMock(AbstractPlatform::class);
    $platform->expects(self::once())->method('getName')->willReturn('postgresql');

    $result = $this->createMock(Result::class);
    $result->expects(self::once())->method('fetchAllAssociative')->willReturn([]);

    $connection = $this->createMock(Connection::class);
    $connection->expects(self::once())->method('getDatabasePlatform')->willReturn($platform);
    $connection->expects(self::once())
      ->method('executeQuery')
      ->with(
        self::isString(),
        self::callback(static function (array $params): bool {
          self::assertSame('2026-03-28 23:00:00.500000', $params['performedAtFrom']);
          self::assertSame('2026-03-30 21:59:59.250000', $params['performedAtTo']);

          return true;
        }),
      )
      ->willReturn($result);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())->method('getRepository')->with(InspectionRecord::class)->willReturn($doctrineRepository);
    $entityManager->expects(self::exactly(2))->method('getConnection')->willReturn($connection);

    $repository = new InspectionRepository(
      entityManager: $entityManager,
      storageTimeZone: 'UTC',
    );

    self::assertSame(
      [],
      $repository->countByPerformedDayForOrganizationId(
        organizationId: $organizationId,
        performedAtFrom: '2026-03-29T00:00:00.500000+01:00',
        performedAtTo: '2026-03-30T23:59:59.250000+02:00',
        timeZone: 'Europe/Paris',
      ),
    );
  }

  #[Test]
  public function testCountByPerformedDayForOrganizationIdPostgreSqlAppliesOptionalFiltersInSql(): void
  {
    $organizationId = InspectionOrganizationId::fromString('550e8400-e29b-41d4-a716-446655440033');
    $doctrineRepository = $this->createStub(EntityRepository::class);

    $platform = $this->createMock(AbstractPlatform::class);
    $platform->expects(self::once())->method('getName')->willReturn('postgresql');

    $result = $this->createMock(Result::class);
    $result->expects(self::once())->method('fetchAllAssociative')->willReturn([]);

    $connection = $this->createMock(Connection::class);
    $connection->expects(self::once())->method('getDatabasePlatform')->willReturn($platform);
    $connection->expects(self::once())
      ->method('executeQuery')
      ->with(
        self::callback(static function (string $sql): bool {
          self::assertStringContainsString('AND status = :status', $sql);
          self::assertStringContainsString('AND result = :result', $sql);
          self::assertStringContainsString('AND inspector_type = :inspectorType', $sql);

          return true;
        }),
        self::callback(static function (array $params): bool {
          self::assertSame('closed', $params['status']);
          self::assertSame('pass', $params['result']);
          self::assertSame('user', $params['inspectorType']);

          return true;
        }),
      )
      ->willReturn($result);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())->method('getRepository')->with(InspectionRecord::class)->willReturn($doctrineRepository);
    $entityManager->expects(self::exactly(2))->method('getConnection')->willReturn($connection);

    $repository = new InspectionRepository(
      entityManager: $entityManager,
      storageTimeZone: 'UTC',
    );

    self::assertSame(
      [],
      $repository->countByPerformedDayForOrganizationId(
        organizationId: $organizationId,
        performedAtFrom: '2026-03-29T00:00:00+00:00',
        performedAtTo: '2026-03-30T23:59:59+00:00',
        timeZone: 'UTC',
        status: 'closed',
        result: 'pass',
        inspectorType: 'user',
      ),
    );
  }

  #[Test]
  public function testCountByOrganizationIdPushesWildcardSafeSearchPredicateIntoSql(): void
  {
    $organizationId = InspectionOrganizationId::fromString('550e8400-e29b-41d4-a716-446655440041');
    $doctrineRepository = $this->createStub(EntityRepository::class);
    $query = $this->createMock(Query::class);
    $query->expects(self::once())->method('getSingleScalarResult')->willReturn('0');

    $capturedWhereClauses = [];
    $capturedParameters = [];
    $queryBuilder = $this->createMock(QueryBuilder::class);
    $queryBuilder->method('select')->willReturnSelf();
    $queryBuilder->method('from')->willReturnSelf();
    $queryBuilder->method('where')->willReturnSelf();
    $queryBuilder->method('andWhere')->willReturnCallback(function (string $expression) use (&$capturedWhereClauses, $queryBuilder): QueryBuilder {
      $capturedWhereClauses[] = $expression;

      return $queryBuilder;
    });
    $queryBuilder->method('setParameter')->willReturnCallback(function (string $name, mixed $value) use (&$capturedParameters, $queryBuilder): QueryBuilder {
      $capturedParameters[$name] = $value;

      return $queryBuilder;
    });
    $queryBuilder->expects(self::once())->method('getQuery')->willReturn($query);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())->method('getRepository')->with(InspectionRecord::class)->willReturn($doctrineRepository);
    $entityManager->expects(self::once())->method('getReference')->with(OrganizationRecord::class, (string) $organizationId)->willReturn(new OrganizationRecord());
    $entityManager->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);

    $repository = new InspectionRepository(
      entityManager: $entityManager,
      storageTimeZone: 'UTC',
    );

    $repository->countByOrganizationId(
      organizationId: $organizationId,
      search: 'A_B pass',
    );

    $searchClause = null;
    foreach ($capturedWhereClauses as $clause) {
      if (str_contains($clause, 'i.inspectorName')) {
        $searchClause = $clause;
      }
    }

    self::assertNotNull($searchClause, 'The search predicate must be pushed down into the query builder.');
    self::assertSame(
      "(LOWER(i.result) LIKE :search ESCAPE '\\' OR LOWER(i.status) LIKE :search ESCAPE '\\' OR LOWER(i.inspectorName) LIKE :search ESCAPE '\\' OR LOWER(i.equipmentId) LIKE :search ESCAPE '\\' OR LOWER(i.facilityId) LIKE :search ESCAPE '\\' OR LOWER(i.checklistId) LIKE :search ESCAPE '\\')",
      $searchClause,
    );

    self::assertSame(TrigramSearchExpression::likeValue('A_B pass'), $capturedParameters['search']);
    self::assertSame('%a\\_b pass%', $capturedParameters['search']);
  }
}
