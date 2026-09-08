<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function basename;
use function is_string;
use function json_encode;
use function str_contains;
use function uniqid;

/**
 * Test CalendarFeedTokenPermissionLossFlow.
 *
 * The security-review regression for the anti-oracle claim documented on
 * {@see \Calendar\Presentation\Api\Controller\GetCalendarFeedIcsController}:
 * a member who loses `organization.events.read` — WITHOUT the feed token
 * itself ever being touched — must see the exact same uniform 404 an
 * unknown token gets. Losing the permission is a real production path
 * (an admin narrows a member's role) and is exercised here through the
 * actual role-management endpoints, not a direct database write, so the
 * `OrganizationAuthorizationService` shared permission cache is invalidated
 * exactly the way production invalidates it — by
 * {@see \Organization\Application\Service\OrganizationCacheInvalidator}
 * running off the real `SET_ORGANIZATION_MEMBER_ROLES` write, not by the
 * test reaching into the cache.
 *
 * @category E2E Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CalendarFeedTokenPermissionLossFlowTest extends OAuth2WebTestCase
{
  private const string LD_JSON = 'application/ld+json';

  public function testFeedAnswersTheSameUniformNotFoundOnceTheMemberLosesReadPermission(): void
  {
    $client = static::createClientWithFixtures();

    $ownerEmail = 'calendar-feed-owner-' . uniqid() . '@example.com';
    $ownerPassword = 'OwnerPassword123!';
    $memberEmail = 'calendar-feed-member-' . uniqid() . '@example.com';
    $memberPassword = 'MemberPassword123!';

    $this->createAndActivateUser($client, $ownerEmail, $ownerPassword);
    $this->createAndActivateUser($client, $memberEmail, $memberPassword);

    $ownerToken = $this->loginAndGetUserAccessToken($client, $ownerEmail, $ownerPassword);
    $organizationId = $this->createOrganization($client, $ownerToken, 'Calendar Feed Permission Org ' . uniqid());
    self::assertIsString($organizationId, 'Organization creation should succeed.');

    $memberUserId = $this->findUserIdByEmail($client, $memberEmail);
    self::assertIsString($memberUserId, 'The member account should exist.');

    // Adding a member with no explicit roleIds falls back to the default
    // "member" role, which carries organization.events.read — see
    // OrganizationSystemRoleCatalog::permissionsFor().
    $memberId = $this->addOrganizationMember($client, $ownerToken, $organizationId, $memberUserId);
    self::assertIsString($memberId, 'Adding the organization member should succeed.');

    $memberToken = $this->loginAndGetUserAccessToken($client, $memberEmail, $memberPassword);

    // 1. The member creates their own feed token while still entitled.
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/calendar/feed-token',
      server: $this->headers($memberToken),
    );
    $response = $client->getResponse();
    self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), 'Token creation should succeed while the member holds organization.events.read. Response: ' . ($response->getContent() ?: ''));
    $secret = $this->decodeJsonResponse($response->getContent() ?: '{}')['secret'] ?? null;
    self::assertTrue(is_string($secret) && '' !== $secret, 'The 201 must carry the raw secret.');

    // 2. Sanity: the feed answers 200 for the still-entitled member.
    $client->request(method: 'GET', uri: '/api/calendar/feed/' . $secret . '.ics');
    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), 'The feed must serve while the member is entitled.');

    // 3. The owner narrows the member's roles to one WITHOUT
    // organization.events.read. The token itself is never touched.
    $limitedRoleId = $this->createOrganizationRole(
      client: $client,
      token: $ownerToken,
      organizationId: $organizationId,
      roleName: 'limited_no_events_' . uniqid(),
      permissions: ['organization.read'],
    );

    $client->request(
      method: 'PUT',
      uri: '/api/organizations/' . $organizationId . '/members/' . $memberId . '/roles',
      server: $this->headers($ownerToken),
      content: json_encode(['roleIds' => [$limitedRoleId]]) ?: '',
    );
    $setRolesResponse = $client->getResponse();
    self::assertSame(
      Response::HTTP_OK,
      $setRolesResponse->getStatusCode(),
      'Replacing the member roles should succeed. Response: ' . ($setRolesResponse->getContent() ?: ''),
    );

    // 4. The SAME still-active secret must now answer the same uniform 404
    // an unknown token gets — not 401/403, and the exact same body.
    $client->request(method: 'GET', uri: '/api/calendar/feed/' . $secret . '.ics');
    $afterPermissionLoss = $client->getResponse();
    self::assertSame(
      Response::HTTP_NOT_FOUND,
      $afterPermissionLoss->getStatusCode(),
      'A member who lost organization.events.read must be denied, even with a live token.',
    );

    $client->request(method: 'GET', uri: '/api/calendar/feed/definitely-unknown-token-' . uniqid() . '.ics');
    $unknownToken = $client->getResponse();
    self::assertSame(Response::HTTP_NOT_FOUND, $unknownToken->getStatusCode());

    self::assertSame(
      $unknownToken->getContent(),
      $afterPermissionLoss->getContent(),
      'The permission-loss 404 must be byte-identical to the unknown-token 404 — the anti-oracle contract.',
    );
  }

  // #region Helpers

  private function loginAndGetUserAccessToken(KernelBrowser $client, string $email, string $password): string
  {
    $client->request(
      method: 'POST',
      uri: '/api/auth/login',
      server: [
        'CONTENT_TYPE' => self::LD_JSON,
        'HTTP_ACCEPT' => self::LD_JSON,
      ],
      content: json_encode(['email' => $email, 'password' => $password]) ?: '',
    );

    $data = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    $token = $data['access_token'] ?? null;
    self::assertTrue(is_string($token) && '' !== $token, 'Login should return an access token.');

    return $token;
  }

  private function createOrganization(KernelBrowser $client, string $token, string $name): ?string
  {
    $client->request(
      method: 'POST',
      uri: '/api/organizations',
      server: $this->headers($token),
      content: json_encode(['name' => $name]) ?: '',
    );

    return $this->extractResourceId($this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}'));
  }

  private function findUserIdByEmail(KernelBrowser $client, string $email): ?string
  {
    /** @var \Doctrine\ORM\EntityManagerInterface $entityManager */
    $entityManager = $client->getContainer()->get('doctrine.orm.auth_entity_manager');

    $value = $entityManager->getConnection()->fetchOne(
      'SELECT id FROM users WHERE email = ?',
      [$email],
    );

    return is_string($value) && '' !== $value ? $value : null;
  }

  private function addOrganizationMember(KernelBrowser $client, string $token, string $organizationId, string $userId): ?string
  {
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/members',
      server: $this->headers($token),
      content: json_encode(['userId' => $userId]) ?: '',
    );

    $response = $client->getResponse();
    self::assertContains(
      $response->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_OK],
      'Adding the organization member should succeed. Response: ' . ($response->getContent() ?: ''),
    );

    return $this->extractResourceId($this->decodeJsonResponse($response->getContent() ?: '{}'));
  }

  /**
   * @param list<string> $permissions
   */
  private function createOrganizationRole(KernelBrowser $client, string $token, string $organizationId, string $roleName, array $permissions): string
  {
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/roles',
      server: $this->headers($token),
      content: json_encode([
        'name' => $roleName,
        'permissions' => $permissions,
      ]) ?: '',
    );

    $response = $client->getResponse();
    self::assertContains(
      $response->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_OK],
      'Creating the limited organization role should succeed. Response: ' . ($response->getContent() ?: ''),
    );

    $roleId = $this->extractResourceId($this->decodeJsonResponse($response->getContent() ?: '{}'));
    self::assertTrue(is_string($roleId) && '' !== $roleId, 'The created role must expose an id.');

    return $roleId;
  }

  /**
   * @param array<string, mixed> $data
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

  /**
   * @return array<string, string>
   */
  private function headers(string $token): array
  {
    return [
      'CONTENT_TYPE' => self::LD_JSON,
      'HTTP_ACCEPT' => self::LD_JSON,
      'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
    ];
  }

  // #endregion
}
