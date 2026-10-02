<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

use function is_numeric;
use function json_encode;
use function uniqid;

/**
 * Test OrganizationMemberGrantBoundaryTest.
 *
 * Exercises role defaults and explicit roles through real bearer HTTP requests.
 *
 * @category E2E Tests
 */
final class OrganizationMemberGrantBoundaryTest extends OAuth2WebTestCase
{
  /**
   * @return iterable<string, array{string, string, int}>
   */
  public static function roleRequests(): iterable
  {
    foreach (['members', 'invitations'] as $operation) {
      yield $operation . '-omitted' => [$operation, 'omitted', 403];
      yield $operation . '-empty' => [$operation, 'empty', 403];
      yield $operation . '-permitted' => [$operation, 'permitted', 201];
      yield $operation . '-foreign' => [$operation, 'foreign', 404];
    }
  }

  #[Test]
  #[DataProvider('roleRequests')]
  public function resolvedRolesAlwaysRespectTheGrantCeiling(string $operation, string $roles, int $expectedStatus): void
  {
    $client = static::createClientWithFixtures();
    $ownerToken = $this->authenticateAsSeededAdmin($client);
    $organizationId = $this->createResource($client, $ownerToken, '/api/organizations', ['name' => 'Grant Boundary ' . uniqid()]);
    $managerRoleId = $this->createResource($client, $ownerToken, '/api/organizations/' . $organizationId . '/roles', [
      'name' => 'limited_manager', 'permissions' => ['organization.read', 'organization.members.manage'],
    ]);
    $permittedRoleId = $this->createResource($client, $ownerToken, '/api/organizations/' . $organizationId . '/roles', [
      'name' => 'read_only', 'permissions' => ['organization.read'],
    ]);
    $manager = $this->authenticateFreshUser($client);
    $memberId = $this->createResource($client, $ownerToken, '/api/organizations/' . $organizationId . '/members', [
      'userId' => $manager['userId'], 'roleIds' => [$managerRoleId],
    ]);
    $foreignOrganization = $this->createResource($client, $ownerToken, '/api/organizations', ['name' => 'Foreign Grant ' . uniqid()]);
    $foreignRoleId = $this->createResource($client, $ownerToken, '/api/organizations/' . $foreignOrganization . '/roles', [
      'name' => 'foreign_read', 'permissions' => ['organization.read'],
    ]);

    // Self-add must not acquire the broader implicit Member role merely
    // because its active membership already exists.
    $email = 'grant-recipient-' . uniqid() . '@example.test';
    $payload = 'members' === $operation ? ['userId' => $manager['userId']] : ['email' => $email];
    if ('omitted' !== $roles) {
      $payload['roleIds'] = match ($roles) {
        'empty' => [], 'permitted' => [$permittedRoleId], default => [$foreignRoleId],
      };
    }
    $client->request(
      'POST',
      '/api/organizations/' . $organizationId . '/' . $operation,
      server: $this->headers($manager['token']),
      content: json_encode($payload) ?: '',
    );
    self::assertSame($expectedStatus, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent() ?: '');

    /** @var EntityManagerInterface $main */
    $main = $client->getContainer()->get('doctrine.orm.main_entity_manager');
    if (201 !== $expectedStatus) {
      $assigned = $main->getConnection()->fetchOne('SELECT COUNT(*) FROM organization_member_roles WHERE member_id = ?', [$memberId]);
      $invitations = $main->getConnection()->fetchOne('SELECT COUNT(*) FROM organization_invitations WHERE organization_id = ? AND email = ?', [$organizationId, $email]);
      self::assertSame(1, is_numeric($assigned) ? (int) $assigned : null);
      self::assertSame(0, is_numeric($invitations) ? (int) $invitations : null);
    }
  }

  /**
   * @return iterable<string, array{string, bool}>
   */
  public static function retainedRoleReactivations(): iterable
  {
    foreach (['omitted', 'empty', 'reactivate'] as $operation) {
      yield $operation . '-limited-manager' => [$operation, false];
      yield $operation . '-owner' => [$operation, true];
    }
  }

  #[Test]
  #[DataProvider('retainedRoleReactivations')]
  public function retainedAdministrativeRolesRequireTheReactivatingActorsGrantCeiling(string $operation, bool $owner): void
  {
    $client = static::createClientWithFixtures();
    $ownerToken = $this->authenticateAsSeededAdmin($client);
    $organizationId = $this->createResource($client, $ownerToken, '/api/organizations', ['name' => 'Readmission Boundary ' . uniqid()]);
    $managerRoleId = $this->createResource($client, $ownerToken, '/api/organizations/' . $organizationId . '/roles', [
      'name' => 'limited_manager', 'permissions' => ['organization.read', 'organization.members.manage'],
    ]);
    $adminRoleId = $this->createResource($client, $ownerToken, '/api/organizations/' . $organizationId . '/roles', [
      'name' => 'former_admin', 'permissions' => ['organization.roles.manage'],
    ]);
    /** @var EntityManagerInterface $main */
    $main = $client->getContainer()->get('doctrine.orm.main_entity_manager');
    $defaultRoleId = $main->getConnection()->fetchOne('SELECT id FROM organization_roles WHERE organization_id = ? AND name = ?', [$organizationId, 'member']);
    self::assertIsString($defaultRoleId);
    $manager = $this->authenticateFreshUser($client);
    $this->createResource($client, $ownerToken, '/api/organizations/' . $organizationId . '/members', [
      'userId' => $manager['userId'], 'roleIds' => [$managerRoleId, $defaultRoleId],
    ]);
    $formerAdmin = $this->authenticateFreshUser($client);
    $memberId = $this->createResource($client, $ownerToken, '/api/organizations/' . $organizationId . '/members', [
      'userId' => $formerAdmin['userId'], 'roleIds' => [$adminRoleId],
    ]);
    $client->request('DELETE', '/api/organizations/' . $organizationId . '/members/' . $memberId, server: $this->headers($ownerToken));
    self::assertSame(204, $client->getResponse()->getStatusCode());

    // The manager holds every requested/default Member permission, but not
    // the retained administrative permission that activation would restore.
    $payload = ['userId' => $formerAdmin['userId']];
    if ('empty' === $operation) {
      $payload['roleIds'] = [];
    }
    $path = '/api/organizations/' . $organizationId . '/members' . ('reactivate' === $operation ? '/' . $memberId . '/reactivate' : '');
    $client->request('POST', $path, server: $this->headers($owner ? $ownerToken : $manager['token']), content: json_encode($payload) ?: '');
    self::assertSame($owner ? ('reactivate' === $operation ? 200 : 201) : 403, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent() ?: '');

    /** @var EntityManagerInterface $currentMain */
    $currentMain = $client->getContainer()->get('doctrine.orm.main_entity_manager');
    $active = $currentMain->getConnection()->fetchOne('SELECT COUNT(*) FROM organization_members WHERE id = ? AND is_active = TRUE', [$memberId]);
    self::assertSame($owner ? 1 : 0, is_numeric($active) ? (int) $active : null);
    $retained = $currentMain->getConnection()->fetchOne('SELECT COUNT(*) FROM organization_member_roles WHERE member_id = ? AND role_id = ?', [$memberId, $adminRoleId]);
    self::assertSame(1, is_numeric($retained) ? (int) $retained : null);
  }

  /**
   * @param array<string, mixed> $payload the HTTP input
   */
  private function createResource(KernelBrowser $client, string $token, string $path, array $payload): string
  {
    $client->request('POST', $path, server: $this->headers($token), content: json_encode($payload) ?: '');
    self::assertSame(201, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent() ?: '');
    $resource = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertIsString($resource['id'] ?? null);

    return $resource['id'];
  }

  /**
   * @return array<string, string>
   */
  private function headers(string $token): array
  {
    return ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token];
  }
}
