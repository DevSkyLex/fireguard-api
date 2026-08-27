<?php

declare(strict_types=1);

namespace Organization\Infrastructure\DataFixtures;

use Doctrine\Bundle\FixturesBundle\{Fixture, FixtureGroupInterface};
use Doctrine\Persistence\ObjectManager;
use Organization\Domain\Catalog\OrganizationSystemRoleCatalog;
use Organization\Domain\ValueObject\{OrganizationInvitationStatus, OrganizationStatus};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationInvitationRecord, OrganizationInvitationRoleRecord, OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord, TeamMemberRecord, TeamRecord};
use Shared\Infrastructure\DataFixtures\{SeedTimeline, SeedUuid};
use User\Infrastructure\DataFixtures\UserFixtures;

use function explode;
use function hash;
use function sprintf;

final class OrganizationFixtures extends Fixture implements FixtureGroupInterface
{
  public const string ORGANIZATION_REFERENCE = 'organization-seed';

  public const string ADMIN_ROLE_REFERENCE = 'organization-seed-admin-role';

  public const string MEMBER_ROLE_REFERENCE = 'organization-seed-member-role';

  public const string INSPECTOR_ROLE_REFERENCE = 'organization-seed-inspector-role';

  public const string OWNER_MEMBER_REFERENCE = 'organization-seed-owner-member';

  public const string INSPECTOR_MEMBER_REFERENCE = 'organization-seed-inspector-member';

  public const string INVITATION_REFERENCE = 'organization-seed-invitation';

  public const string SAFETY_MANAGER_MEMBER_REFERENCE = 'organization-seed-safety-manager-member';

  public const string PARIS_TECHNICIAN_MEMBER_REFERENCE = 'organization-seed-paris-technician-member';

  public const string FIELD_TECHNICIAN_MEMBER_REFERENCE = 'organization-seed-field-technician-member';

  public const string REGIONAL_COORDINATOR_MEMBER_REFERENCE = 'organization-seed-regional-coordinator-member';

  public const string EXTERNAL_AUDITOR_MEMBER_REFERENCE = 'organization-seed-external-auditor-member';

  public const string WAREHOUSE_LEAD_MEMBER_REFERENCE = 'organization-seed-warehouse-lead-member';

  public const string PARIS_TEAM_REFERENCE = 'organization-seed-paris-team';

  public const string REGIONAL_TEAM_REFERENCE = 'organization-seed-regional-team';

  public const string AUDIT_TEAM_REFERENCE = 'organization-seed-audit-team';

  public const string ORGANIZATION_ID = '11111111-1111-4111-8111-111111111111';

  /**
   * Constant STAFF_MEMBER_SEEDS.
   *
   * Turns the seeded {@see UserFixtures::STAFF_SEEDS} workforce into
   * organization members. `roleReference` picks which of the three seeded
   * organization roles they carry, and `isActive` mirrors their account
   * state: a departed, locked or unverified user stays on the roster but
   * cannot be assigned work — the member policy rejects inactive members as
   * an intervention responsible or assignee.
   *
   * @since 1.1.0
   *
   * @var list<array{
   *   reference: string,
   *   id: string,
   *   userId: string,
   *   roleReference: string,
   *   isActive: bool,
   *   joinedAt: string
   * }>
   */
  public const array STAFF_MEMBER_SEEDS = [
    [
      'reference' => self::SAFETY_MANAGER_MEMBER_REFERENCE,
      'id' => 'd0c2c74e-0c66-438c-b7e1-2b10f54bbd79',
      'userId' => '1a1891d0-f1a4-4392-8cf5-a6b2604873fc',
      'roleReference' => self::ADMIN_ROLE_REFERENCE,
      'isActive' => true,
      'joinedAt' => '2026-02-03T09:00:00+00:00',
    ],
    [
      'reference' => self::PARIS_TECHNICIAN_MEMBER_REFERENCE,
      'id' => '126b5cfc-208e-48b0-ae88-7bd97a1eecf8',
      'userId' => '8a181f64-6590-4365-94c4-14e313badf80',
      'roleReference' => self::MEMBER_ROLE_REFERENCE,
      'isActive' => true,
      'joinedAt' => '2026-02-03T09:10:00+00:00',
    ],
    [
      'reference' => self::FIELD_TECHNICIAN_MEMBER_REFERENCE,
      'id' => '0034f559-02ce-4ddb-a8b7-2e47dbc0be2c',
      'userId' => 'd2bbb340-23ce-46e5-be65-7e534970811e',
      'roleReference' => self::MEMBER_ROLE_REFERENCE,
      'isActive' => true,
      'joinedAt' => '2026-02-03T09:20:00+00:00',
    ],
    [
      'reference' => self::REGIONAL_COORDINATOR_MEMBER_REFERENCE,
      'id' => '853db03d-8a54-4c57-9faa-822de84c93f4',
      'userId' => '4a15e7c4-c5ff-4683-8df5-2b7c375cdada',
      'roleReference' => self::MEMBER_ROLE_REFERENCE,
      'isActive' => true,
      'joinedAt' => '2026-02-04T09:00:00+00:00',
    ],
    [
      'reference' => self::EXTERNAL_AUDITOR_MEMBER_REFERENCE,
      'id' => 'a6a9f897-43bb-4b71-841c-0784b8418423',
      'userId' => '287c2f89-c67c-4c2b-8a40-36f46d6e5f5b',
      'roleReference' => self::INSPECTOR_ROLE_REFERENCE,
      'isActive' => true,
      'joinedAt' => '2026-02-05T09:00:00+00:00',
    ],
    [
      'reference' => self::WAREHOUSE_LEAD_MEMBER_REFERENCE,
      'id' => '24fcc785-bd58-43b9-9028-47f01b66d9fe',
      'userId' => 'fd5ca358-2664-4c16-a55e-91d7b13fca87',
      'roleReference' => self::MEMBER_ROLE_REFERENCE,
      'isActive' => true,
      'joinedAt' => '2026-02-05T09:30:00+00:00',
    ],
    [
      'reference' => 'organization-seed-departed-member',
      'id' => 'dddb19a5-c275-480b-8e36-75c074c742bb',
      'userId' => '8e334c8d-ab1c-4929-8515-cc750abcc390',
      'roleReference' => self::MEMBER_ROLE_REFERENCE,
      'isActive' => false,
      'joinedAt' => '2026-02-06T09:00:00+00:00',
    ],
    [
      'reference' => 'organization-seed-new-joiner-member',
      'id' => 'e1246498-fdf6-4eb7-8ce7-0c7b925142f7',
      'userId' => '21434c1d-0e91-4c89-a3bf-8f67b2d61f9d',
      'roleReference' => self::MEMBER_ROLE_REFERENCE,
      'isActive' => false,
      'joinedAt' => '2026-03-10T09:00:00+00:00',
    ],
    [
      'reference' => 'organization-seed-locked-member',
      'id' => '4f80a5f9-91ba-46bd-a327-bb5b132d1495',
      'userId' => '9b60e414-c5d3-437b-b394-44446a482e1c',
      'roleReference' => self::MEMBER_ROLE_REFERENCE,
      'isActive' => false,
      'joinedAt' => '2026-02-06T09:30:00+00:00',
    ],
  ];

  /**
   * Constant TEAM_SEEDS.
   *
   * Squads the roster is grouped into. `members` holds `[memberReference,
   * teamRole]` pairs; the first entry of each team is its lead.
   *
   * @since 1.1.0
   *
   * @var list<array{
   *   reference: string,
   *   id: string,
   *   name: string,
   *   description: string,
   *   createdAt: string,
   *   members: list<array{reference: string, role: string}>
   * }>
   */
  public const array TEAM_SEEDS = [
    [
      'reference' => self::PARIS_TEAM_REFERENCE,
      'id' => '8aeaa8c5-be4f-40ad-90c9-6466a89731c7',
      'name' => 'Paris Safety Team',
      'description' => 'Day-to-day safety operations across the Paris headquarters.',
      'createdAt' => '2026-02-07T09:00:00+00:00',
      'members' => [
        ['reference' => self::SAFETY_MANAGER_MEMBER_REFERENCE, 'role' => 'lead'],
        ['reference' => self::OWNER_MEMBER_REFERENCE, 'role' => 'member'],
        ['reference' => self::PARIS_TECHNICIAN_MEMBER_REFERENCE, 'role' => 'member'],
        ['reference' => self::FIELD_TECHNICIAN_MEMBER_REFERENCE, 'role' => 'member'],
      ],
    ],
    [
      'reference' => self::REGIONAL_TEAM_REFERENCE,
      'id' => 'e9e5b575-dadc-4a0f-b232-6d9d27a85f71',
      'name' => 'Regional Field Team',
      'description' => 'Covers the Lyon, Marseille, Bordeaux and Lille sites.',
      'createdAt' => '2026-02-07T09:15:00+00:00',
      'members' => [
        ['reference' => self::REGIONAL_COORDINATOR_MEMBER_REFERENCE, 'role' => 'lead'],
        ['reference' => self::WAREHOUSE_LEAD_MEMBER_REFERENCE, 'role' => 'member'],
        ['reference' => self::FIELD_TECHNICIAN_MEMBER_REFERENCE, 'role' => 'member'],
      ],
    ],
    [
      'reference' => self::AUDIT_TEAM_REFERENCE,
      'id' => '10df08d3-9526-4a87-ac6c-552128dbd9c8',
      'name' => 'Audit & Compliance',
      'description' => 'Regulatory audits and non-conformity follow-up.',
      'createdAt' => '2026-02-07T09:30:00+00:00',
      'members' => [
        ['reference' => self::SAFETY_MANAGER_MEMBER_REFERENCE, 'role' => 'lead'],
        ['reference' => self::EXTERNAL_AUDITOR_MEMBER_REFERENCE, 'role' => 'auditor'],
        ['reference' => self::INSPECTOR_MEMBER_REFERENCE, 'role' => 'member'],
      ],
    ],
  ];

  /**
   * Constant BULK_MEMBER_COUNT.
   *
   * Mirrors {@see UserFixtures::BULK_STAFF_COUNT} one-to-one: every bulk
   * staff account becomes exactly one active "member" of this organization,
   * so the member list clears 50 rows the same way the user directory does.
   *
   * @since 1.2.0
   *
   * @var int
   */
  public const int BULK_MEMBER_COUNT = UserFixtures::BULK_STAFF_COUNT;

  /**
   * Constant SECONDARY_ORGANIZATION_SEEDS.
   *
   * Four more tenants alongside "Fireguard Seed Organization", so the
   * organization switcher and any platform-level "all organizations" view
   * have more than one row, and the other {@see OrganizationStatus} and plan
   * values are represented too. None of these get the rich Facility /
   * Equipment / Intervention graph the main organization does — that stays
   * scoped to `ORGANIZATION_ID` on purpose, so its integration tests keep
   * their exact counts.
   *
   * `extraMemberIndexes` are comma-separated {@see UserFixtures::bulkStaffId()}
   * indexes reused as additional members, demonstrating that one person can
   * belong to more than one organization; empty means "owner only" —
   * Prévention Alpha is deliberately a freshly onboarded, near-empty tenant,
   * so the empty-state screens have a real organization to render against.
   *
   * @since 1.3.0
   *
   * @var list<array{
   *   reference: string,
   *   name: string,
   *   slug: string,
   *   description: string,
   *   status: string,
   *   country: string,
   *   legalType: string,
   *   planId: string,
   *   ownerReference: string,
   *   createdAt: string,
   *   extraMemberIndexes: string
   * }>
   */
  public const array SECONDARY_ORGANIZATION_SEEDS = [
    [
      'reference' => 'organization-seed-nova',
      'name' => 'Nova Sécurité Incendie',
      'slug' => 'nova-securite-incendie',
      'description' => 'Prestataire de maintenance et de vérification incendie pour le secteur tertiaire.',
      'status' => 'active',
      'country' => 'FR',
      'legalType' => 'limited_liability_company',
      'planId' => PlanFixtures::PRO_PLAN_ID,
      'ownerReference' => UserFixtures::NOVA_OWNER_REFERENCE,
      'createdAt' => '2026-02-15T09:00:00+00:00',
      'extraMemberIndexes' => '0,1',
    ],
    [
      'reference' => 'organization-seed-vigilance',
      'name' => 'Groupe Vigilance Sécurité',
      'slug' => 'groupe-vigilance-securite',
      'description' => 'Petite structure régionale de contrôle des équipements de sécurité incendie.',
      'status' => 'active',
      'country' => 'FR',
      'legalType' => 'sole_proprietorship',
      'planId' => PlanFixtures::FREE_PLAN_ID,
      'ownerReference' => UserFixtures::VIGILANCE_OWNER_REFERENCE,
      'createdAt' => '2026-03-01T09:00:00+00:00',
      'extraMemberIndexes' => '5',
    ],
    [
      'reference' => 'organization-seed-safeguard',
      'name' => 'SafeGuard Consulting',
      'slug' => 'safeguard-consulting',
      'description' => 'Cabinet de conseil en conformité incendie, actuellement suspendu pour facturation impayée.',
      'status' => 'suspended',
      'country' => 'BE',
      'legalType' => 'partnership',
      'planId' => PlanFixtures::PRO_PLAN_ID,
      'ownerReference' => UserFixtures::SAFEGUARD_OWNER_REFERENCE,
      'createdAt' => '2026-01-20T09:00:00+00:00',
      'extraMemberIndexes' => '10',
    ],
    [
      'reference' => 'organization-seed-prevention',
      'name' => 'Prévention Alpha',
      'slug' => 'prevention-alpha',
      'description' => '',
      'status' => 'active',
      'country' => 'FR',
      'legalType' => 'sole_proprietorship',
      'planId' => PlanFixtures::FREE_PLAN_ID,
      'ownerReference' => UserFixtures::PREVENTION_OWNER_REFERENCE,
      'createdAt' => '2026-04-16T09:00:00+00:00',
      'extraMemberIndexes' => '',
    ],
  ];

  private const string OWNER_USER_ID = 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890';

  private const string INSPECTOR_USER_ID = 'b2c3d4e5-f6a7-4901-8cde-f23456789012';

  public static function getGroups(): array
  {
    return ['organization', 'main-seed'];
  }

  public function load(ObjectManager $manager): void
  {
    $organizationCreatedAt = SeedTimeline::at('2026-02-01T09:00:00+00:00');
    $roleCreatedAt = SeedTimeline::at('2026-02-01T09:15:00+00:00');
    $memberJoinedAt = SeedTimeline::at('2026-02-01T10:00:00+00:00');

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Fireguard Seed Organization';
    $organization->slug = 'fireguard-seed-organization';
    $organization->ownerUserId = self::OWNER_USER_ID;
    $organization->createdByUserId = self::OWNER_USER_ID;
    $organization->status = OrganizationStatus::ACTIVE->value;
    $organization->isActive = true;
    $organization->planId = PlanFixtures::MAX_PLAN_ID;
    $organization->createdAt = $organizationCreatedAt;
    $organization->updatedAt = $organizationCreatedAt;
    $manager->persist($organization);
    $this->addReference(self::ORGANIZATION_REFERENCE, $organization);

    $adminRole = new OrganizationRoleRecord();
    $adminRole->id = '7c99e153-c6f0-470b-9711-1719cbc421d2';
    $adminRole->organization = $organization;
    $adminRole->name = OrganizationSystemRoleCatalog::ADMIN;
    $adminRole->permissions = OrganizationSystemRoleCatalog::permissionsFor(OrganizationSystemRoleCatalog::ADMIN);
    $adminRole->description = 'Seeded administrator role';
    $adminRole->isSystem = true;
    $adminRole->createdAt = $roleCreatedAt;
    $manager->persist($adminRole);
    $this->addReference(self::ADMIN_ROLE_REFERENCE, $adminRole);

    $memberRole = new OrganizationRoleRecord();
    $memberRole->id = '958b13b0-be0f-4a07-807a-cf66f8dc21b9';
    $memberRole->organization = $organization;
    $memberRole->name = OrganizationSystemRoleCatalog::MEMBER;
    $memberRole->permissions = OrganizationSystemRoleCatalog::permissionsFor(OrganizationSystemRoleCatalog::MEMBER);
    $memberRole->description = 'Seeded member role';
    $memberRole->isSystem = true;
    $memberRole->createdAt = $roleCreatedAt;
    $manager->persist($memberRole);
    $this->addReference(self::MEMBER_ROLE_REFERENCE, $memberRole);

    $inspectorRole = new OrganizationRoleRecord();
    $inspectorRole->id = '4b8fcd56-13af-4c4f-b216-52ea0e9f02a7';
    $inspectorRole->organization = $organization;
    $inspectorRole->name = 'inspector';
    $inspectorRole->permissions = [
      'organization.read',
      'organization.facilities.read',
      'organization.equipment.read',
      'organization.inspection.read',
    ];
    $inspectorRole->description = 'Seeded restricted inspector role';
    $inspectorRole->isSystem = false;
    $inspectorRole->createdAt = SeedTimeline::at('2026-02-01T09:30:00+00:00');
    $manager->persist($inspectorRole);
    $this->addReference(self::INSPECTOR_ROLE_REFERENCE, $inspectorRole);

    $ownerMember = new OrganizationMemberRecord();
    $ownerMember->id = '11111111-1111-4111-8111-111111111115';
    $ownerMember->organization = $organization;
    $ownerMember->userId = self::OWNER_USER_ID;
    $ownerMember->isActive = true;
    $ownerMember->joinedAt = $memberJoinedAt;
    $manager->persist($ownerMember);
    $this->addReference(self::OWNER_MEMBER_REFERENCE, $ownerMember);

    $ownerAssignment = new OrganizationMemberRoleRecord();
    $ownerAssignment->member = $ownerMember;
    $ownerAssignment->role = $adminRole;
    $ownerAssignment->assignedAt = $memberJoinedAt;
    $manager->persist($ownerAssignment);

    $inspectorMember = new OrganizationMemberRecord();
    $inspectorMember->id = '11111111-1111-4111-8111-111111111116';
    $inspectorMember->organization = $organization;
    $inspectorMember->userId = self::INSPECTOR_USER_ID;
    $inspectorMember->isActive = true;
    $inspectorMember->joinedAt = SeedTimeline::at('2026-02-02T10:00:00+00:00');
    $manager->persist($inspectorMember);
    $this->addReference(self::INSPECTOR_MEMBER_REFERENCE, $inspectorMember);

    $inspectorAssignment = new OrganizationMemberRoleRecord();
    $inspectorAssignment->member = $inspectorMember;
    $inspectorAssignment->role = $inspectorRole;
    $inspectorAssignment->assignedAt = SeedTimeline::at('2026-02-02T10:05:00+00:00');
    $manager->persist($inspectorAssignment);

    $rolesByReference = [
      self::ADMIN_ROLE_REFERENCE => $adminRole,
      self::MEMBER_ROLE_REFERENCE => $memberRole,
      self::INSPECTOR_ROLE_REFERENCE => $inspectorRole,
    ];
    $membersByReference = [
      self::OWNER_MEMBER_REFERENCE => $ownerMember,
      self::INSPECTOR_MEMBER_REFERENCE => $inspectorMember,
    ];

    foreach (self::STAFF_MEMBER_SEEDS as $seed) {
      $joinedAt = SeedTimeline::at($seed['joinedAt']);

      $member = new OrganizationMemberRecord();
      $member->id = $seed['id'];
      $member->organization = $organization;
      $member->userId = $seed['userId'];
      $member->isActive = $seed['isActive'];
      $member->joinedAt = $joinedAt;
      $manager->persist($member);
      $this->addReference($seed['reference'], $member);
      $membersByReference[$seed['reference']] = $member;

      $assignment = new OrganizationMemberRoleRecord();
      $assignment->member = $member;
      $assignment->role = $rolesByReference[$seed['roleReference']];
      $assignment->assignedAt = $joinedAt;
      $manager->persist($assignment);
    }

    // Bulk generic members — mirrors UserFixtures::BULK_STAFF_COUNT one-to-one
    // so the roster clears 50 rows. Kept out of any team; they exist for
    // pagination volume, not for narrative roles.
    for ($i = 0; $i < self::BULK_MEMBER_COUNT; ++$i) {
      $joinedAt = SeedTimeline::at('2026-02-08T09:00:00+00:00')->modify(sprintf('+%d hours', $i));

      $bulkMember = new OrganizationMemberRecord();
      $bulkMember->id = SeedUuid::from(sprintf('organization-bulk-member:%d', $i));
      $bulkMember->organization = $organization;
      $bulkMember->userId = UserFixtures::bulkStaffId($i);
      $bulkMember->isActive = true;
      $bulkMember->joinedAt = $joinedAt;
      $manager->persist($bulkMember);
      $this->addReference(self::bulkMemberReference($i), $bulkMember);
      $membersByReference[self::bulkMemberReference($i)] = $bulkMember;

      $bulkAssignment = new OrganizationMemberRoleRecord();
      $bulkAssignment->member = $bulkMember;
      $bulkAssignment->role = $memberRole;
      $bulkAssignment->assignedAt = $joinedAt;
      $manager->persist($bulkAssignment);
    }

    foreach (self::TEAM_SEEDS as $seed) {
      $createdAt = SeedTimeline::at($seed['createdAt']);

      $team = new TeamRecord();
      $team->id = $seed['id'];
      $team->organization = $organization;
      $team->name = $seed['name'];
      $team->description = $seed['description'];
      $team->createdAt = $createdAt;
      $team->updatedAt = $createdAt;
      $manager->persist($team);
      $this->addReference($seed['reference'], $team);

      foreach ($seed['members'] as $teamMemberSeed) {
        $teamMember = new TeamMemberRecord();
        $teamMember->team = $team;
        $teamMember->member = $membersByReference[$teamMemberSeed['reference']];
        $teamMember->role = $teamMemberSeed['role'];
        $teamMember->addedAt = $createdAt;
        $manager->persist($teamMember);
      }
    }

    $invitation = new OrganizationInvitationRecord();
    $invitation->id = '591ba6c4-0d03-4bf3-a15f-22653463478c';
    $invitation->organization = $organization;
    $invitation->email = 'invitee@fireguard.local';
    $invitation->tokenHash = hash('sha256', 'organization-seed-invitation');
    $invitation->invitedByUserId = self::OWNER_USER_ID;
    $invitation->status = OrganizationInvitationStatus::PENDING->value;
    $invitation->expiresAt = SeedTimeline::at('2026-04-01T09:00:00+00:00');
    $invitation->createdAt = SeedTimeline::at('2026-03-01T09:00:00+00:00');
    $invitation->updatedAt = SeedTimeline::at('2026-03-01T09:00:00+00:00');
    $manager->persist($invitation);
    $this->addReference(self::INVITATION_REFERENCE, $invitation);

    $invitationAssignment = new OrganizationInvitationRoleRecord();
    $invitationAssignment->invitation = $invitation;
    $invitationAssignment->role = $memberRole;
    $invitationAssignment->assignedAt = SeedTimeline::at('2026-03-01T09:05:00+00:00');
    $manager->persist($invitationAssignment);

    // An accepted and an expired invitation, so the invitation list is not a
    // single pending row: the status filter and the "resend" affordance both
    // need a non-pending example to be exercised at all.
    $acceptedInvitation = new OrganizationInvitationRecord();
    $acceptedInvitation->id = '531fb8e7-2b90-474e-9410-168ec1acf835';
    $acceptedInvitation->organization = $organization;
    $acceptedInvitation->email = 'nadia.haddad@fireguard.local';
    $acceptedInvitation->tokenHash = hash('sha256', 'organization-seed-invitation-accepted');
    $acceptedInvitation->invitedByUserId = self::OWNER_USER_ID;
    $acceptedInvitation->acceptedByUserId = '21434c1d-0e91-4c89-a3bf-8f67b2d61f9d';
    $acceptedInvitation->status = OrganizationInvitationStatus::ACCEPTED->value;
    $acceptedInvitation->expiresAt = SeedTimeline::at('2026-04-05T09:00:00+00:00');
    $acceptedInvitation->acceptedAt = SeedTimeline::at('2026-03-10T09:00:00+00:00');
    $acceptedInvitation->createdAt = SeedTimeline::at('2026-03-06T09:00:00+00:00');
    $acceptedInvitation->updatedAt = SeedTimeline::at('2026-03-10T09:00:00+00:00');
    $manager->persist($acceptedInvitation);

    $acceptedInvitationAssignment = new OrganizationInvitationRoleRecord();
    $acceptedInvitationAssignment->invitation = $acceptedInvitation;
    $acceptedInvitationAssignment->role = $memberRole;
    $acceptedInvitationAssignment->assignedAt = SeedTimeline::at('2026-03-06T09:05:00+00:00');
    $manager->persist($acceptedInvitationAssignment);

    $expiredInvitation = new OrganizationInvitationRecord();
    $expiredInvitation->id = 'ab4df357-3b41-4f78-84fa-5f99109fd5eb';
    $expiredInvitation->organization = $organization;
    $expiredInvitation->email = 'contractor@safecheck.example';
    $expiredInvitation->tokenHash = hash('sha256', 'organization-seed-invitation-expired');
    $expiredInvitation->invitedByUserId = self::OWNER_USER_ID;
    $expiredInvitation->status = OrganizationInvitationStatus::EXPIRED->value;
    $expiredInvitation->expiresAt = SeedTimeline::at('2026-02-20T09:00:00+00:00');
    $expiredInvitation->createdAt = SeedTimeline::at('2026-02-06T09:00:00+00:00');
    $expiredInvitation->updatedAt = SeedTimeline::at('2026-02-20T09:00:00+00:00');
    $manager->persist($expiredInvitation);

    $expiredInvitationAssignment = new OrganizationInvitationRoleRecord();
    $expiredInvitationAssignment->invitation = $expiredInvitation;
    $expiredInvitationAssignment->role = $inspectorRole;
    $expiredInvitationAssignment->assignedAt = SeedTimeline::at('2026-02-06T09:05:00+00:00');
    $manager->persist($expiredInvitationAssignment);

    $this->loadSecondaryOrganizations($manager);

    $manager->flush();
  }

  /**
   * Method bulkMemberReference.
   *
   * @since 1.2.0
   *
   * @param int $index the bulk member index, `0` to `BULK_MEMBER_COUNT - 1`
   *
   * @return string the fixture reference name
   */
  public static function bulkMemberReference(int $index): string
  {
    return sprintf('organization-seed-bulk-member-%02d', $index);
  }

  /**
   * Method loadSecondaryOrganizations.
   *
   * @since 1.3.0
   *
   * @param ObjectManager $manager the object manager
   */
  private function loadSecondaryOrganizations(ObjectManager $manager): void
  {
    foreach (self::SECONDARY_ORGANIZATION_SEEDS as $seed) {
      $createdAt = SeedTimeline::at($seed['createdAt']);
      $ownerId = SeedUuid::from($seed['ownerReference']);

      $organization = new OrganizationRecord();
      $organization->id = SeedUuid::from($seed['reference']);
      $organization->name = $seed['name'];
      $organization->slug = $seed['slug'];
      $organization->description = '' === $seed['description'] ? null : $seed['description'];
      $organization->ownerUserId = $ownerId;
      $organization->createdByUserId = $ownerId;
      $organization->status = $seed['status'];
      $organization->isActive = OrganizationStatus::ACTIVE->value === $seed['status'];
      $organization->country = $seed['country'];
      $organization->legalType = $seed['legalType'];
      $organization->planId = $seed['planId'];
      $organization->createdAt = $createdAt;
      $organization->updatedAt = $createdAt;
      $manager->persist($organization);
      $this->addReference($seed['reference'], $organization);

      $adminRole = new OrganizationRoleRecord();
      $adminRole->id = SeedUuid::from(sprintf('%s:admin-role', $seed['reference']));
      $adminRole->organization = $organization;
      $adminRole->name = OrganizationSystemRoleCatalog::ADMIN;
      $adminRole->permissions = OrganizationSystemRoleCatalog::permissionsFor(OrganizationSystemRoleCatalog::ADMIN);
      $adminRole->description = 'Seeded administrator role';
      $adminRole->isSystem = true;
      $adminRole->createdAt = $createdAt;
      $manager->persist($adminRole);

      $memberRole = new OrganizationRoleRecord();
      $memberRole->id = SeedUuid::from(sprintf('%s:member-role', $seed['reference']));
      $memberRole->organization = $organization;
      $memberRole->name = OrganizationSystemRoleCatalog::MEMBER;
      $memberRole->permissions = OrganizationSystemRoleCatalog::permissionsFor(OrganizationSystemRoleCatalog::MEMBER);
      $memberRole->description = 'Seeded member role';
      $memberRole->isSystem = true;
      $memberRole->createdAt = $createdAt;
      $manager->persist($memberRole);

      $ownerMember = new OrganizationMemberRecord();
      $ownerMember->id = SeedUuid::from(sprintf('%s:owner-member', $seed['reference']));
      $ownerMember->organization = $organization;
      $ownerMember->userId = $ownerId;
      $ownerMember->isActive = true;
      $ownerMember->joinedAt = $createdAt;
      $manager->persist($ownerMember);

      $ownerAssignment = new OrganizationMemberRoleRecord();
      $ownerAssignment->member = $ownerMember;
      $ownerAssignment->role = $adminRole;
      $ownerAssignment->assignedAt = $createdAt;
      $manager->persist($ownerAssignment);

      $extraIndexes = '' === $seed['extraMemberIndexes'] ? [] : explode(',', $seed['extraMemberIndexes']);
      foreach ($extraIndexes as $bulkIndexString) {
        $bulkIndex = (int) $bulkIndexString;

        $extraMember = new OrganizationMemberRecord();
        $extraMember->id = SeedUuid::from(sprintf('%s:extra-member:%d', $seed['reference'], $bulkIndex));
        $extraMember->organization = $organization;
        $extraMember->userId = UserFixtures::bulkStaffId($bulkIndex);
        $extraMember->isActive = true;
        $extraMember->joinedAt = $createdAt->modify('+1 day');
        $manager->persist($extraMember);

        $extraAssignment = new OrganizationMemberRoleRecord();
        $extraAssignment->member = $extraMember;
        $extraAssignment->role = $memberRole;
        $extraAssignment->assignedAt = $createdAt->modify('+1 day');
        $manager->persist($extraAssignment);
      }
    }
  }
}
