<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Factory;

use Intervention\Application\Contract\Workflow\InterventionWorkflowView;
use Intervention\Presentation\Api\Factory\InterventionWorkItemOutputFactory;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionWorkItemOutputFactoryTest.
 *
 * Verifies deterministic output mapping from authorized Application snapshots.
 * Lookup, fallback and permission decisions belong to Application enrichment.
 *
 * @category Factory Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionWorkItemOutputFactory::class)]
final class InterventionWorkItemOutputFactoryTest extends TestCase
{
  private const string ASSIGNEE_IRI = '/api/organizations/org-1/members/member-1';

  #[Test]
  public function testMapsTheProjectedAssigneeIdentity(): void
  {
    $output = new InterventionWorkItemOutputFactory()->fromView($this->view(self::ASSIGNEE_IRI, [
      'assigneeProfile' => $this->profile(),
    ]));
    self::assertSame(self::ASSIGNEE_IRI, $output->assignee);
    self::assertNotNull($output->assigneeProfile);
    self::assertSame(self::ASSIGNEE_IRI, $output->assigneeProfile->member);
    self::assertSame('user-1', $output->assigneeProfile->userId);
    self::assertSame('Jane Doe', $output->assigneeProfile->displayName);
    self::assertSame('Jane', $output->assigneeProfile->firstName);
    self::assertSame('Doe', $output->assigneeProfile->lastName);
    self::assertSame('https://cdn/avatar/256.webp', $output->assigneeProfile->avatarUrl);
  }

  #[Test]
  public function testUnassignedWorkItemHasNoProfile(): void
  {
    $output = new InterventionWorkItemOutputFactory()->fromView($this->view(null));
    self::assertNull($output->assignee);
    self::assertNull($output->assigneeProfile);
  }

  #[Test]
  public function testAnUnavailableProfilePreservesTheAssignedMemberIri(): void
  {
    $output = new InterventionWorkItemOutputFactory()->fromView($this->view(self::ASSIGNEE_IRI));
    self::assertSame(self::ASSIGNEE_IRI, $output->assignee);
    self::assertNull($output->assigneeProfile);
  }

  #[Test]
  public function testPreservesTheApplicationFallbackIdentity(): void
  {
    $output = new InterventionWorkItemOutputFactory()->fromView($this->view(self::ASSIGNEE_IRI, [
      'assigneeProfile' => [...$this->profile(), 'displayName' => 'user-1', 'firstName' => null, 'lastName' => null, 'avatarUrl' => null],
    ]));
    self::assertSame('user-1', $output->assigneeProfile?->displayName);
    self::assertNull($output->assigneeProfile->avatarUrl);
  }

  #[Test]
  public function testDoesNotReuseAnAssigneeFromAnEarlierProjection(): void
  {
    $factory = new InterventionWorkItemOutputFactory();
    $first = $factory->fromView($this->view(self::ASSIGNEE_IRI, ['assigneeProfile' => $this->profile()]));
    $second = $factory->fromView($this->view('/api/organizations/org-1/members/other-member'));
    self::assertSame('Jane Doe', $first->assigneeProfile?->displayName);
    self::assertNull($second->assigneeProfile);
  }

  #[Test]
  public function testMapsTheProjectedFacilityTarget(): void
  {
    $target = '/api/facilities/fac-1';
    $output = new InterventionWorkItemOutputFactory()->fromView($this->view(null, [
      'target' => $target,
      'targetSummary' => ['resource' => $target, 'kind' => 'facility', 'label' => 'Boiler Room'],
    ]));
    self::assertSame($target, $output->targetSummary?->resource);
    self::assertSame('facility', $output->targetSummary->kind);
    self::assertSame('Boiler Room', $output->targetSummary->label);
  }

  #[Test]
  public function testMapsTheProjectedEquipmentLabel(): void
  {
    $target = '/api/equipment/eq-1';
    $output = new InterventionWorkItemOutputFactory()->fromView($this->view(null, [
      'target' => $target,
      'targetSummary' => ['resource' => $target, 'kind' => 'equipment', 'label' => 'extinguisher · SN-42'],
    ]));
    self::assertSame('equipment', $output->targetSummary?->kind);
    self::assertSame('extinguisher · SN-42', $output->targetSummary->label);
  }

  #[Test]
  public function testFreeTextTargetHasNoSummary(): void
  {
    $output = new InterventionWorkItemOutputFactory()->fromView($this->view(null, ['target' => 'Check the back door']));
    self::assertSame('Check the back door', $output->target);
    self::assertNull($output->targetSummary);
  }

  #[Test]
  public function testAnUnavailableTargetSummaryPreservesTheTargetIri(): void
  {
    $output = new InterventionWorkItemOutputFactory()->fromView($this->view(null, ['target' => '/api/facilities/fac-1']));
    self::assertSame('/api/facilities/fac-1', $output->target);
    self::assertNull($output->targetSummary);
  }

  #[Test]
  public function testFreeTextAssigneeYieldsNoProfile(): void
  {
    $output = new InterventionWorkItemOutputFactory()->fromView($this->view('Someone on site'));
    self::assertSame('Someone on site', $output->assignee);
    self::assertNull($output->assigneeProfile);
  }

  #[Test]
  public function testMapsAuthorizedActionCapabilitiesWithoutInferringThemFromStatus(): void
  {
    $actions = ['canLogTime' => false, 'canManageTime' => true, 'canReestimate' => false, 'canReassign' => false, 'canEditPlanning' => true, 'canExecute' => false];
    $output = new InterventionWorkItemOutputFactory()->fromView($this->view(self::ASSIGNEE_IRI, ['allowedActions' => $actions]), 'user-1');
    self::assertSame($actions, $output->allowedActions);
    $withoutSnapshot = new InterventionWorkItemOutputFactory()->fromView($this->view(self::ASSIGNEE_IRI), 'user-1');
    self::assertSame(['canLogTime' => false, 'canManageTime' => false, 'canReestimate' => false, 'canReassign' => false, 'canEditPlanning' => false, 'canExecute' => false], $withoutSnapshot->allowedActions);
  }

  /**
   * @return array{member: string, userId: string, displayName: string, firstName: string, lastName: string, avatarUrl: string}
   */
  private function profile(): array
  {
    return ['member' => self::ASSIGNEE_IRI, 'userId' => 'user-1', 'displayName' => 'Jane Doe', 'firstName' => 'Jane', 'lastName' => 'Doe', 'avatarUrl' => 'https://cdn/avatar/256.webp'];
  }

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
        'spentMinutes' => 0,
        'revision' => 1,
        'createdAt' => '2026-01-01T00:00:00+00:00',
        'updatedAt' => '2026-01-01T00:00:00+00:00',
        ...$extra,
      ],
    );
  }
}
