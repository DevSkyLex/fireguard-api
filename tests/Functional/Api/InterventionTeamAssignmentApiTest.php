<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord, TeamMemberRecord, TeamRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_decode;

/**
 * Test InterventionTeamAssignmentApiTest.
 *
 * The `POST /interventions/{id}/team-assignments` HTTP contract, denial paths
 * first: 401 unauthenticated, 403 for a member of the owning organization
 * without `organization.interventions.plan`, 404 — never 403 — for an
 * intervention or a team outside the caller's organization, 409 for a frozen
 * intervention, and 422 for a team that exists but has no active members.
 *
 * Two claims this file exists to pin down:
 *
 * - the assignment is **not** draft-only. Participants follow
 *   `Intervention::assertScheduleMutable()`, so a `planned` intervention still
 *   accepts a team and only `submitted` (frozen under review), `published` and
 *   `abandoned` conflict. The resource used to document a draft-only rule the
 *   code never enforced;
 * - an unknown team and an empty team are **different** answers. Both used to
 *   be 422, because `TeamDirectoryPort::listActiveMemberIds()` flattens a
 *   missing team into the same empty list an empty one returns.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionTeamAssignmentApiTest extends WebTestCase
{
  // #region Constants
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  private const string DUMMY_UUID_2 = '550e8400-e29b-41d4-a716-446655440001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655490001';

  private const string OUTSIDER_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655490002';

  /**
   * Holds `*`, therefore `organization.interventions.plan`.
   */
  private const string PLANNER_USER_ID = '550e8400-e29b-41d4-a716-446655490003';

  /**
   * A member of the owning organization with `organization.read` only: may be
   * seen by the endpoint, may not plan.
   */
  private const string PLAIN_MEMBER_USER_ID = '550e8400-e29b-41d4-a716-446655490004';

  private const string OUTSIDER_USER_ID = '550e8400-e29b-41d4-a716-446655490005';

  private const string PLANNER_MEMBER_ID = '550e8400-e29b-41d4-a716-446655490010';

  private const string PLAIN_MEMBER_ID = '550e8400-e29b-41d4-a716-446655490011';

  private const string TEAM_MEMBER_ID = '550e8400-e29b-41d4-a716-446655490012';

  private const string OUTSIDER_MEMBER_ID = '550e8400-e29b-41d4-a716-446655490013';

  /**
   * Two active members: the planner and a field member.
   */
  private const string TEAM_ID = '550e8400-e29b-41d4-a716-446655490020';

  /**
   * Exists in the owning organization, holds no member at all.
   */
  private const string EMPTY_TEAM_ID = '550e8400-e29b-41d4-a716-446655490021';

  /**
   * Real, with an active member — owned by the unrelated organization.
   */
  private const string OUTSIDER_TEAM_ID = '550e8400-e29b-41d4-a716-446655490022';

  private const string DRAFT_INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655490030';

  private const string PLANNED_INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655490031';

  private const string SUBMITTED_INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655490032';

  private const string OUTSIDER_INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655490033';
  // #endregion

  // #region Unauthenticated
  #[Test]
  public function testAssignTeamToInterventionRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/interventions/' . self::DUMMY_UUID . '/team-assignments', server: [
      'CONTENT_TYPE' => 'application/ld+json',
    ], content: '{"teamId":"' . self::DUMMY_UUID_2 . '"}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'POST /interventions/{id}/team-assignments endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /interventions/{id}/team-assignments, got ' . $statusCode,
    );
  }
  // #endregion

  // #region Success paths
  #[Test]
  public function testAssignTeamUnionsTheTeamsActiveMembersIntoTheParticipants(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::PLANNER_USER_ID, 'team-assignment-planner@example.com');

    $this->assignTeam($client, self::DRAFT_INTERVENTION_ID, self::TEAM_ID);

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

    $decoded = json_decode((string) $response->getContent(), true);
    self::assertIsArray($decoded);
    self::assertSame(self::DRAFT_INTERVENTION_ID, $decoded['id'] ?? null);
    self::assertEqualsCanonicalizing(
      expected: [
        $this->memberIri(self::PLANNER_MEMBER_ID),
        $this->memberIri(self::TEAM_MEMBER_ID),
      ],
      actual: $decoded['participants'] ?? null,
      message: 'Both active team members must land in the participants list, as member IRIs.',
    );
  }

  #[Test]
  public function testAssignTeamIsAcceptedOnAPlannedIntervention(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::PLANNER_USER_ID, 'team-assignment-planner@example.com');

    // The regression this guards: the resource documented the assignment as
    // draft-only, while `assertScheduleMutable()` has always let participants
    // move through planned, in_progress and changes_requested. Tightening the
    // code to match the old text would break replanning a delayed
    // intervention, which is the behaviour the aggregate intends.
    $this->assignTeam($client, self::PLANNED_INTERVENTION_ID, self::TEAM_ID);

    self::assertSame(
      expected: 200,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A planned intervention still accepts a team assignment. Response: ' . $client->getResponse()->getContent(),
    );
  }
  // #endregion

  // #region Denial paths
  #[Test]
  public function testAssignTeamRejectsAMemberWithoutThePlanPermissionWith403(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::PLAIN_MEMBER_USER_ID, 'team-assignment-plain-member@example.com');

    $this->assignTeam($client, self::DRAFT_INTERVENTION_ID, self::TEAM_ID);

    // An active member of the owning organization: they may know the
    // intervention exists, they simply may not plan it.
    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A member without organization.interventions.plan must get 403. Response: ' . $client->getResponse()->getContent(),
    );
  }

  #[Test]
  public function testAssignTeamReturns404ForAnInterventionInAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::PLANNER_USER_ID, 'team-assignment-planner@example.com');

    $this->assignTeam($client, self::OUTSIDER_INTERVENTION_ID, self::TEAM_ID);

    // 403 here would confirm the intervention exists to someone with no
    // membership in the organization that owns it.
    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'An intervention outside the caller\'s organization must yield 404, not 403.',
    );
  }

  #[Test]
  public function testAssignTeamReturns404ForAnUnknownIntervention(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::PLANNER_USER_ID, 'team-assignment-planner@example.com');

    $this->assignTeam($client, self::DUMMY_UUID, self::TEAM_ID);

    self::assertSame(404, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  #[Test]
  public function testAssignTeamReturns404ForAnUnknownTeam(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::PLANNER_USER_ID, 'team-assignment-planner@example.com');

    $this->assignTeam($client, self::DRAFT_INTERVENTION_ID, self::DUMMY_UUID_2);

    // Not the 422 an empty team gets: nothing was assigned because the
    // identifier names no team, not because the team is empty.
    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A teamId naming no team must yield 404. Response: ' . $client->getResponse()->getContent(),
    );
  }

  #[Test]
  public function testAssignTeamReturns404ForATeamInAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::PLANNER_USER_ID, 'team-assignment-planner@example.com');

    $this->assignTeam($client, self::DRAFT_INTERVENTION_ID, self::OUTSIDER_TEAM_ID);

    // Byte-identical to the unknown-team answer above on purpose: a distinct
    // status would tell the caller which team ids are real elsewhere.
    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A team owned by another organization must yield 404, indistinguishable from an unknown one.',
    );
  }

  #[Test]
  public function testAssignTeamReturns409OnASubmittedIntervention(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::PLANNER_USER_ID, 'team-assignment-planner@example.com');

    $this->assignTeam($client, self::SUBMITTED_INTERVENTION_ID, self::TEAM_ID);

    // `submitted` is the one non-terminal status that freezes participants:
    // withdraw the intervention to replan it.
    self::assertSame(
      expected: 409,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A submitted intervention must refuse the assignment with 409. Response: ' . $client->getResponse()->getContent(),
    );
  }

  #[Test]
  public function testAssignTeamReturns428WithoutAnIfMatchHeader(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::PLANNER_USER_ID, 'team-assignment-planner@example.com');

    $this->assignTeam($client, self::DRAFT_INTERVENTION_ID, self::TEAM_ID, revision: null);

    // Inherited from the workflow mutation path rather than declared on this
    // resource: the assignment is a participants write like any other, so it
    // carries the same optimistic-concurrency guard.
    self::assertSame(
      expected: 428,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A team assignment without If-Match must be refused with 428. Response: ' . $client->getResponse()->getContent(),
    );
  }

  #[Test]
  public function testAssignTeamReturns412ForAStaleRevision(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::PLANNER_USER_ID, 'team-assignment-planner@example.com');

    $this->assignTeam($client, self::DRAFT_INTERVENTION_ID, self::TEAM_ID, revision: 99);

    self::assertSame(
      expected: 412,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A stale If-Match revision must be refused with 412. Response: ' . $client->getResponse()->getContent(),
    );
  }

  #[Test]
  public function testAssignTeamReturns422ForATeamWithNoActiveMembers(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::PLANNER_USER_ID, 'team-assignment-planner@example.com');

    $this->assignTeam($client, self::DRAFT_INTERVENTION_ID, self::EMPTY_TEAM_ID);

    // The team is real and in the caller's organization — it simply has
    // nobody to assign. Rejected rather than silently no-op'ing.
    self::assertSame(
      expected: 422,
      actual: $client->getResponse()->getStatusCode(),
      message: 'An empty team must be rejected with 422. Response: ' . $client->getResponse()->getContent(),
    );
  }
  // #endregion

  // #region Helpers
  /**
   * Posts a team assignment. The endpoint rides the workflow mutation path,
   * so it inherits its optimistic-concurrency guard: without `If-Match` the
   * request is refused with 428 before any business rule runs. Seeded
   * interventions carry `revision` 1.
   *
   * @param ?int $revision the expected revision, or null to omit If-Match entirely
   */
  private function assignTeam(KernelBrowser $client, string $interventionId, string $teamId, ?int $revision = 1): void
  {
    $server = ['CONTENT_TYPE' => 'application/ld+json'];
    if (null !== $revision) {
      $server['HTTP_IF_MATCH'] = '"revision-' . $revision . '"';
    }

    $client->request('POST', '/api/interventions/' . $interventionId . '/team-assignments', server: $server, content: '{"teamId":"' . $teamId . '"}');
  }

  private function memberIri(string $memberId): string
  {
    return '/api/organizations/' . self::ORGANIZATION_ID . '/members/' . $memberId;
  }

  /**
   * Authenticates the client against the stateless `api` firewall (the token
   * is stored in the container, not the session).
   */
  private function loginAs(KernelBrowser $client, string $userId, string $email): void
  {
    $user = new SecurityUser(
      id: $userId,
      email: $email,
      password: 'hashed-password',
      roles: ['ROLE_USER'],
    );
    $client->loginUser($user, 'api');
  }

  private function seed(): void
  {
    $this->seedOrganizations();
    $this->seedTeams();
    $this->seedInterventions();
  }

  /**
   * Seeds (idempotently) the owning organization with a planner (`*`) and a
   * plain member (`organization.read` only), a third member who only ever
   * appears through the team, plus an unrelated organization with its own
   * member — the "outside scope" caller and team owner.
   */
  private function seedOrganizations(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    foreach ([self::ORGANIZATION_ID, self::OUTSIDER_ORGANIZATION_ID] as $organizationId) {
      $existing = $entityManager->find(OrganizationRecord::class, $organizationId);
      if ($existing instanceof OrganizationRecord) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }
    }

    $now = new DateTimeImmutable('2026-08-18T00:00:00+00:00');

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Team Assignment Test Org';
    $organization->slug = 'team-assignment-test-org-' . self::ORGANIZATION_ID;
    $organization->ownerUserId = self::PLANNER_USER_ID;
    $organization->createdByUserId = self::PLANNER_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $outsiderOrganization = new OrganizationRecord();
    $outsiderOrganization->id = self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->name = 'Team Assignment Outsider Org';
    $outsiderOrganization->slug = 'team-assignment-outsider-org-' . self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->ownerUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->createdByUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->status = 'active';
    $outsiderOrganization->isActive = true;
    $outsiderOrganization->createdAt = $now;
    $outsiderOrganization->updatedAt = $now;
    $entityManager->persist($outsiderOrganization);

    $roles = [
      ['550e8400-e29b-41d4-a716-446655490040', $organization, 'team-assignment-full-access', ['*'], 'Functional-test-only role granting every permission.'],
      ['550e8400-e29b-41d4-a716-446655490041', $organization, 'team-assignment-read-only', ['organization.read'], 'Functional-test-only role without organization.interventions.plan.'],
      ['550e8400-e29b-41d4-a716-446655490042', $outsiderOrganization, 'team-assignment-outsider-full-access', ['*'], 'Functional-test-only role for the unrelated organization.'],
    ];

    $roleRecords = [];
    foreach ($roles as [$roleId, $roleOrganization, $roleName, $permissions, $description]) {
      $role = new OrganizationRoleRecord();
      $role->id = $roleId;
      $role->organization = $roleOrganization;
      $role->name = $roleName;
      $role->permissions = $permissions;
      $role->description = $description;
      $role->isSystem = false;
      $role->createdAt = $now;
      $entityManager->persist($role);
      $roleRecords[$roleId] = $role;
    }

    $members = [
      [self::PLANNER_MEMBER_ID, $organization, self::PLANNER_USER_ID, '550e8400-e29b-41d4-a716-446655490040'],
      [self::PLAIN_MEMBER_ID, $organization, self::PLAIN_MEMBER_USER_ID, '550e8400-e29b-41d4-a716-446655490041'],
      // No user of their own logs in as this one: it exists to be expanded
      // out of the team into the participants list.
      [self::TEAM_MEMBER_ID, $organization, '550e8400-e29b-41d4-a716-446655490006', '550e8400-e29b-41d4-a716-446655490041'],
      [self::OUTSIDER_MEMBER_ID, $outsiderOrganization, self::OUTSIDER_USER_ID, '550e8400-e29b-41d4-a716-446655490042'],
    ];

    foreach ($members as [$memberId, $memberOrganization, $userId, $roleId]) {
      $member = new OrganizationMemberRecord();
      $member->id = $memberId;
      $member->organization = $memberOrganization;
      $member->userId = $userId;
      $member->isActive = true;
      $member->joinedAt = $now;
      $entityManager->persist($member);

      $assignment = new OrganizationMemberRoleRecord();
      $assignment->member = $member;
      $assignment->role = $roleRecords[$roleId];
      $assignment->assignedAt = $now;
      $entityManager->persist($assignment);
    }

    $entityManager->flush();
  }

  /**
   * Seeds the three teams the denial paths need: a populated one, an empty
   * one in the same organization, and a populated one in the unrelated
   * organization.
   */
  private function seedTeams(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-08-18T00:00:00+00:00');

    $teams = [
      [self::TEAM_ID, self::ORGANIZATION_ID, 'Night shift', [self::PLANNER_MEMBER_ID, self::TEAM_MEMBER_ID]],
      [self::EMPTY_TEAM_ID, self::ORGANIZATION_ID, 'Newly created team', []],
      [self::OUTSIDER_TEAM_ID, self::OUTSIDER_ORGANIZATION_ID, 'Outsider crew', [self::OUTSIDER_MEMBER_ID]],
    ];

    foreach ($teams as [$teamId, $organizationId, $name, $memberIds]) {
      $existing = $entityManager->find(TeamRecord::class, $teamId);
      if ($existing instanceof TeamRecord) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }

      $team = new TeamRecord();
      $team->id = $teamId;
      $team->organization = $entityManager->getReference(OrganizationRecord::class, $organizationId);
      $team->name = $name;
      $team->description = 'Functional-test-only team.';
      $team->createdAt = $now;
      $team->updatedAt = $now;
      $entityManager->persist($team);

      foreach ($memberIds as $memberId) {
        $membership = new TeamMemberRecord();
        $membership->team = $team;
        $membership->member = $entityManager->getReference(OrganizationMemberRecord::class, $memberId);
        $membership->addedAt = $now;
        $entityManager->persist($membership);
      }
    }

    $entityManager->flush();
  }

  /**
   * Seeds one intervention per status the contract distinguishes, plus one
   * owned by the unrelated organization. The planner is the responsible
   * member throughout, so a non-draft status never trips
   * `InterventionMemberPolicy` on top of the permission gate under test.
   */
  private function seedInterventions(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-08-18T00:00:00+00:00');

    $interventions = [
      [self::DRAFT_INTERVENTION_ID, self::ORGANIZATION_ID, 'draft', 910, self::PLANNER_MEMBER_ID],
      [self::PLANNED_INTERVENTION_ID, self::ORGANIZATION_ID, 'planned', 911, self::PLANNER_MEMBER_ID],
      [self::SUBMITTED_INTERVENTION_ID, self::ORGANIZATION_ID, 'submitted', 912, self::PLANNER_MEMBER_ID],
      [self::OUTSIDER_INTERVENTION_ID, self::OUTSIDER_ORGANIZATION_ID, 'draft', 913, self::OUTSIDER_MEMBER_ID],
    ];

    foreach ($interventions as [$interventionId, $organizationId, $status, $number, $responsibleId]) {
      $existing = $entityManager->find(InterventionRecord::class, $interventionId);
      if ($existing instanceof InterventionRecord) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }

      $intervention = new InterventionRecord();
      $intervention->id = $interventionId;
      $intervention->organization = $entityManager->getReference(OrganizationRecord::class, $organizationId);
      $intervention->type = 'site_setup';
      $intervention->name = 'Team Assignment Test Intervention ' . $number;
      $intervention->number = $number;
      $intervention->status = $status;
      $intervention->responsibleId = $responsibleId;
      $intervention->createdAt = $now;
      $intervention->updatedAt = $now;
      $entityManager->persist($intervention);
    }

    $entityManager->flush();
  }
  // #endregion
}
