<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\Adapter\Inventory;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Port\Inbound\InterventionInventoryContextPort;
use Intervention\Domain\Exception\InterventionAccessDeniedException;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionRecord, InterventionWorkItemRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** Class InterventionInventoryContextAdapterTest. Stock facts retain the canonical assigned/team execution policy. @category IntegrationTest */
final class InterventionInventoryContextAdapterTest extends KernelTestCase
{
  private const string ORG = '970e8400-e29b-41d4-a716-449140000001';

  private const string ORDER = '970e8400-e29b-41d4-a716-449140000002';

  private const string TASK = '970e8400-e29b-41d4-a716-449140000003';

  private const string RESPONSIBLE = '970e8400-e29b-41d4-a716-449140000011';

  private const string ASSIGNED = '970e8400-e29b-41d4-a716-449140000012';

  private const string OUTSIDE_TEAM = '970e8400-e29b-41d4-a716-449140000013';

  private const string RESPONSIBLE_USER = '970e8400-e29b-41d4-a716-449140000021';

  private const string ASSIGNED_USER = '970e8400-e29b-41d4-a716-449140000022';

  private const string OUTSIDE_USER = '970e8400-e29b-41d4-a716-449140000023';

  public function testExecutionPermissionAloneCannotDeclareStockOutsideTheInterventionTeam(): void
  {
    [$manager, $context] = $this->fixture(null);
    $this->expectException(InterventionAccessDeniedException::class);
    $manager->getConnection()->transactional(fn () => $context->validate(self::ORG, self::ORDER, null, null, self::OUTSIDE_USER));
  }

  public function testAResponsibleMemberCannotConsumeOnAnotherMembersAssignedTask(): void
  {
    [$manager, $context] = $this->fixture(self::ASSIGNED);
    $this->expectException(InterventionAccessDeniedException::class);
    $manager->getConnection()->transactional(fn () => $context->validate(self::ORG, self::ORDER, self::TASK, null, self::RESPONSIBLE_USER));
  }

  public function testTheAssignedMemberMayDeclareALatePhysicalFactWithoutMutatingPublishedWork(): void
  {
    [$manager, $context] = $this->fixture(self::ASSIGNED, true);
    $before = $manager->getConnection()->fetchAssociative('SELECT status, revision, closure_snapshot FROM interventions WHERE id = ?', [self::ORDER]);
    $result = $manager->getConnection()->transactional(fn () => $context->validate(self::ORG, self::ORDER, self::TASK, null, self::ASSIGNED_USER));
    self::assertTrue($result->published);
    self::assertSame($before, $manager->getConnection()->fetchAssociative('SELECT status, revision, closure_snapshot FROM interventions WHERE id = ?', [self::ORDER]));
  }

  public function testAnActiveParticipantMayDeclareStockForAnUnassignedTask(): void
  {
    [$manager, $context] = $this->fixture(null);
    $result = $manager->getConnection()->transactional(fn () => $context->validate(self::ORG, self::ORDER, self::TASK, null, self::ASSIGNED_USER));
    self::assertFalse($result->published);
  }

  public function testReadOnlyExistenceChecksKeepExplicitInventoryListsInsideTheirOrganization(): void
  {
    [, $context] = $this->fixture(null);
    self::assertTrue($context->existsInOrganization(self::ORG, self::ORDER));
    self::assertFalse($context->existsInOrganization('970e8400-e29b-41d4-a716-449140000099', self::ORDER));
    self::assertFalse($context->existsInOrganization(self::ORG, '970e8400-e29b-41d4-a716-449140000098'));
  }

  /**
   * @return array{EntityManagerInterface,InterventionInventoryContextPort}
   */
  private function fixture(?string $assignee, bool $published = false): array
  {
    self::bootKernel();
    /** @var EntityManagerInterface $manager */
    $manager = self::getContainer()->get('doctrine.orm.main_entity_manager');
    $now = new DateTimeImmutable('2026-10-01T10:00:00Z');
    $org = new OrganizationRecord();
    $org->id = self::ORG;
    $org->name = 'Stock execution scope';
    $org->slug = 'stock-execution-scope';
    $org->ownerUserId = self::RESPONSIBLE_USER;
    $org->createdByUserId = self::RESPONSIBLE_USER;
    $org->status = 'active';
    $org->isActive = true;
    $org->createdAt = $org->updatedAt = $now;
    $manager->persist($org);
    $role = new OrganizationRoleRecord();
    $role->id = '970e8400-e29b-41d4-a716-449140000031';
    $role->organization = $org;
    $role->name = 'stock_executor';
    $role->permissions = ['organization.interventions.execute'];
    $role->isSystem = false;
    $role->createdAt = $now;
    $manager->persist($role);
    foreach ([self::RESPONSIBLE => self::RESPONSIBLE_USER, self::ASSIGNED => self::ASSIGNED_USER, self::OUTSIDE_TEAM => self::OUTSIDE_USER] as $memberId => $userId) {
      $member = new OrganizationMemberRecord();
      $member->id = $memberId;
      $member->organization = $org;
      $member->userId = $userId;
      $member->isActive = true;
      $member->joinedAt = $now;
      $manager->persist($member);
      $assignment = new OrganizationMemberRoleRecord();
      $assignment->member = $member;
      $assignment->role = $role;
      $assignment->assignedAt = $now;
      $manager->persist($assignment);
    }
    $order = new InterventionRecord();
    $order->id = self::ORDER;
    $order->organization = $org;
    $order->number = 1;
    $order->type = 'corrective_maintenance';
    $order->name = 'Actual stock declarations';
    $order->status = $published ? 'published' : 'in_progress';
    $order->responsibleId = self::RESPONSIBLE;
    $order->participants = [self::ASSIGNED];
    $order->closureSnapshot = $published ? ['version' => 1, 'identity' => 'original'] : null;
    $order->createdAt = $order->updatedAt = $now;
    $manager->persist($order);
    $task = new InterventionWorkItemRecord();
    $task->id = self::TASK;
    $task->intervention = $order;
    $task->action = 'repair';
    $task->status = $published ? 'completed' : 'in_progress';
    $task->source = 'planned';
    $task->assigneeId = $assignee;
    $task->createdAt = $task->updatedAt = $now;
    $manager->persist($task);
    $manager->flush();
    /** @var InterventionInventoryContextPort $context */
    $context = self::getContainer()->get(InterventionInventoryContextPort::class);

    return [$manager, $context];
  }
}
