<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Infrastructure\Persistence\Doctrine\Repository;

use Authorization\Application\Service\AuthorizationCacheInvalidator;
use Authorization\Domain\Model\RoleAssignment\RoleAssignment;
use Authorization\Domain\ValueObject\{RoleAssignmentId, RoleId, SubjectType};
use Authorization\Infrastructure\Persistence\Doctrine\Mapper\{PermissionMapper, RoleAssignmentMapper, RoleMapper};
use Authorization\Infrastructure\Persistence\Doctrine\Record\{RoleAssignmentRecord, RoleRecord};
use Authorization\Infrastructure\Persistence\Doctrine\Repository\RoleAssignmentRepository;
use Doctrine\ORM\{EntityManagerInterface, Query, QueryBuilder};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\CachePort;

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

  #[Test]
  public function testSaveInvalidatesUserAuthorizationCache(): void
  {
    $assignment = RoleAssignment::assignToUser(
      id: new RoleAssignmentId('550e8400-e29b-41d4-a716-446655440001'),
      roleId: new RoleId('550e8400-e29b-41d4-a716-446655440002'),
      userId: '550e8400-e29b-41d4-a716-446655440003',
    );

    $roleRecord = new RoleRecord();
    $roleRecord->id = $assignment->roleId()->value;

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('find')
      ->with(RoleAssignmentRecord::class, $assignment->id()->value)
      ->willReturn(null);
    $entityManager->expects(self::once())
      ->method('getReference')
      ->with(RoleRecord::class, $assignment->roleId()->value)
      ->willReturn($roleRecord);
    $entityManager->expects(self::once())
      ->method('persist')
      ->with(self::isInstanceOf(RoleAssignmentRecord::class));
    $entityManager->expects(self::once())->method('flush');

    /** @var CachePort&MockObject $cache */
    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::exactly(2))
      ->method('delete')
      ->willReturnCallback(static function (string $key): void {
        self::assertContains($key, [
          'authz.roles.550e8400-e29b-41d4-a716-446655440003',
          'authz.permissions.550e8400-e29b-41d4-a716-446655440003',
        ]);
      });

    $repository = new RoleAssignmentRepository(
      entityManager: $entityManager,
      mapper: new RoleAssignmentMapper(),
      roleMapper: new RoleMapper(new PermissionMapper()),
      cacheInvalidator: new AuthorizationCacheInvalidator($cache),
    );

    $repository->save($assignment);
  }
  // #endregion
}
