<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Factory;

use DateTimeImmutable;
use Equipment\Application\UseCase\Query\Equipment\GetEquipment\{GetEquipmentQuery, GetEquipmentResult};
use Facility\Application\UseCase\Query\Facility\GetFacility\{GetFacilityQuery, GetFacilityResult};
use Intervention\Application\Contract\Workflow\InterventionWorkflowView;
use Intervention\Presentation\Api\Factory\InterventionWorkItemOutputFactory;
use Organization\Application\UseCase\Query\Organization\GetOrganizationMember\{GetOrganizationMemberQuery, GetOrganizationMemberResult};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Message\{QueryMessage, ResultMessage};
use Shared\Application\Port\Inbound\QueryBusPort;
use User\Application\Contract\User\UserView;
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};

/**
 * Test InterventionWorkItemOutputFactoryTest.
 *
 * @category Factory Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionWorkItemOutputFactory::class)]
final class InterventionWorkItemOutputFactoryTest extends TestCase
{
  private const string ASSIGNEE_IRI = '/api/organizations/org-1/members/member-1';

  // #region Methods
  #[Test]
  public function testResolvesAssigneeIdentityFromMemberAndUser(): void
  {
    $factory = new InterventionWorkItemOutputFactory($this->queryBus(
      member: $this->memberResult('member-1', 'user-1'),
      user: $this->userResult('user-1', 'Jane', 'Doe', 'https://cdn/avatar/256.webp'),
    ));

    $output = $factory->fromView($this->view(self::ASSIGNEE_IRI));

    self::assertSame(self::ASSIGNEE_IRI, $output->assignee);
    self::assertNotNull($output->assigneeProfile);
    self::assertSame(self::ASSIGNEE_IRI, $output->assigneeProfile->member);
    self::assertSame('user-1', $output->assigneeProfile->userId);
    self::assertSame('Jane Doe', $output->assigneeProfile->displayName);
    self::assertSame('https://cdn/avatar/256.webp', $output->assigneeProfile->avatarUrl);
  }

  #[Test]
  public function testUnassignedWorkItemHasNoProfile(): void
  {
    $factory = new InterventionWorkItemOutputFactory($this->queryBus());

    $output = $factory->fromView($this->view(null));

    self::assertNull($output->assignee);
    self::assertNull($output->assigneeProfile);
  }

  #[Test]
  public function testUnresolvableMemberYieldsNullProfileWithoutFailing(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new RuntimeException('member gone'));

    $factory = new InterventionWorkItemOutputFactory($queryBus);

    $output = $factory->fromView($this->view(self::ASSIGNEE_IRI));

    self::assertSame(self::ASSIGNEE_IRI, $output->assignee);
    self::assertNull($output->assigneeProfile);
  }

  #[Test]
  public function testFallsBackToMemberUserIdWhenUserUnresolved(): void
  {
    $factory = new InterventionWorkItemOutputFactory($this->queryBus(
      member: $this->memberResult('member-1', 'user-1'),
      user: new GetUserResult(null),
    ));

    $output = $factory->fromView($this->view(self::ASSIGNEE_IRI));

    self::assertNotNull($output->assigneeProfile);
    self::assertSame('user-1', $output->assigneeProfile->displayName);
    self::assertNull($output->assigneeProfile->avatarUrl);
  }

  #[Test]
  public function testResolvesMemberAndUserOnlyOnceAcrossSharedAssignees(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::exactly(2))
      ->method('ask')
      ->willReturnCallback(fn (QueryMessage $query): ResultMessage => match (true) {
        $query instanceof GetOrganizationMemberQuery => $this->memberResult('member-1', 'user-1'),
        $query instanceof GetUserQuery => $this->userResult('user-1', 'Jane', 'Doe', null),
        default => throw new RuntimeException('unexpected query'),
      });

    $factory = new InterventionWorkItemOutputFactory($queryBus);

    $first = $factory->fromView($this->view(self::ASSIGNEE_IRI));
    $second = $factory->fromView($this->view(self::ASSIGNEE_IRI));

    self::assertSame('Jane Doe', $first->assigneeProfile?->displayName);
    self::assertSame('Jane Doe', $second->assigneeProfile?->displayName);
  }

  #[Test]
  public function testResolvesFacilityTarget(): void
  {
    $target = '/api/facilities/fac-1';
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturnCallback(
      fn (QueryMessage $query): ResultMessage => $query instanceof GetFacilityQuery
        ? $this->facilityResult('fac-1', 'Boiler Room')
        : throw new RuntimeException('unexpected query'),
    );

    $output = new InterventionWorkItemOutputFactory($queryBus)
      ->fromView($this->view(null, ['target' => $target]));

    self::assertNotNull($output->targetSummary);
    self::assertSame($target, $output->targetSummary->resource);
    self::assertSame('facility', $output->targetSummary->kind);
    self::assertSame('Boiler Room', $output->targetSummary->label);
  }

  #[Test]
  public function testResolvesEquipmentTargetWithComposedLabel(): void
  {
    $target = '/api/equipment/eq-1';
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturnCallback(
      fn (QueryMessage $query): ResultMessage => $query instanceof GetEquipmentQuery
        ? $this->equipmentResult('eq-1', 'extinguisher', 'SN-42')
        : throw new RuntimeException('unexpected query'),
    );

    $output = new InterventionWorkItemOutputFactory($queryBus)
      ->fromView($this->view(null, ['target' => $target]));

    self::assertNotNull($output->targetSummary);
    self::assertSame('equipment', $output->targetSummary->kind);
    self::assertSame('extinguisher · SN-42', $output->targetSummary->label);
  }

  #[Test]
  public function testFreeTextTargetHasNoSummary(): void
  {
    $factory = new InterventionWorkItemOutputFactory($this->queryBus());

    $output = $factory->fromView($this->view(null, ['target' => 'Check the back door']));

    self::assertSame('Check the back door', $output->target);
    self::assertNull($output->targetSummary);
  }

  #[Test]
  public function testUnresolvableTargetYieldsNullSummaryWithoutFailing(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new RuntimeException('facility gone'));

    $output = new InterventionWorkItemOutputFactory($queryBus)
      ->fromView($this->view(null, ['target' => '/api/facilities/fac-1']));

    self::assertSame('/api/facilities/fac-1', $output->target);
    self::assertNull($output->targetSummary);
  }

  #[Test]
  public function testFreeTextAssigneeYieldsNoProfile(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $output = new InterventionWorkItemOutputFactory($queryBus)->fromView($this->view('Someone on site'));

    self::assertSame('Someone on site', $output->assignee);
    self::assertNull($output->assigneeProfile);
  }

  #[Test]
  public function testFallsBackToTheUsernameWhenTheUserHasNoName(): void
  {
    $factory = new InterventionWorkItemOutputFactory($this->queryBus(
      member: $this->memberResult('member-1', 'user-1'),
      user: $this->userResult('user-1', '', '', null),
    ));

    $output = $factory->fromView($this->view(self::ASSIGNEE_IRI));

    self::assertSame('jdoe', $output->assigneeProfile?->displayName);
  }

  #[Test]
  public function testFallsBackToTheMemberUserIdWhenTheUserQueryFails(): void
  {
    $factory = new InterventionWorkItemOutputFactory($this->queryBus(
      member: $this->memberResult('member-1', 'user-1'),
    ));

    $output = $factory->fromView($this->view(self::ASSIGNEE_IRI));

    self::assertNotNull($output->assigneeProfile);
    self::assertSame('user-1', $output->assigneeProfile->displayName);
    self::assertNull($output->assigneeProfile->avatarUrl);
  }

  #[Test]
  public function testResolvesTheSameTargetOnlyOnce(): void
  {
    $target = '/api/facilities/fac-1';
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturnCallback(fn (QueryMessage $query): ResultMessage => $query instanceof GetFacilityQuery
        ? $this->facilityResult('fac-1', 'Boiler Room')
        : throw new RuntimeException('unexpected query'));

    $factory = new InterventionWorkItemOutputFactory($queryBus);

    $first = $factory->fromView($this->view(null, ['target' => $target]));
    $second = $factory->fromView($this->view(null, ['target' => $target]));

    self::assertSame('Boiler Room', $first->targetSummary?->label);
    self::assertSame('Boiler Room', $second->targetSummary?->label);
  }

  // #region Helpers
  /**
   * @param array<string, mixed> $extra
   */
  private function view(?string $assignee, array $extra = []): InterventionWorkflowView
  {
    return new InterventionWorkflowView(
      resource: 'work_item',
      organizationId: 'org-1',
      data: [
        'id' => 'wi-1',
        'intervention' => '/api/interventions/int-1',
        'action' => 'inspect',
        'target' => null,
        'resultResource' => null,
        'assignee' => $assignee,
        'source' => 'planned',
        'status' => 'planned',
        'required' => true,
        'skipReason' => null,
        'evidenceCount' => 0,
        'revision' => 1,
        'createdAt' => '2026-01-01T00:00:00+00:00',
        'updatedAt' => '2026-01-01T00:00:00+00:00',
        ...$extra,
      ],
    );
  }

  private function queryBus(?GetOrganizationMemberResult $member = null, ?GetUserResult $user = null): QueryBusPort
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturnCallback(
      fn (QueryMessage $query): ResultMessage => match (true) {
        $query instanceof GetOrganizationMemberQuery && null !== $member => $member,
        $query instanceof GetUserQuery && null !== $user => $user,
        default => throw new RuntimeException('unexpected query'),
      },
    );

    return $queryBus;
  }

  private function memberResult(string $memberId, string $userId): GetOrganizationMemberResult
  {
    return new GetOrganizationMemberResult(
      id: $memberId,
      organizationId: 'org-1',
      userId: $userId,
      isActive: true,
      joinedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }

  private function userResult(string $id, string $firstName, string $lastName, ?string $avatarUrl): GetUserResult
  {
    return new GetUserResult(new UserView(
      id: $id,
      username: 'jdoe',
      email: 'jdoe@example.com',
      firstName: $firstName,
      lastName: $lastName,
      avatarUrl: $avatarUrl,
      status: 'active',
      emailVerified: true,
      tenantId: null,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      lastLoginAt: null,
      canLogin: true,
    ));
  }

  private function facilityResult(string $facilityId, string $name): GetFacilityResult
  {
    return new GetFacilityResult(
      facilityId: $facilityId,
      organizationId: 'org-1',
      parentFacilityId: null,
      type: 'building',
      name: $name,
      code: null,
      status: 'active',
      address: null,
      metadata: [],
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }

  private function equipmentResult(string $equipmentId, string $type, ?string $serialNumber): GetEquipmentResult
  {
    return new GetEquipmentResult(
      equipmentId: $equipmentId,
      organizationId: 'org-1',
      facilityId: null,
      type: $type,
      subType: null,
      brand: null,
      model: null,
      serialNumber: $serialNumber,
      locationLabel: null,
      status: 'active',
      installedAt: null,
      commissionedAt: null,
      tags: [],
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }
  // #endregion
}
