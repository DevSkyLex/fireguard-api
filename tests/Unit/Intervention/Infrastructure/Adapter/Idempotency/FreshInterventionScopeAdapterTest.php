<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Equipment\Application\Port\Inbound\FreshEquipmentScopePort;
use Intervention\Infrastructure\Adapter\Idempotency\FreshInterventionScopeAdapter;
use Organization\Application\Contract\Member\OrganizationMemberView;
use Organization\Application\Port\Inbound\{FreshOrganizationAuthorizationPort, FreshOrganizationMemberDirectoryPort};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;

/** Current team and assignment facts must be rechecked even when a receipt is already complete. @category Unit Test */
final class FreshInterventionScopeAdapterTest extends TestCase
{
  public function testRevokedParticipationIsReadAgainWithTheSameAdapter(): void
  {
    $participants = '["member"]';
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::exactly(2))->method('fetchAssociative')->willReturnCallback(static function () use (&$participants): array {
      return ['id' => 'intervention', 'organization_id' => 'org', 'status' => 'in_progress', 'responsible_id' => null, 'participants' => $participants];
    });
    $adapter = $this->adapter($connection);
    $adapter->assertExecutionAccess('org', 'intervention', 'user');
    $participants = '[]';
    $this->expectException(IdempotencyDeniedException::class);
    $adapter->assertExecutionAccess('org', 'intervention', 'user');
  }

  public function testInactiveMembershipDeniesRetainedDeletedWorkItemProof(): void
  {
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturn(['id' => 'intervention', 'organization_id' => 'org', 'status' => 'in_progress', 'responsible_id' => null, 'participants' => '[]']);
    $adapter = $this->adapter($connection, false);
    $this->expectException(IdempotencyDeniedException::class);
    $adapter->assertRetainedWorkItemExecutionAccess('org', 'intervention', 'user', 'member');
  }

  public function testAssignedMemberRetainsAccessAfterWorkItemDeletionWithoutJoiningParentTeam(): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::once())->method('fetchAssociative')->willReturn(['id' => 'intervention', 'organization_id' => 'org', 'status' => 'in_progress', 'responsible_id' => null, 'participants' => '[]']);
    $connection->expects(self::never())->method('fetchOne');
    $this->adapter($connection)->assertRetainedWorkItemExecutionAccess('org', 'intervention', 'user', 'member');
    self::addToAssertionCount(1);
  }

  public function testForeignEquipmentCannotUseOtherwiseAuthorizedInventoryContext(): void
  {
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturn(['id' => 'intervention', 'organization_id' => 'org', 'status' => 'published', 'responsible_id' => 'member', 'participants' => '[]']);
    $adapter = $this->adapter($connection, equipmentOrganization: 'foreign');
    $this->expectException(IdempotencyDeniedException::class);
    $adapter->assertInventoryAccess('org', 'intervention', null, 'equipment', 'user');
  }

  public function testWorkItemEquipmentTargetMustMatchTheCurrentOwnerRow(): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->method('fetchAssociative')->willReturnOnConsecutiveCalls(
      ['id' => 'intervention', 'organization_id' => 'org', 'status' => 'published', 'responsible_id' => 'member', 'participants' => '[]'],
      ['assignee_id' => 'member'],
    );
    $connection->expects(self::once())->method('fetchOne')->willReturn('/api/equipment/other-equipment');
    $this->expectException(IdempotencyDeniedException::class);
    $this->adapter($connection)->assertInventoryAccess('org', 'intervention', 'task', 'equipment', 'user');
  }

  private function adapter(Connection $connection, bool $active = true, string $equipmentOrganization = 'org'): FreshInterventionScopeAdapter
  {
    $authorization = $this->createStub(FreshOrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);
    $authorization->method('isMemberOf')->willReturn(true);
    $members = $this->createStub(FreshOrganizationMemberDirectoryPort::class);
    $members->method('findByOrganizationAndUser')->willReturn(new OrganizationMemberView('member', 'org', 'user', $active));
    $equipment = $this->createStub(FreshEquipmentScopePort::class);
    $equipment->method('organizationId')->willReturn($equipmentOrganization);

    return new FreshInterventionScopeAdapter($connection, $authorization, $members, $equipment);
  }
}
