<?php

declare(strict_types=1);

namespace Organization\Infrastructure\DataFixtures;

use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\{Fixture, FixtureGroupInterface};
use Doctrine\Persistence\ObjectManager;
use Organization\Domain\Catalog\OrganizationSystemRoleCatalog;
use Organization\Domain\ValueObject\{OrganizationInvitationStatus, OrganizationStatus};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationInvitationRecord, OrganizationInvitationRoleRecord, OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};

use function hash;

final class OrganizationFixtures extends Fixture implements FixtureGroupInterface
{
  public const string ORGANIZATION_REFERENCE = 'organization-seed';

  public const string ADMIN_ROLE_REFERENCE = 'organization-seed-admin-role';

  public const string MEMBER_ROLE_REFERENCE = 'organization-seed-member-role';

  public const string INSPECTOR_ROLE_REFERENCE = 'organization-seed-inspector-role';

  public const string OWNER_MEMBER_REFERENCE = 'organization-seed-owner-member';

  public const string INSPECTOR_MEMBER_REFERENCE = 'organization-seed-inspector-member';

  public const string INVITATION_REFERENCE = 'organization-seed-invitation';

  public const string ORGANIZATION_ID = '11111111-1111-4111-8111-111111111111';

  private const string OWNER_USER_ID = 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890';

  private const string INSPECTOR_USER_ID = 'b2c3d4e5-f6a7-4901-8cde-f23456789012';

  public static function getGroups(): array
  {
    return ['organization', 'main-seed'];
  }

  public function load(ObjectManager $manager): void
  {
    $organizationCreatedAt = new DateTimeImmutable('2026-02-01T09:00:00+00:00');
    $roleCreatedAt = new DateTimeImmutable('2026-02-01T09:15:00+00:00');
    $memberJoinedAt = new DateTimeImmutable('2026-02-01T10:00:00+00:00');

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
    $adminRole->id = '11111111-1111-4111-8111-111111111112';
    $adminRole->organization = $organization;
    $adminRole->name = OrganizationSystemRoleCatalog::ADMIN;
    $adminRole->permissions = OrganizationSystemRoleCatalog::permissionsFor(OrganizationSystemRoleCatalog::ADMIN);
    $adminRole->description = 'Seeded administrator role';
    $adminRole->isSystem = true;
    $adminRole->createdAt = $roleCreatedAt;
    $manager->persist($adminRole);
    $this->addReference(self::ADMIN_ROLE_REFERENCE, $adminRole);

    $memberRole = new OrganizationRoleRecord();
    $memberRole->id = '11111111-1111-4111-8111-111111111113';
    $memberRole->organization = $organization;
    $memberRole->name = OrganizationSystemRoleCatalog::MEMBER;
    $memberRole->permissions = OrganizationSystemRoleCatalog::permissionsFor(OrganizationSystemRoleCatalog::MEMBER);
    $memberRole->description = 'Seeded member role';
    $memberRole->isSystem = true;
    $memberRole->createdAt = $roleCreatedAt;
    $manager->persist($memberRole);
    $this->addReference(self::MEMBER_ROLE_REFERENCE, $memberRole);

    $inspectorRole = new OrganizationRoleRecord();
    $inspectorRole->id = '11111111-1111-4111-8111-111111111114';
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
    $inspectorRole->createdAt = new DateTimeImmutable('2026-02-01T09:30:00+00:00');
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
    $inspectorMember->joinedAt = new DateTimeImmutable('2026-02-02T10:00:00+00:00');
    $manager->persist($inspectorMember);
    $this->addReference(self::INSPECTOR_MEMBER_REFERENCE, $inspectorMember);

    $inspectorAssignment = new OrganizationMemberRoleRecord();
    $inspectorAssignment->member = $inspectorMember;
    $inspectorAssignment->role = $inspectorRole;
    $inspectorAssignment->assignedAt = new DateTimeImmutable('2026-02-02T10:05:00+00:00');
    $manager->persist($inspectorAssignment);

    $invitation = new OrganizationInvitationRecord();
    $invitation->id = '11111111-1111-4111-8111-111111111117';
    $invitation->organization = $organization;
    $invitation->email = 'invitee@fireguard.local';
    $invitation->tokenHash = hash('sha256', 'organization-seed-invitation');
    $invitation->invitedByUserId = self::OWNER_USER_ID;
    $invitation->status = OrganizationInvitationStatus::PENDING->value;
    $invitation->expiresAt = new DateTimeImmutable('2026-04-01T09:00:00+00:00');
    $invitation->createdAt = new DateTimeImmutable('2026-03-01T09:00:00+00:00');
    $invitation->updatedAt = new DateTimeImmutable('2026-03-01T09:00:00+00:00');
    $manager->persist($invitation);
    $this->addReference(self::INVITATION_REFERENCE, $invitation);

    $invitationAssignment = new OrganizationInvitationRoleRecord();
    $invitationAssignment->invitation = $invitation;
    $invitationAssignment->role = $memberRole;
    $invitationAssignment->assignedAt = new DateTimeImmutable('2026-03-01T09:05:00+00:00');
    $manager->persist($invitationAssignment);

    $manager->flush();
  }
}
