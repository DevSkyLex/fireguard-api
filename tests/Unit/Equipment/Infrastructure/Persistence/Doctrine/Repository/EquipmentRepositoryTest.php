<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository, Query, QueryBuilder};
use Equipment\Domain\ValueObject\EquipmentOrganizationId;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Equipment\Infrastructure\Persistence\Doctrine\Repository\EquipmentRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Doctrine\Search\TrigramSearchExpression;

use function str_contains;

/**
 * Test EquipmentRepositoryTest.
 *
 * Proves the free-text `search` filter is pushed down into the query
 * builder through the shared TrigramSearchExpression builder (not
 * post-filtered in memory), and that the emitted predicate is
 * wildcard-safe (the parameter is escaped, not the raw term).
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentRepository::class)]
final class EquipmentRepositoryTest extends TestCase
{
  #[Test]
  public function testCountByOrganizationIdPushesWildcardSafeSearchPredicateIntoSql(): void
  {
    $organizationId = EquipmentOrganizationId::fromString('550e8400-e29b-41d4-a716-446655440001');
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
    $entityManager->expects(self::once())->method('getRepository')->with(EquipmentRecord::class)->willReturn($doctrineRepository);
    $entityManager->expects(self::once())->method('getReference')->with(OrganizationRecord::class, (string) $organizationId)->willReturn(new OrganizationRecord());
    $entityManager->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);

    $repository = new EquipmentRepository(entityManager: $entityManager);

    $repository->countByOrganizationId(
      organizationId: $organizationId,
      search: '50%_off',
    );

    $searchClause = null;
    foreach ($capturedWhereClauses as $clause) {
      if (str_contains($clause, 'e.serialNumber')) {
        $searchClause = $clause;
      }
    }

    self::assertNotNull($searchClause, 'The search predicate must be pushed down into the query builder.');
    self::assertSame(
      "(LOWER(e.type) LIKE :search ESCAPE '\\' OR LOWER(e.subType) LIKE :search ESCAPE '\\' OR LOWER(e.brand) LIKE :search ESCAPE '\\' OR LOWER(e.model) LIKE :search ESCAPE '\\' OR LOWER(e.serialNumber) LIKE :search ESCAPE '\\' OR LOWER(e.status) LIKE :search ESCAPE '\\' OR LOWER(e.locationLabel) LIKE :search ESCAPE '\\')",
      $searchClause,
    );

    // The wildcard characters in the raw term are escaped in the bound
    // parameter (not interpolated as-is), matching TrigramSearchExpression.
    self::assertSame(TrigramSearchExpression::likeValue('50%_off'), $capturedParameters['search']);
    self::assertSame('%50\\%\\_off%', $capturedParameters['search']);
  }

  #[Test]
  public function testCountByOrganizationIdOmitsSearchPredicateWhenTermIsNull(): void
  {
    $organizationId = EquipmentOrganizationId::fromString('550e8400-e29b-41d4-a716-446655440002');
    $doctrineRepository = $this->createStub(EntityRepository::class);
    $query = $this->createMock(Query::class);
    $query->expects(self::once())->method('getSingleScalarResult')->willReturn('0');

    $capturedWhereClauses = [];
    $queryBuilder = $this->createMock(QueryBuilder::class);
    $queryBuilder->method('select')->willReturnSelf();
    $queryBuilder->method('from')->willReturnSelf();
    $queryBuilder->method('where')->willReturnSelf();
    $queryBuilder->method('andWhere')->willReturnCallback(function (string $expression) use (&$capturedWhereClauses, $queryBuilder): QueryBuilder {
      $capturedWhereClauses[] = $expression;

      return $queryBuilder;
    });
    $queryBuilder->method('setParameter')->willReturnSelf();
    $queryBuilder->expects(self::once())->method('getQuery')->willReturn($query);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())->method('getRepository')->with(EquipmentRecord::class)->willReturn($doctrineRepository);
    $entityManager->expects(self::once())->method('getReference')->willReturn(new OrganizationRecord());
    $entityManager->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);

    $repository = new EquipmentRepository(entityManager: $entityManager);

    $repository->countByOrganizationId(organizationId: $organizationId);

    foreach ($capturedWhereClauses as $clause) {
      self::assertStringNotContainsString('e.serialNumber', $clause);
    }
  }
}
