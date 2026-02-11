<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\AddOrganizationMember;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\UseCase\Command\Organization\AddOrganizationMember\{AddOrganizationMemberCommand, AddOrganizationMemberHandler, AddOrganizationMemberResult};
use Organization\Domain\Exception\{OrganizationNotFoundException, OrganizationRoleNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationName, OrganizationRoleId, OrganizationRoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Tests\Support\Factory\UserTestFactory;
use User\Application\Port\Outbound\UserRepositoryPort;

use function count;

#[CoversClass(AddOrganizationMemberHandler::class)]
final class AddOrganizationMemberHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeCreatesMemberAndAssignsDefaultRoleWhenNoRolesProvided(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440100';
    $memberId = '550e8400-e29b-41d4-a716-446655440101';
    $userId = '550e8400-e29b-41d4-a716-446655440102';
    $defaultRoleId = '550e8400-e29b-41d4-a716-446655440103';

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard Paris'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $defaultRole = OrganizationRole::reconstitute(
      id: new OrganizationRoleId($defaultRoleId),
      organizationId: new OrganizationId($organizationId),
      name: new OrganizationRoleName('member'),
      permissions: ['organization.read'],
      isSystem: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findById')
      ->willReturn(UserTestFactory::createActive($userId));

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findByOrganizationAndUser')
      ->willReturn(null);
    $memberRepository->expects(self::once())
      ->method('save')
      ->with(self::isInstanceOf(OrganizationMember::class));
    $memberRepository->expects(self::once())
      ->method('assignRole')
      ->with(
        self::callback(static fn (OrganizationMemberId $id): bool => $memberId === (string) $id),
        self::callback(static fn (OrganizationRoleId $id): bool => $defaultRoleId === (string) $id),
      );
    $memberRepository->expects(self::once())
      ->method('findRoleIdsForMember')
      ->willReturn([$defaultRoleId]);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByOrganizationAndName')
      ->with(
        self::callback(static fn (OrganizationId $id): bool => $organizationId === (string) $id),
        self::callback(static fn (OrganizationRoleName $name): bool => 'member' === (string) $name),
      )
      ->willReturn($defaultRole);
    $roleRepository->expects(self::once())
      ->method('findByIdsInOrganization')
      ->willReturn([$defaultRole]);

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(OrganizationMemberId::class)
      ->willReturn(new OrganizationMemberId($memberId));

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    $handler = new AddOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      userRepository: $userRepository,
      uuidFactory: $uuidFactory,
      transactionManager: $transactionManager,
    );

    $result = $handler->__invoke(new AddOrganizationMemberCommand(
      organizationId: $organizationId,
      userId: $userId,
      roleIds: [],
    ));

    self::assertInstanceOf(AddOrganizationMemberResult::class, $result);
    self::assertSame($memberId, $result->memberId);
    self::assertSame($organizationId, $result->organizationId);
    self::assertSame($userId, $result->userId);
    self::assertSame([$defaultRoleId], $result->roleIds);
    self::assertTrue($result->isActive);
    self::assertInstanceOf(DateTimeImmutable::class, $result->joinedAt);
  }

  #[Test]
  public function testInvokeReactivatesExistingInactiveMemberBeforeAssigningRoles(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440810';
    $memberId = '550e8400-e29b-41d4-a716-446655440811';
    $userId = '550e8400-e29b-41d4-a716-446655440812';
    $roleId = '550e8400-e29b-41d4-a716-446655440813';

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard Paris'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $inactiveMember = OrganizationMember::reconstitute(
      id: new OrganizationMemberId($memberId),
      organizationId: new OrganizationId($organizationId),
      userId: $userId,
      isActive: false,
      joinedAt: new DateTimeImmutable('-5 days'),
    );

    $role = OrganizationRole::reconstitute(
      id: new OrganizationRoleId($roleId),
      organizationId: new OrganizationId($organizationId),
      name: new OrganizationRoleName('technician'),
      permissions: ['organization.read'],
      isSystem: false,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findById')
      ->willReturn(UserTestFactory::createActive($userId));

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findByOrganizationAndUser')
      ->willReturn($inactiveMember);
    $memberRepository->expects(self::once())
      ->method('save')
      ->with(self::callback(static fn (OrganizationMember $member): bool => $member->isActive()));
    $memberRepository->expects(self::once())
      ->method('assignRole')
      ->with(
        self::callback(static fn (OrganizationMemberId $id): bool => $memberId === (string) $id),
        self::callback(static fn (OrganizationRoleId $id): bool => $roleId === (string) $id),
      );
    $memberRepository->expects(self::once())
      ->method('findRoleIdsForMember')
      ->willReturn([$roleId]);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByOrganizationAndName');
    $roleRepository->expects(self::once())
      ->method('findByIdsInOrganization')
      ->willReturn([$role]);

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    $handler = new AddOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      userRepository: $userRepository,
      uuidFactory: $this->createMock(UuidFactory::class),
      transactionManager: $transactionManager,
    );

    $result = $handler->__invoke(new AddOrganizationMemberCommand(
      organizationId: $organizationId,
      userId: $userId,
      roleIds: [$roleId],
    ));

    self::assertTrue($result->isActive);
    self::assertSame($userId, $result->userId);
    self::assertSame([$roleId], $result->roleIds);
  }

  #[Test]
  public function testInvokeDeduplicatesRoleIdsBeforeLookupAndAssignment(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440500';
    $memberId = '550e8400-e29b-41d4-a716-446655440501';
    $userId = '550e8400-e29b-41d4-a716-446655440502';
    $roleId = '550e8400-e29b-41d4-a716-446655440503';

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard Lille'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $member = OrganizationMember::reconstitute(
      id: new OrganizationMemberId($memberId),
      organizationId: new OrganizationId($organizationId),
      userId: $userId,
      isActive: true,
      joinedAt: new DateTimeImmutable('-1 day'),
    );

    $role = OrganizationRole::reconstitute(
      id: new OrganizationRoleId($roleId),
      organizationId: new OrganizationId($organizationId),
      name: new OrganizationRoleName('technician'),
      permissions: ['organization.read'],
      isSystem: false,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findById')
      ->willReturn(UserTestFactory::createActive($userId));

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findByOrganizationAndUser')
      ->willReturn($member);
    $memberRepository->expects(self::never())->method('save');
    $memberRepository->expects(self::once())
      ->method('assignRole')
      ->with(
        self::callback(static fn (OrganizationMemberId $id): bool => $memberId === (string) $id),
        self::callback(static fn (OrganizationRoleId $id): bool => $roleId === (string) $id),
      );
    $memberRepository->expects(self::once())
      ->method('findRoleIdsForMember')
      ->willReturn([$roleId]);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByOrganizationAndName');
    $roleRepository->expects(self::once())
      ->method('findByIdsInOrganization')
      ->with(
        self::isInstanceOf(OrganizationId::class),
        self::callback(static function (array $roleIds) use ($roleId): bool {
          return 1 === count($roleIds)
            && $roleIds[0] instanceof OrganizationRoleId
            && $roleId === (string) $roleIds[0];
        }),
      )
      ->willReturn([$role]);

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    $handler = new AddOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      userRepository: $userRepository,
      uuidFactory: $this->createMock(UuidFactory::class),
      transactionManager: $transactionManager,
    );

    $result = $handler->__invoke(new AddOrganizationMemberCommand(
      organizationId: $organizationId,
      userId: $userId,
      roleIds: [$roleId, $roleId],
    ));

    self::assertSame([$roleId], $result->roleIds);
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationDoesNotExist(): void
  {
    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::never())->method('transactional');

    $handler = new AddOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $this->createMock(OrganizationMemberRepositoryPort::class),
      roleRepository: $this->createMock(OrganizationRoleRepositoryPort::class),
      userRepository: $this->createMock(UserRepositoryPort::class),
      uuidFactory: $this->createMock(UuidFactory::class),
      transactionManager: $transactionManager,
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new AddOrganizationMemberCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440100',
      userId: '550e8400-e29b-41d4-a716-446655440102',
      roleIds: [],
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenOneRequestedRoleIsMissing(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440100';
    $userId = '550e8400-e29b-41d4-a716-446655440102';

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard Paris'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findById')
      ->willReturn(UserTestFactory::createActive($userId));

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())->method('assignRole');

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByIdsInOrganization')
      ->willReturn([]);

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::never())->method('transactional');

    $handler = new AddOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      userRepository: $userRepository,
      uuidFactory: $this->createMock(UuidFactory::class),
      transactionManager: $transactionManager,
    );

    $this->expectException(OrganizationRoleNotFoundException::class);

    $handler->__invoke(new AddOrganizationMemberCommand(
      organizationId: $organizationId,
      userId: $userId,
      roleIds: ['550e8400-e29b-41d4-a716-446655440300'],
    ));
  }
}
