<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_encode;

/**
 * Test OrganizationApiTest.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  #[Test]
  public function testListOrganizationStatusesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/statuses');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/statuses endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /organizations/statuses, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListOrganizationInvitationStatusesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/invitation-statuses');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/invitation-statuses endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /organizations/invitation-statuses, got ' . $statusCode,
    );
  }

  // #region Methods

  // -------------------------------------------------------------------------
  // Organization endpoints
  // -------------------------------------------------------------------------

  #[Test]
  public function testCreateOrganizationRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      method: 'POST',
      uri: '/api/organizations',
      server: ['CONTENT_TYPE' => 'application/json'],
      content: (string) json_encode([
        'name' => 'Acme Corp',
        'legalType' => 'company',
        'country' => 'FR',
      ]),
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /organizations, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListOrganizationsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /organizations, got ' . $statusCode,
    );
  }

  #[Test]
  public function testGetOrganizationRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /organizations/{id}, got ' . $statusCode,
    );
  }

  // -------------------------------------------------------------------------
  // Member endpoints
  // -------------------------------------------------------------------------

  #[Test]
  public function testInviteMemberRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::DUMMY_UUID . '/members',
      server: ['CONTENT_TYPE' => 'application/json'],
      content: (string) json_encode([
        'userId' => self::DUMMY_UUID,
      ]),
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /members, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListMembersRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      'GET',
      '/api/organizations/' . self::DUMMY_UUID . '/members',
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /members, got ' . $statusCode,
    );
  }

  #[Test]
  public function testRemoveMemberRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      'DELETE',
      '/api/organizations/' . self::DUMMY_UUID . '/members/' . self::DUMMY_UUID,
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'DELETE /organizations/{id}/members/{memberId} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated DELETE /members/{memberId}, got ' . $statusCode,
    );
  }

  // -------------------------------------------------------------------------
  // Role endpoints
  // -------------------------------------------------------------------------

  #[Test]
  public function testCreateRoleRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::DUMMY_UUID . '/roles',
      server: ['CONTENT_TYPE' => 'application/json'],
      content: (string) json_encode([
        'name' => 'Inspector',
        'permissions' => [],
      ]),
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /roles, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListRolesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      'GET',
      '/api/organizations/' . self::DUMMY_UUID . '/roles',
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /roles, got ' . $statusCode,
    );
  }

  #[Test]
  public function testUpdateRoleRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . self::DUMMY_UUID . '/roles/' . self::DUMMY_UUID,
      server: ['CONTENT_TYPE' => 'application/merge-patch+json'],
      content: (string) json_encode([
        'name' => 'Senior Inspector',
      ]),
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'PATCH /organizations/{id}/roles/{roleId} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated PATCH /roles/{roleId}, got ' . $statusCode,
    );
  }

  #[Test]
  public function testDeleteRoleRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      'DELETE',
      '/api/organizations/' . self::DUMMY_UUID . '/roles/' . self::DUMMY_UUID,
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'DELETE /organizations/{id}/roles/{roleId} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated DELETE /roles/{roleId}, got ' . $statusCode,
    );
  }

  // -------------------------------------------------------------------------
  // Member role assignment endpoints
  // -------------------------------------------------------------------------

  #[Test]
  public function testAssignRoleToMemberRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::DUMMY_UUID . '/members/' . self::DUMMY_UUID . '/roles',
      server: ['CONTENT_TYPE' => 'application/json'],
      content: (string) json_encode([
        'roleId' => self::DUMMY_UUID,
      ]),
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /members/{memberId}/roles, got ' . $statusCode,
    );
  }

  #[Test]
  public function testRemoveRoleFromMemberRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      'DELETE',
      '/api/organizations/' . self::DUMMY_UUID . '/members/' . self::DUMMY_UUID . '/roles/' . self::DUMMY_UUID,
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'DELETE /organizations/{id}/members/{memberId}/roles/{roleId} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated DELETE /members/{memberId}/roles/{roleId}, got ' . $statusCode,
    );
  }

  // #endregion
}
