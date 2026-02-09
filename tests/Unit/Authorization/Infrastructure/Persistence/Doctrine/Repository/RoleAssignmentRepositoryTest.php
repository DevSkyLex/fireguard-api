<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Infrastructure\Persistence\Doctrine\Repository;

use Authorization\Domain\ValueObject\SubjectType;
use Authorization\Infrastructure\Persistence\Doctrine\Mapper\{PermissionMapper, RoleAssignmentMapper, RoleMapper};
use Authorization\Infrastructure\Persistence\Doctrine\Repository\RoleAssignmentRepository;
use Doctrine\ORM\{EntityManagerInterface, Query, QueryBuilder};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test RoleAssignmentRepositoryTest.
 *
 * @category Repository Tests
 */
#[CoversClass(className: RoleAssignmentRepository::class)]
final class RoleAssignmentRepositoryTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testFindBySubjectReturnsEmptyWhenQueryResultNotArray(): void
  {
    $query = $this->createMock(Query::class);
    $query->expects(self::once())
      ->method('getResult')
      ->willReturn('not-array');

    $qb = $this->createMock(QueryBuilder::class);
    $qb->method('select')->willReturnSelf();
    $qb->method('from')->willReturnSelf();
    $qb->method('where')->willReturnSelf();
    $qb->method('andWhere')->willReturnSelf();
    $qb->method('setParameter')->willReturnSelf();
    $qb->expects(self::once())
      ->method('getQuery')
      ->willReturn($query);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('createQueryBuilder')
      ->willReturn($qb);

    $repository = new RoleAssignmentRepository(
      entityManager: $entityManager,
      mapper: new RoleAssignmentMapper(),
      roleMapper: new RoleMapper(new PermissionMapper()),
    );

    $result = $repository->findBySubject(SubjectType::USER, 'user-1');

    self::assertSame([], $result);
  }
  // #endregion
}
