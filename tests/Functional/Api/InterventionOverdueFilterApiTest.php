<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function basename;
use function http_build_query;
use function is_array;
use function is_string;
use function json_decode;
use function str_contains;

use const DATE_ATOM;

/**
 * Test InterventionOverdueFilterApiTest.
 *
 * Contract tests for `GET /api/interventions?…&due=overdue` — the list
 * endpoint's overdue shortcut must apply the exact same "overdue" definition
 * `GET /interventions/statistics` uses for its `overdue` count
 * (`InterventionStatus::closedValues()`, i.e. `dueAt` in the past AND status
 * not `published`/`abandoned`), so the KPI tile and the list it links to
 * never disagree. Denial paths (401/403/404) already exist for `GET
 * /interventions` elsewhere and are not duplicated here.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionOverdueFilterApiTest extends WebTestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655480001';

  private const string ADMIN_USER_ID = '550e8400-e29b-41d4-a716-446655480002';

  #[Test]
  public function testOverdueFilterExcludesPublishedAndAbandonedInterventionsWithPastDueDates(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $ids = $this->seedInterventions();

    $client->loginUser(new SecurityUser(
      id: self::ADMIN_USER_ID,
      email: 'overdue-admin@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
    ), 'api');

    $client->request('GET', '/api/interventions?' . http_build_query([
      'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
      'due' => 'overdue',
    ]), server: ['HTTP_ACCEPT' => 'application/ld+json']);

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), 'Overdue-filtered request should succeed. Response: ' . $response->getContent());

    $listedIds = $this->memberIds($this->decode($response->getContent() ?: '{}'));

    self::assertContains(
      $ids['overdue_in_progress'],
      $listedIds,
      'A non-terminal intervention past its due date must be listed as overdue.',
    );
    self::assertNotContains(
      $ids['not_yet_due_planned'],
      $listedIds,
      'An intervention not yet due must be excluded.',
    );
    self::assertNotContains(
      $ids['past_due_published'],
      $listedIds,
      'A published (terminal) intervention must be excluded despite its past due date.',
    );
    self::assertNotContains(
      $ids['past_due_abandoned'],
      $listedIds,
      'An abandoned (terminal) intervention must be excluded despite its past due date.',
    );
  }

  #[Test]
  public function testOverdueFilterComposesWithTheCallerSuppliedDueAtBounds(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $ids = $this->seedInterventions();

    $client->loginUser(new SecurityUser(
      id: self::ADMIN_USER_ID,
      email: 'overdue-admin@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
    ), 'api');

    $now = new DateTimeImmutable();

    // Narrows the overdue population to the last 48 hours: the -24h
    // in_progress intervention must survive, the -240h one must not.
    $client->request('GET', '/api/interventions?' . http_build_query([
      'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
      'due' => 'overdue',
      'dueAtAfter' => $now->modify('-48 hours')->format(DATE_ATOM),
    ]), server: ['HTTP_ACCEPT' => 'application/ld+json']);

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), 'Composed overdue + dueAtAfter request should succeed. Response: ' . $response->getContent());

    $listedIds = $this->memberIds($this->decode($response->getContent() ?: '{}'));

    self::assertContains(
      $ids['overdue_in_progress'],
      $listedIds,
      'An overdue intervention inside the dueAtAfter bound must remain listed.',
    );
    self::assertNotContains(
      $ids['far_past_due_in_progress'],
      $listedIds,
      'An overdue intervention outside the dueAtAfter bound must be excluded — the two filters compose.',
    );
  }

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
    $organization->name = 'Overdue Filter Test Org';
    $organization->slug = 'overdue-filter-test-org-' . self::ORGANIZATION_ID;
    $organization->ownerUserId = self::ADMIN_USER_ID;
    $organization->createdByUserId = self::ADMIN_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $adminRole = new OrganizationRoleRecord();
    $adminRole->id = '550e8400-e29b-41d4-a716-446655480020';
    $adminRole->organization = $organization;
    $adminRole->name = 'overdue-full-access';
    $adminRole->permissions = ['*'];
    $adminRole->description = 'Functional-test-only role granting every permission.';
    $adminRole->isSystem = false;
    $adminRole->createdAt = $now;
    $entityManager->persist($adminRole);

    $adminMember = new OrganizationMemberRecord();
    $adminMember->id = '550e8400-e29b-41d4-a716-446655480022';
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

    $entityManager->flush();
  }

  /**
   * Seeds (idempotently) one intervention per overdue-relevant case, writing
   * directly through the entity manager — like `InterventionStatisticsApiTest`
   * — so a terminal status (`published`, `abandoned`) can carry a past due
   * date without going through the workflow transitions that would normally
   * forbid it.
   *
   * @return array<string, string> the seeded intervention ids, keyed by case
   */
  private function seedInterventions(): array
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable();
    $createdAt = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $seeds = [
      'overdue_in_progress' => [
        'id' => '550e8400-e29b-41d4-a716-446655480101',
        'status' => 'in_progress',
        'dueAt' => $now->modify('-24 hours'),
      ],
      'not_yet_due_planned' => [
        'id' => '550e8400-e29b-41d4-a716-446655480102',
        'status' => 'planned',
        'dueAt' => $now->modify('+24 hours'),
      ],
      'past_due_published' => [
        'id' => '550e8400-e29b-41d4-a716-446655480103',
        'status' => 'published',
        'dueAt' => $now->modify('-24 hours'),
      ],
      'past_due_abandoned' => [
        'id' => '550e8400-e29b-41d4-a716-446655480104',
        'status' => 'abandoned',
        'dueAt' => $now->modify('-100 hours'),
      ],
      'far_past_due_in_progress' => [
        'id' => '550e8400-e29b-41d4-a716-446655480105',
        'status' => 'in_progress',
        'dueAt' => $now->modify('-240 hours'),
      ],
    ];

    foreach ($seeds as $seed) {
      $existing = $entityManager->find(InterventionRecord::class, $seed['id']);
      if ($existing instanceof InterventionRecord) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }
    }

    /** @var OrganizationRecord $organization */
    $organization = $entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $ids = [];
    $number = 200;
    foreach ($seeds as $case => $seed) {
      $intervention = new InterventionRecord();
      $intervention->id = $seed['id'];
      $intervention->organization = $organization;
      $intervention->type = 'site_setup';
      $intervention->name = 'Overdue Filter Test Intervention — ' . $case;
      $intervention->number = $number++;
      $intervention->status = $seed['status'];
      $intervention->priority = 'normal';
      $intervention->siteId = null;
      $intervention->responsibleId = null;
      $intervention->dueAt = $seed['dueAt'];
      $intervention->createdAt = $createdAt;
      $intervention->updatedAt = $createdAt;
      $entityManager->persist($intervention);
      $ids[$case] = $seed['id'];
    }
    $entityManager->flush();

    return $ids;
  }

  /**
   * @return array<string, mixed>
   */
  private function decode(string $json): array
  {
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
      return [];
    }
    // Filter to ensure string keys for PHPStan.
    $result = [];
    foreach ($decoded as $key => $value) {
      if (is_string($key)) {
        $result[$key] = $value;
      }
    }

    return $result;
  }

  /**
   * Extracts the resource ids of a Hydra collection's members.
   *
   * @param array<string, mixed> $collection
   *
   * @return list<string>
   */
  private function memberIds(array $collection): array
  {
    $members = $collection['member'] ?? [];
    if (!is_array($members)) {
      return [];
    }
    $ids = [];
    foreach ($members as $member) {
      if (is_array($member)) {
        $id = $this->extractResourceId($member);
        if (is_string($id)) {
          $ids[] = $id;
        }
      }
    }

    return $ids;
  }

  /**
   * @param array<array-key, mixed> $data
   */
  private function extractResourceId(array $data): ?string
  {
    $id = $data['id'] ?? null;
    if (is_string($id) && '' !== $id) {
      return $id;
    }
    $iri = $data['@id'] ?? null;
    if (is_string($iri) && str_contains($iri, '/')) {
      $candidate = basename($iri);

      return '' !== $candidate ? $candidate : null;
    }

    return null;
  }
}
