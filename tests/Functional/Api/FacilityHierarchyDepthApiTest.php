<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Infrastructure\DataFixtures\PlanFixtures;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

use function json_decode;
use function json_encode;

/**
 * Test FacilityHierarchyDepthApiTest.
 *
 * Creates a facility chain up to the configured FACILITY_MAX_DEPTH cap
 * through the real HTTP surface, then asserts the next level is refused
 * with the mapped 400 and the FacilityHierarchyException::maxDepthExceeded
 * message.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityHierarchyDepthApiTest extends WebTestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655448900';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655448901';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655448902';

  private const string ROLE_ID = '550e8400-e29b-41d4-a716-446655448903';

  private const int MAX_DEPTH = 8;

  #[Test]
  public function testCreatingBeyondTheDepthCapIsRefused(): void
  {
    $this->seedAuthorizedMember();

    // The token set by loginUser() does not reliably survive a second
    // request on a reused client (see OrganizationApiTest), so every
    // request in this chain uses its own freshly authenticated client.
    $parentFacilityId = null;
    for ($level = 1; $level <= self::MAX_DEPTH; ++$level) {
      $response = $this->createFacility('Depth Level ' . $level, $parentFacilityId);

      self::assertSame(
        201,
        $response->getStatusCode(),
        'Creating facility at level ' . $level . ' (within the cap) should succeed. Response: ' . $response->getContent(),
      );

      $decoded = json_decode($response->getContent() ?: '{}', true);
      self::assertIsArray($decoded);
      self::assertIsString($decoded['id'] ?? null);
      $parentFacilityId = $decoded['id'];
    }

    // One level beyond the cap must be refused.
    $response = $this->createFacility('Depth Level ' . (self::MAX_DEPTH + 1), $parentFacilityId);

    self::assertSame(
      400,
      $response->getStatusCode(),
      'Creating a facility past the depth cap should be refused with 400. Response: ' . $response->getContent(),
    );

    $decoded = json_decode($response->getContent() ?: '{}', true);
    self::assertIsArray($decoded);
    $detail = $decoded['detail'] ?? $decoded['hydra:description'] ?? null;
    self::assertSame('Facility hierarchy depth cap of ' . self::MAX_DEPTH . ' levels exceeded.', $detail);
  }

  private function createFacility(string $name, ?string $parentFacilityId): Response
  {
    static::ensureKernelShutdown();
    $client = static::createClient();
    $this->loginAsAuthorizedMember($client);

    $client->request(
      'POST',
      '/api/organizations/' . self::ORGANIZATION_ID . '/facilities',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'type' => 'zone',
        'name' => $name,
        'parentFacilityId' => $parentFacilityId,
      ]),
    );

    return $client->getResponse();
  }

  private function seedAuthorizedMember(): void
  {
    static::ensureKernelShutdown();
    static::createClient();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $existingOrganization = $entityManager->find(OrganizationRecord::class, self::ORGANIZATION_ID);
    if ($existingOrganization instanceof OrganizationRecord) {
      $entityManager->remove($existingOrganization);
      $entityManager->flush();
    }

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Facility Depth Cap Test';
    $organization->slug = 'facility-depth-cap-test-' . self::ORGANIZATION_ID;
    // The Free plan caps facilities at 2, far below the 8-level depth chain
    // this test creates; the Max plan's cap of 125 keeps the quota check
    // out of the way so only the depth cap is under test.
    $organization->planId = PlanFixtures::MAX_PLAN_ID;
    $organization->ownerUserId = self::USER_ID;
    $organization->createdByUserId = self::USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $role = new OrganizationRoleRecord();
    $role->id = self::ROLE_ID;
    $role->organization = $organization;
    $role->name = 'full-access-tester';
    $role->permissions = ['*'];
    $role->description = 'Functional-test-only role granting every permission.';
    $role->isSystem = false;
    $role->createdAt = $now;
    $entityManager->persist($role);

    $member = new OrganizationMemberRecord();
    $member->id = self::MEMBER_ID;
    $member->organization = $organization;
    $member->userId = self::USER_ID;
    $member->isActive = true;
    $member->joinedAt = $now;
    $entityManager->persist($member);

    $roleAssignment = new OrganizationMemberRoleRecord();
    $roleAssignment->member = $member;
    $roleAssignment->role = $role;
    $roleAssignment->assignedAt = $now;
    $entityManager->persist($roleAssignment);

    $entityManager->flush();
  }

  private function loginAsAuthorizedMember(KernelBrowser $client): void
  {
    $client->loginUser(new SecurityUser(
      id: self::USER_ID,
      email: 'facility-depth-cap-test@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
    ), 'api');
  }
}
