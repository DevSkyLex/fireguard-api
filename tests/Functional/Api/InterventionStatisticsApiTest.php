<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_decode;

/**
 * Test InterventionStatisticsApiTest.
 *
 * Contract tests for GET /api/interventions/statistics?organization=<IRI> —
 * the whole-organization KPI snapshot for the list page and the kanban. The
 * denial paths are the point: 401 unauthenticated, 400 without the
 * `organization` filter, 403 for a member without
 * `organization.interventions.read`, and 403 for a caller who is not a
 * member of the requested organization at all — this endpoint is
 * organization-scoped through a query parameter, not a path id, so there is
 * no separate "record" whose existence a 404 would hide; it mirrors
 * `InterventionProvider`'s list path, which resolves both cases through the
 * same `organization.interventions.read` check and therefore the same 403.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionStatisticsApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655470001';

  private const string ADMIN_USER_ID = '550e8400-e29b-41d4-a716-446655470002';

  private const string PLAIN_MEMBER_USER_ID = '550e8400-e29b-41d4-a716-446655470003';

  private const string OUTSIDER_USER_ID = '550e8400-e29b-41d4-a716-446655470004';

  private const string SITE_ID = '550e8400-e29b-41d4-a716-446655470010';

  private const string RESPONSIBLE_MEMBER_ID = '550e8400-e29b-41d4-a716-446655470011';

  #[Test]
  public function testGetInterventionStatisticsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/interventions/statistics?organization=' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /interventions/statistics endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /interventions/statistics, got ' . $statusCode,
    );
  }

  #[Test]
  public function testGetInterventionStatisticsRequiresOrganizationFilter(): void
  {
    $client = static::createClient();
    $this->seedOrganization();

    $this->loginAs($client, self::ADMIN_USER_ID, 'stats-admin@example.com');

    $client->request('GET', '/api/interventions/statistics');

    self::assertSame(
      expected: 400,
      actual: $client->getResponse()->getStatusCode(),
      message: 'Missing organization filter must yield 400.',
    );
  }

  #[Test]
  public function testGetInterventionStatisticsRejectsMemberWithoutPermission(): void
  {
    $client = static::createClient();
    $this->seedOrganization();

    $this->loginAs($client, self::PLAIN_MEMBER_USER_ID, 'stats-plain-member@example.com');

    $client->request('GET', '/api/interventions/statistics?organization=/api/organizations/' . self::ORGANIZATION_ID);

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A member without organization.interventions.read must get 403.',
    );
  }

  #[Test]
  public function testGetInterventionStatisticsRejectsNonMemberOfTheOrganization(): void
  {
    $client = static::createClient();
    $this->seedOrganization();

    $this->loginAs($client, self::OUTSIDER_USER_ID, 'stats-outsider@example.com');

    $client->request('GET', '/api/interventions/statistics?organization=/api/organizations/' . self::ORGANIZATION_ID);

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A non-member of the organization must get 403 (same authorization path as the list endpoint).',
    );
  }

  #[Test]
  public function testGetInterventionStatisticsReturnsTheFullShapeForAnAuthorizedAdmin(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacility();
    $this->seedInterventions();

    $this->loginAs($client, self::ADMIN_USER_ID, 'stats-admin@example.com');

    $client->request('GET', '/api/interventions/statistics?organization=/api/organizations/' . self::ORGANIZATION_ID, server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), 'Statistics request should succeed. Response: ' . $response->getContent());

    $decoded = json_decode($response->getContent() ?: '{}', true);
    self::assertIsArray($decoded);

    self::assertSame(7, $decoded['total']);

    // All seven status keys, zero included, regardless of what was seeded.
    self::assertSame([
      'draft' => 1,
      'planned' => 1,
      'in_progress' => 1,
      'submitted' => 1,
      'changes_requested' => 1,
      'published' => 1,
      'abandoned' => 1,
    ], $decoded['byStatus']);

    // All four priority keys, zero included.
    self::assertSame([
      'low' => 2,
      'normal' => 3,
      'high' => 1,
      'urgent' => 1,
    ], $decoded['byPriority']);

    // Only the in_progress intervention is both non-terminal and past due;
    // the abandoned one's past dueAt must NOT count (terminal status).
    self::assertSame(1, $decoded['overdue']);

    // Only the planned intervention falls within the 48h due-soon window;
    // the overdue in_progress one is in the past, not the window.
    self::assertSame(1, $decoded['dueSoon']);

    self::assertIsArray($decoded['bySite']);
    self::assertCount(1, $decoded['bySite']);
    $site = $decoded['bySite'][0];
    self::assertIsArray($site);
    self::assertSame(self::SITE_ID, $site['siteId']);
    self::assertSame('Statistics Test Site', $site['siteName']);
    self::assertSame(2, $site['count']);

    self::assertIsArray($decoded['byResponsible']);
    self::assertCount(1, $decoded['byResponsible']);
    $responsible = $decoded['byResponsible'][0];
    self::assertIsArray($responsible);
    self::assertSame(self::RESPONSIBLE_MEMBER_ID, $responsible['memberId']);
    self::assertSame(2, $responsible['count']);

    // The one published intervention was created on day T and updated
    // (immutably frozen from then on) exactly 3 days later. json_decode
    // hands back an int when the API serializes a whole float as `3` rather
    // than `3.0` — the numeric value is what the contract promises, not the
    // PHP type json_decode happens to infer.
    self::assertIsNumeric($decoded['averagePublicationDays']);
    self::assertSame(3.0, (float) $decoded['averagePublicationDays']);
  }

  /**
   * Method loginAs.
   *
   * Authenticates the client against the stateless `api` firewall (the token
   * is stored in the container, not the session).
   *
   * @param KernelBrowser $client the test client
   * @param string $userId the user id to authenticate as
   * @param string $email the user email
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

  /**
   * Method seedOrganization.
   *
   * Seeds (idempotently) an organization with an admin member (permissions
   * `['*']`) and a plain member (`organization.read` only, no
   * `organization.interventions.read`).
   */
  private function seedOrganization(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $existing = $entityManager->find(OrganizationRecord::class, self::ORGANIZATION_ID);
    if ($existing instanceof OrganizationRecord) {
      $entityManager->remove($existing);
      $entityManager->flush();
    }

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Statistics Test Org';
    $organization->slug = 'statistics-test-org-' . self::ORGANIZATION_ID;
    $organization->ownerUserId = self::ADMIN_USER_ID;
    $organization->createdByUserId = self::ADMIN_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $adminRole = new OrganizationRoleRecord();
    $adminRole->id = '550e8400-e29b-41d4-a716-446655470020';
    $adminRole->organization = $organization;
    $adminRole->name = 'stats-full-access';
    $adminRole->permissions = ['*'];
    $adminRole->description = 'Functional-test-only role granting every permission.';
    $adminRole->isSystem = false;
    $adminRole->createdAt = $now;
    $entityManager->persist($adminRole);

    $readOnlyRole = new OrganizationRoleRecord();
    $readOnlyRole->id = '550e8400-e29b-41d4-a716-446655470021';
    $readOnlyRole->organization = $organization;
    $readOnlyRole->name = 'stats-read-only';
    $readOnlyRole->permissions = ['organization.read'];
    $readOnlyRole->description = 'Functional-test-only role without intervention read access.';
    $readOnlyRole->isSystem = false;
    $readOnlyRole->createdAt = $now;
    $entityManager->persist($readOnlyRole);

    $adminMember = new OrganizationMemberRecord();
    $adminMember->id = '550e8400-e29b-41d4-a716-446655470022';
    $adminMember->organization = $organization;
    $adminMember->userId = self::ADMIN_USER_ID;
    $adminMember->isActive = true;
    $adminMember->joinedAt = $now;
    $entityManager->persist($adminMember);

    $adminAssignment = new OrganizationMemberRoleRecord();
    $adminAssignment->member = $adminMember;
    $adminAssignment->role = $adminRole;
    $adminAssignment->assignedAt = $now;
    $entityManager->persist($adminAssignment);

    $plainMember = new OrganizationMemberRecord();
    $plainMember->id = '550e8400-e29b-41d4-a716-446655470023';
    $plainMember->organization = $organization;
    $plainMember->userId = self::PLAIN_MEMBER_USER_ID;
    $plainMember->isActive = true;
    $plainMember->joinedAt = $now;
    $entityManager->persist($plainMember);

    $plainAssignment = new OrganizationMemberRoleRecord();
    $plainAssignment->member = $plainMember;
    $plainAssignment->role = $readOnlyRole;
    $plainAssignment->assignedAt = $now;
    $entityManager->persist($plainAssignment);

    $responsibleMember = new OrganizationMemberRecord();
    $responsibleMember->id = self::RESPONSIBLE_MEMBER_ID;
    $responsibleMember->organization = $organization;
    $responsibleMember->userId = '550e8400-e29b-41d4-a716-446655470030';
    $responsibleMember->isActive = true;
    $responsibleMember->joinedAt = $now;
    $entityManager->persist($responsibleMember);

    $entityManager->flush();
  }

  /**
   * Method seedFacility.
   *
   * Seeds (idempotently) a single facility used as an intervention `siteId`,
   * proving the statistics endpoint's `bySite` name resolution.
   */
  private function seedFacility(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $existing = $entityManager->find(FacilityRecord::class, self::SITE_ID);
    if ($existing instanceof FacilityRecord) {
      $entityManager->remove($existing);
      $entityManager->flush();
    }

    /** @var OrganizationRecord $organization */
    $organization = $entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $facility = new FacilityRecord();
    $facility->id = self::SITE_ID;
    $facility->organization = $organization;
    $facility->type = 'site';
    $facility->name = 'Statistics Test Site';
    $facility->status = 'active';
    $facility->createdAt = $now;
    $facility->updatedAt = $now;
    $entityManager->persist($facility);
    $entityManager->flush();
  }

  /**
   * Method seedInterventions.
   *
   * Seeds (idempotently) one intervention per `InterventionStatus`, so
   * `byStatus`/`byPriority` cover every key. Two interventions share
   * {@see self::SITE_ID}; two share {@see self::RESPONSIBLE_MEMBER_ID}.
   * `dueAt` is set relative to the real clock (the handler's `ClockPort`
   * resolves to the system clock in the test kernel) so the overdue/due-soon
   * windows are exercised deterministically without freezing time.
   */
  private function seedInterventions(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    foreach ($this->interventionSeeds() as $seed) {
      $existing = $entityManager->find(InterventionRecord::class, $seed['id']);
      if ($existing instanceof InterventionRecord) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }
    }

    /** @var OrganizationRecord $organization */
    $organization = $entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    foreach ($this->interventionSeeds() as $index => $seed) {
      $intervention = new InterventionRecord();
      $intervention->id = $seed['id'];
      $intervention->organization = $organization;
      $intervention->type = 'site_setup';
      $intervention->name = 'Statistics Test Intervention ' . $index;
      $intervention->number = 100 + $index;
      $intervention->status = $seed['status'];
      $intervention->priority = $seed['priority'];
      $intervention->siteId = $seed['siteId'];
      $intervention->responsibleId = $seed['responsibleId'];
      $intervention->dueAt = $seed['dueAt'];
      $intervention->createdAt = $seed['createdAt'];
      $intervention->updatedAt = $seed['updatedAt'];
      $entityManager->persist($intervention);
    }

    $entityManager->flush();
  }

  /**
   * Method interventionSeeds.
   *
   * @return list<array{id: string, status: string, priority: string, siteId: ?string, responsibleId: ?string, dueAt: ?DateTimeImmutable, createdAt: DateTimeImmutable, updatedAt: DateTimeImmutable}>
   */
  private function interventionSeeds(): array
  {
    $now = new DateTimeImmutable();
    $createdAt = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    return [
      [
        'id' => '550e8400-e29b-41d4-a716-446655470101',
        'status' => 'draft',
        'priority' => 'low',
        'siteId' => self::SITE_ID,
        'responsibleId' => null,
        'dueAt' => null,
        'createdAt' => $createdAt,
        'updatedAt' => $createdAt,
      ],
      [
        'id' => '550e8400-e29b-41d4-a716-446655470102',
        'status' => 'planned',
        'priority' => 'normal',
        'siteId' => self::SITE_ID,
        'responsibleId' => self::RESPONSIBLE_MEMBER_ID,
        'dueAt' => $now->modify('+24 hours'),
        'createdAt' => $createdAt,
        'updatedAt' => $createdAt,
      ],
      [
        'id' => '550e8400-e29b-41d4-a716-446655470103',
        'status' => 'in_progress',
        'priority' => 'high',
        'siteId' => null,
        'responsibleId' => self::RESPONSIBLE_MEMBER_ID,
        'dueAt' => $now->modify('-24 hours'),
        'createdAt' => $createdAt,
        'updatedAt' => $createdAt,
      ],
      [
        'id' => '550e8400-e29b-41d4-a716-446655470104',
        'status' => 'submitted',
        'priority' => 'urgent',
        'siteId' => null,
        'responsibleId' => null,
        'dueAt' => null,
        'createdAt' => $createdAt,
        'updatedAt' => $createdAt,
      ],
      [
        'id' => '550e8400-e29b-41d4-a716-446655470105',
        'status' => 'changes_requested',
        'priority' => 'normal',
        'siteId' => null,
        'responsibleId' => null,
        'dueAt' => null,
        'createdAt' => $createdAt,
        'updatedAt' => $createdAt,
      ],
      [
        'id' => '550e8400-e29b-41d4-a716-446655470106',
        'status' => 'published',
        'priority' => 'normal',
        'siteId' => null,
        'responsibleId' => null,
        'dueAt' => null,
        'createdAt' => $createdAt,
        // published is terminal/immutable, so updatedAt never moves again —
        // it IS the publish instant, exactly 3 days after creation here.
        'updatedAt' => $createdAt->modify('+3 days'),
      ],
      [
        'id' => '550e8400-e29b-41d4-a716-446655470107',
        'status' => 'abandoned',
        'priority' => 'low',
        'siteId' => null,
        'responsibleId' => null,
        // Far in the past, but terminal: must NOT count toward `overdue`.
        'dueAt' => $now->modify('-100 hours'),
        'createdAt' => $createdAt,
        'updatedAt' => $createdAt,
      ],
    ];
  }
}
