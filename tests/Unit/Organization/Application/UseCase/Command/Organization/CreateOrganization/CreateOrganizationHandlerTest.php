<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\CreateOrganization;

use Doctrine\DBAL\Driver\Exception as DoctrineDriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use InvalidArgumentException;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\UseCase\Command\Organization\CreateOrganization\{CreateOrganizationCommand, CreateOrganizationHandler, CreateOrganizationResult};
use Organization\Domain\Exception\OrganizationSlugAlreadyExistsException;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationRoleId};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Shared\Infrastructure\Exception\TransactionExecutionException;
use Tests\Support\Factory\UserTestFactory;
use User\Application\Port\Outbound\UserRepositoryPort;

#[CoversClass(CreateOrganizationHandler::class)]
final class CreateOrganizationHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeCreatesOrganizationOwnerMemberAndSystemRoles(): void
  {
    $ownerUserId = '550e8400-e29b-41d4-a716-446655440001';
    $organizationId = '550e8400-e29b-41d4-a716-446655440010';
    $ownerRoleId = '550e8400-e29b-41d4-a716-446655440011';
    $memberRoleId = '550e8400-e29b-41d4-a716-446655440012';
    $ownerMemberId = '550e8400-e29b-41d4-a716-446655440013';

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findById')
      ->willReturn(UserTestFactory::createActive($ownerUserId));

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::exactly(4))
      ->method('create')
      ->willReturnOnConsecutiveCalls(
        new OrganizationId($organizationId),
        new OrganizationRoleId($ownerRoleId),
        new OrganizationRoleId($memberRoleId),
        new OrganizationMemberId($ownerMemberId),
      );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('save')
      ->with(self::callback(static function (Organization $organization) use ($organizationId, $ownerUserId): bool {
        return $organizationId === (string) $organization->id()
          && 'Fireguard Lyon' === (string) $organization->name()
          && $ownerUserId === $organization->createdByUserId()
          && true === $organization->isActive();
      }));

    $savedRoles = [];
    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::exactly(2))
      ->method('save')
      ->with(self::callback(static function (OrganizationRole $role) use (&$savedRoles): bool {
        $savedRoles[] = [
          'id' => (string) $role->id(),
          'name' => (string) $role->name(),
          'permissions' => $role->permissions(),
          'isSystem' => $role->isSystem(),
        ];

        return true;
      }));

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('save')
      ->with(self::callback(static function (OrganizationMember $member) use ($ownerMemberId, $organizationId, $ownerUserId): bool {
        return $ownerMemberId === (string) $member->id()
          && $organizationId === (string) $member->organizationId()
          && $ownerUserId === $member->userId();
      }));
    $memberRepository->expects(self::once())
      ->method('assignRole')
      ->with(
        self::callback(static fn (OrganizationMemberId $id): bool => $ownerMemberId === (string) $id),
        self::callback(static fn (OrganizationRoleId $id): bool => $ownerRoleId === (string) $id),
      );

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    $handler = new CreateOrganizationHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      memberRepository: $memberRepository,
      userRepository: $userRepository,
      uuidFactory: $uuidFactory,
      transactionManager: $transactionManager,
    );

    $result = $handler->__invoke(new CreateOrganizationCommand(
      name: 'Fireguard Lyon',
      ownerUserId: $ownerUserId,
    ));

    self::assertInstanceOf(CreateOrganizationResult::class, $result);
    self::assertSame($organizationId, $result->organizationId);
    self::assertSame($ownerMemberId, $result->ownerMemberId);
    self::assertSame($ownerRoleId, $result->ownerRoleId);

    self::assertCount(2, $savedRoles);
    self::assertSame([$ownerRoleId, $memberRoleId], [$savedRoles[0]['id'], $savedRoles[1]['id']]);
    self::assertSame('admin', $savedRoles[0]['name']);
    self::assertSame(['organization.*'], $savedRoles[0]['permissions']);
    self::assertTrue($savedRoles[0]['isSystem']);
    self::assertSame('member', $savedRoles[1]['name']);
    self::assertSame(['organization.read', 'organization.dashboard.read', 'organization.members.read', 'organization.roles.read', 'organization.facilities.read', 'organization.equipment.read', 'organization.inspection.read'], $savedRoles[1]['permissions']);
    self::assertTrue($savedRoles[1]['isSystem']);
  }

  #[Test]
  public function testInvokeThrowsWhenOwnerUserDoesNotExist(): void
  {
    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::never())->method('save');

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('save');

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())->method('save');

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::never())->method('transactional');

    $handler = new CreateOrganizationHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      memberRepository: $memberRepository,
      userRepository: $userRepository,
      uuidFactory: $this->createStub(UuidFactory::class),
      transactionManager: $transactionManager,
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Owner user not found.');

    $handler->__invoke(new CreateOrganizationCommand(
      name: 'Fireguard Marseille',
      ownerUserId: '550e8400-e29b-41d4-a716-446655440001',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenSlugAlreadyExists(): void
  {
    $ownerUserId = '550e8400-e29b-41d4-a716-446655440001';

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findById')
      ->willReturn(UserTestFactory::createActive($ownerUserId));

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::never())->method('save');

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('save');

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())->method('save');
    $memberRepository->expects(self::never())->method('assignRole');

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::exactly(4))
      ->method('create')
      ->willReturnOnConsecutiveCalls(
        new OrganizationId('550e8400-e29b-41d4-a716-446655440010'),
        new OrganizationRoleId('550e8400-e29b-41d4-a716-446655440011'),
        new OrganizationRoleId('550e8400-e29b-41d4-a716-446655440012'),
        new OrganizationMemberId('550e8400-e29b-41d4-a716-446655440013'),
      );

    $driverException = new class ('SQLSTATE[23505]: Unique violation: duplicate key value violates unique constraint "uniq_organization_slug"') extends RuntimeException implements DoctrineDriverException {
      public function getSQLState(): string
      {
        return '23505';
      }
    };

    $uniqueViolation = new UniqueConstraintViolationException($driverException, null);

    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willThrowException(TransactionExecutionException::wrap($uniqueViolation));

    $handler = new CreateOrganizationHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      memberRepository: $memberRepository,
      userRepository: $userRepository,
      uuidFactory: $uuidFactory,
      transactionManager: $transactionManager,
    );

    $this->expectException(OrganizationSlugAlreadyExistsException::class);
    $this->expectExceptionMessage('Organization slug "fireguard-paris" already exists.');

    $handler->__invoke(new CreateOrganizationCommand(
      name: 'Fireguard Paris',
      ownerUserId: $ownerUserId,
      slug: 'fireguard-paris',
    ));
  }
}
