<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\Service;

use DateTimeImmutable;
use Intervention\Application\Contract\Resource\{InterventionAssignmentContext, InterventionResourceAssignment};
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Application\Service\{InterventionMemberPolicy, InterventionResourceManager};
use Intervention\Domain\Exception\{
  ClientResourceAlreadyExistsException,
  InterventionConflictException,
  InterventionNotFoundException,
  InterventionResourceNotFoundException
};
use Intervention\Domain\ValueObject\InterventionResourceType;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(InterventionResourceManager::class)]
final class InterventionResourceManagerTest extends TestCase
{
  private const INTERVENTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c10';

  private const ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c11';

  private const USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c12';

  private const MEMBER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c13';

  private const RESOURCE_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c14';

  #[Test]
  public function testRejectsAnAlreadySynchronizedOfflineCreation(): void
  {
    $resources = $this->createMock(InterventionResourceGatewayPort::class);
    $resources->expects(self::once())->method('clientIdExists')
      ->with(InterventionResourceType::EQUIPMENT, '550e8400-e29b-41d4-a716-446655440001')
      ->willReturn(true);

    $manager = new InterventionResourceManager($resources);

    $this->expectException(ClientResourceAlreadyExistsException::class);
    $this->expectExceptionMessage('A resource with this client identifier already exists.');

    $manager->assertOfflineCreate(
      InterventionResourceType::EQUIPMENT,
      '550e8400-e29b-41d4-a716-446655440001',
    );
  }

  #[Test]
  public function testRequiresPlanningPermissionForDraftInterventionResources(): void
  {
    self::assertSame(
      'organization.interventions.plan',
      $this->managerWithInterventionStatus('draft')->mutationPermission(
        self::INTERVENTION_ID,
      ),
    );
  }

  #[Test]
  public function testRequiresExecutionPermissionForPlannedInterventionResources(): void
  {
    self::assertSame(
      'organization.interventions.execute',
      $this->managerWithInterventionStatus('in_progress')->mutationPermission(
        self::INTERVENTION_ID,
      ),
    );
  }

  #[Test]
  public function testRejectsMutationAfterInterventionSubmission(): void
  {
    $this->expectException(InterventionConflictException::class);
    $this->expectExceptionMessage('Intervention resources are immutable in the current state.');

    $this->managerWithInterventionStatus('submitted')->mutationPermission(
      self::INTERVENTION_ID,
    );
  }

  #[Test]
  public function testChecksInterventionMembershipBeforeExecutionMutation(): void
  {
    $resources = $this->createMock(InterventionResourceGatewayPort::class);
    $resources->expects(self::once())->method('interventionMutationContext')
      ->with(self::INTERVENTION_ID)
      ->willReturn(new InterventionAssignmentContext(
        self::INTERVENTION_ID,
        self::ORGANIZATION_ID,
        'in_progress',
        'responsible-id',
        [self::MEMBER_ID],
      ));
    $members = $this->createMock(OrganizationMemberRepositoryPort::class);
    $members->expects(self::once())->method('findByOrganizationAndUser')
      ->with(OrganizationId::fromString(self::ORGANIZATION_ID), self::USER_ID)
      ->willReturn(OrganizationMember::reconstitute(
        OrganizationMemberId::fromString(self::MEMBER_ID),
        OrganizationId::fromString(self::ORGANIZATION_ID),
        self::USER_ID,
        true,
        new DateTimeImmutable(),
      ));

    $manager = new InterventionResourceManager($resources, new InterventionMemberPolicy($members));

    self::assertSame(
      'organization.interventions.execute',
      $manager->mutationPermission(self::INTERVENTION_ID, self::USER_ID),
    );
  }

  #[Test]
  public function testAttachRejectsAnUnknownResource(): void
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('resourceExists')->willReturn(false);

    $manager = new InterventionResourceManager($resources);

    $this->expectException(InterventionResourceNotFoundException::class);
    $this->expectExceptionMessage('Facility resource with ID "' . self::RESOURCE_ID . '" not found.');

    $manager->attach(
      InterventionResourceType::FACILITY,
      self::RESOURCE_ID,
      self::ORGANIZATION_ID,
      self::INTERVENTION_ID,
    );
  }

  #[Test]
  public function testAttachAssignsResourceWithoutInterventionWhenIdentifierIsNull(): void
  {
    $resources = $this->createMock(InterventionResourceGatewayPort::class);
    $resources->method('resourceExists')->willReturn(true);
    $assignment = new InterventionResourceAssignment(null, 'active', 1);
    $resources->expects(self::once())->method('assign')
      ->with(InterventionResourceType::EQUIPMENT, self::RESOURCE_ID, null, 'client-ref')
      ->willReturn($assignment);

    $manager = new InterventionResourceManager($resources);

    self::assertSame(
      $assignment,
      $manager->attach(
        InterventionResourceType::EQUIPMENT,
        self::RESOURCE_ID,
        self::ORGANIZATION_ID,
        null,
        'client-ref',
      ),
    );
  }

  #[Test]
  public function testAttachAssignsResourceWithoutInterventionWhenIdentifierIsBlank(): void
  {
    $assignment = new InterventionResourceAssignment(null, 'active', 1);
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('resourceExists')->willReturn(true);
    $resources->method('assign')->willReturn($assignment);

    $manager = new InterventionResourceManager($resources);

    self::assertSame(
      $assignment,
      $manager->attach(InterventionResourceType::EQUIPMENT, self::RESOURCE_ID, self::ORGANIZATION_ID, ''),
    );
  }

  #[Test]
  public function testAttachRejectsAnUnknownIntervention(): void
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('resourceExists')->willReturn(true);
    $resources->method('interventionAssignmentContext')->willReturn(null);

    $manager = new InterventionResourceManager($resources);

    $this->expectException(InterventionNotFoundException::class);
    $this->expectExceptionMessage('Intervention with ID "' . self::INTERVENTION_ID . '" not found.');

    $manager->attach(
      InterventionResourceType::EQUIPMENT,
      self::RESOURCE_ID,
      self::ORGANIZATION_ID,
      self::INTERVENTION_ID,
    );
  }

  #[Test]
  public function testAttachRejectsAResourceFromAnotherOrganization(): void
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('resourceExists')->willReturn(true);
    $resources->method('interventionAssignmentContext')->willReturn(new InterventionAssignmentContext(
      self::INTERVENTION_ID,
      'other-organization',
      'planned',
    ));

    $manager = new InterventionResourceManager($resources);

    $this->expectException(InterventionConflictException::class);
    $this->expectExceptionMessage('Intervention and resource must belong to the same organization.');

    $manager->attach(
      InterventionResourceType::EQUIPMENT,
      self::RESOURCE_ID,
      self::ORGANIZATION_ID,
      self::INTERVENTION_ID,
    );
  }

  #[Test]
  public function testAttachRejectsResourcesOnceTheInterventionIsSubmitted(): void
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('resourceExists')->willReturn(true);
    $resources->method('interventionAssignmentContext')->willReturn(new InterventionAssignmentContext(
      self::INTERVENTION_ID,
      self::ORGANIZATION_ID,
      'submitted',
    ));

    $manager = new InterventionResourceManager($resources);

    $this->expectException(InterventionConflictException::class);
    $this->expectExceptionMessage('Resources can only be attached before intervention submission.');

    $manager->attach(
      InterventionResourceType::EQUIPMENT,
      self::RESOURCE_ID,
      self::ORGANIZATION_ID,
      self::INTERVENTION_ID,
    );
  }

  #[Test]
  public function testAttachAssignsResourceToAPlannedIntervention(): void
  {
    $resources = $this->createMock(InterventionResourceGatewayPort::class);
    $resources->method('resourceExists')->willReturn(true);
    $resources->method('interventionAssignmentContext')->willReturn(new InterventionAssignmentContext(
      self::INTERVENTION_ID,
      self::ORGANIZATION_ID,
      'planned',
    ));
    $assignment = new InterventionResourceAssignment(self::INTERVENTION_ID, 'active', 2);
    $resources->expects(self::once())->method('assign')
      ->with(InterventionResourceType::INSPECTION, self::RESOURCE_ID, self::INTERVENTION_ID, 'client-ref')
      ->willReturn($assignment);

    $manager = new InterventionResourceManager($resources);

    self::assertSame(
      $assignment,
      $manager->attach(
        InterventionResourceType::INSPECTION,
        self::RESOURCE_ID,
        self::ORGANIZATION_ID,
        self::INTERVENTION_ID,
        'client-ref',
      ),
    );
  }

  #[Test]
  public function testAssertOfflineCreateSkipsValidationWhenClientIdIsMissing(): void
  {
    $resources = $this->createMock(InterventionResourceGatewayPort::class);
    $resources->expects(self::never())->method('clientIdExists');

    $manager = new InterventionResourceManager($resources);

    $manager->assertOfflineCreate(InterventionResourceType::FACILITY, null);
    $manager->assertOfflineCreate(InterventionResourceType::FACILITY, '');
  }

  #[Test]
  public function testAssertOfflineCreateAllowsAnUnusedClientIdentifier(): void
  {
    $this->expectNotToPerformAssertions();

    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('clientIdExists')->willReturn(false);

    $manager = new InterventionResourceManager($resources);

    $manager->assertOfflineCreate(InterventionResourceType::EQUIPMENT, self::RESOURCE_ID);
  }

  #[Test]
  public function testInterventionContextDelegatesToTheGateway(): void
  {
    $context = new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'draft');
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionAssignmentContext')->willReturn($context);

    $manager = new InterventionResourceManager($resources);

    self::assertSame($context, $manager->interventionContext(self::INTERVENTION_ID));
  }

  #[Test]
  public function testResourceInInterventionScopeDelegatesToTheGateway(): void
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('resourceInInterventionScope')->willReturn(true);

    $manager = new InterventionResourceManager($resources);

    self::assertTrue($manager->resourceInInterventionScope(
      InterventionResourceType::EQUIPMENT,
      self::RESOURCE_ID,
      self::INTERVENTION_ID,
    ));
  }

  #[Test]
  public function testTouchDraftInterventionDelegatesToTheGateway(): void
  {
    $resources = $this->createMock(InterventionResourceGatewayPort::class);
    $resources->expects(self::once())->method('touchDraftIntervention')
      ->with(self::INTERVENTION_ID);

    $manager = new InterventionResourceManager($resources);

    $manager->touchDraftIntervention(self::INTERVENTION_ID);
  }

  #[Test]
  public function testMutationPermissionRejectsAnUnknownIntervention(): void
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionMutationContext')->willReturn(null);

    $manager = new InterventionResourceManager($resources);

    $this->expectException(InterventionNotFoundException::class);
    $this->expectExceptionMessage('Intervention with ID "' . self::INTERVENTION_ID . '" not found.');

    $manager->mutationPermission(self::INTERVENTION_ID);
  }

  #[Test]
  public function testResolvesExecutionPermissionWithoutConsultingMembership(): void
  {
    self::assertSame(
      'organization.interventions.execute',
      $this->managerWithInterventionStatus('changes_requested')->mutationPermission(
        self::INTERVENTION_ID,
        self::USER_ID,
      ),
    );
  }

  private function managerWithInterventionStatus(string $status): InterventionResourceManager
  {
    $resources = $this->createMock(InterventionResourceGatewayPort::class);
    $resources->expects(self::once())->method('interventionMutationContext')
      ->with(self::INTERVENTION_ID)
      ->willReturn(new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, $status));

    return new InterventionResourceManager($resources);
  }
}
