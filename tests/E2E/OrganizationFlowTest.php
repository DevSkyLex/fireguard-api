<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function array_column;
use function basename;
use function is_array;
use function is_string;
use function json_encode;
use function str_contains;
use function uniqid;

/**
 * End-to-end tests for Organization management and Organization-scoped RBAC.
 */
final class OrganizationFlowTest extends OAuth2WebTestCase
{
  public function testOrganizationDashboardEndpointsExposeAuthorizedDashboardContracts(): void
  {
    $client = static::createClientWithFixtures();

    $ownerEmail = 'organization-stats-owner-' . uniqid() . '@example.com';
    $ownerPassword = 'OwnerPassword123!';

    $this->createAndActivateUser($client, $ownerEmail, $ownerPassword);
    $ownerToken = $this->loginAndGetUserAccessToken($client, $ownerEmail, $ownerPassword);
    $organizationId = $this->createOrganization($client, $ownerToken, 'Organization Stats ' . uniqid());

    $this->assertNotNull($organizationId, 'Organization should be created successfully.');

    $dashboardData = $this->requestOrganizationDashboard($client, $ownerToken, $organizationId);
    $this->assertArrayHasKey('generatedAt', $dashboardData);
    $this->assertArrayHasKey('period', $dashboardData);
    $this->assertArrayHasKey('overview', $dashboardData);
    $this->assertArrayHasKey('health', $dashboardData);
    $this->assertArrayHasKey('alerts', $dashboardData);
    $this->assertArrayHasKey('comparison', $dashboardData);

    $overview = $dashboardData['overview'] ?? null;
    $this->assertTrue(is_array($overview), 'Dashboard overview should be an object.');
    $this->assertArrayHasKey('members', $overview);
    $this->assertArrayHasKey('roles', $overview);
    $this->assertArrayHasKey('facilities', $overview);
    $this->assertArrayHasKey('equipment', $overview);
    $this->assertArrayHasKey('inspections', $overview);
    $this->assertArrayHasKey('nonConformities', $overview);
    /** @var array{summary?: mixed} $members */
    $members = is_array($overview['members'] ?? null) ? $overview['members'] : [];
    /** @var array{summary?: mixed} $facilities */
    $facilities = is_array($overview['facilities'] ?? null) ? $overview['facilities'] : [];
    /** @var array{metrics?: mixed} $health */
    $health = is_array($dashboardData['health'] ?? null) ? $dashboardData['health'] : [];
    /** @var array{metrics?: mixed, health?: mixed} $comparison */
    $comparison = is_array($dashboardData['comparison'] ?? null) ? $dashboardData['comparison'] : [];
    /** @var array{metrics?: mixed} $comparisonHealth */
    $comparisonHealth = is_array($comparison['health'] ?? null) ? $comparison['health'] : [];

    $this->assertTrue(is_array($members['summary'] ?? null), 'Widget summaries should be exposed.');
    $this->assertTrue(is_array($facilities['summary'] ?? null), 'Widget summaries should be exposed.');
    $this->assertTrue(is_array($health['metrics'] ?? null), 'Health metrics should be normalized.');
    $this->assertTrue(is_array($comparison['metrics'] ?? null), 'Comparison metrics should be normalized.');
    $this->assertTrue(is_array($comparisonHealth['metrics'] ?? null), 'Comparison health metrics should be normalized.');
    $this->assertArrayHasKey('trends', $dashboardData);
    /** @var array{facilities?: mixed, members?: mixed, equipment?: mixed, inspections?: mixed} $trends */
    $trends = is_array($dashboardData['trends'] ?? null) ? $dashboardData['trends'] : [];
    $this->assertArrayHasKey('facilities', $trends);
    $this->assertArrayHasKey('members', $trends);
    $this->assertArrayHasKey('equipment', $trends);
    $this->assertArrayHasKey('inspections', $trends);
    $this->assertTrue(is_array($trends['facilities'] ?? null), 'Each trend section should be an iterable sparkline series.');
    $this->assertArrayHasKey('recentInterventions', $dashboardData);
    $this->assertTrue(is_array($dashboardData['recentInterventions'] ?? null), 'recentInterventions should be an iterable list.');
    $this->assertArrayNotHasKey('trendMetrics', $dashboardData);
    $this->assertArrayNotHasKey('memberActivationRate', $health);
    $this->assertArrayNotHasKey('total', $members);
    $this->assertArrayNotHasKey('breakdowns', $facilities);
    $this->assertArrayNotHasKey('current', $comparison);
    $this->assertArrayNotHasKey('previous', $comparison);
    $this->assertArrayNotHasKey('deltas', $comparison);

    $inspectionsTrendData = $this->requestOrganizationDashboardTrend($client, $ownerToken, $organizationId, 'inspections');
    $this->assertSame('inspections_performed', $inspectionsTrendData['metric'] ?? null);
    $this->assertArrayHasKey('series', $inspectionsTrendData);
    $this->assertArrayHasKey('summary', $inspectionsTrendData);

    $equipmentCreatedTrendData = $this->requestOrganizationDashboardTrend($client, $ownerToken, $organizationId, 'equipment-created');
    $this->assertSame('equipment_created', $equipmentCreatedTrendData['metric'] ?? null);
    $this->assertArrayHasKey('series', $equipmentCreatedTrendData);

    $facilitiesCreatedTrendData = $this->requestOrganizationDashboardTrend($client, $ownerToken, $organizationId, 'facilities-created');
    $this->assertSame('facilities_created', $facilitiesCreatedTrendData['metric'] ?? null);
    $this->assertArrayHasKey('series', $facilitiesCreatedTrendData);

    $openedTrendData = $this->requestOrganizationDashboardTrend($client, $ownerToken, $organizationId, 'non-conformities-opened');
    $this->assertSame('non_conformities_opened', $openedTrendData['metric'] ?? null);
    $this->assertArrayHasKey('series', $openedTrendData);

    $resolvedTrendData = $this->requestOrganizationDashboardTrend($client, $ownerToken, $organizationId, 'non-conformities-resolved');
    $this->assertSame('non_conformities_resolved', $resolvedTrendData['metric'] ?? null);
    $this->assertArrayHasKey('series', $resolvedTrendData);
  }

  public function testRestrictedMemberIsDeniedProtectedOrganizationDashboardEndpoints(): void
  {
    $client = static::createClientWithFixtures();

    $ownerEmail = 'organization-stats-owner-' . uniqid() . '@example.com';
    $ownerPassword = 'OwnerPassword123!';
    $memberEmail = 'organization-stats-member-' . uniqid() . '@example.com';
    $memberPassword = 'MemberPassword123!';

    $this->createAndActivateUser($client, $ownerEmail, $ownerPassword);
    $this->createAndActivateUser($client, $memberEmail, $memberPassword);

    $ownerToken = $this->loginAndGetUserAccessToken($client, $ownerEmail, $ownerPassword);
    $organizationId = $this->createOrganization($client, $ownerToken, 'Restricted Organization Stats ' . uniqid());

    $this->assertNotNull($organizationId, 'Organization should be created successfully.');

    $memberUserId = $this->findUserIdByEmail($client, $memberEmail);
    $this->assertNotNull($memberUserId, 'Created member user should exist in database.');

    $memberId = $this->addOrganizationMember($client, $ownerToken, $organizationId, $memberUserId);
    $this->assertNotNull($memberId, 'Organization member should be created successfully.');

    $roleMap = $this->listOrganizationRolesByName($client, $ownerToken, $organizationId);
    $defaultMemberRoleId = $roleMap['member'] ?? null;
    $this->assertTrue(is_string($defaultMemberRoleId) && '' !== $defaultMemberRoleId, 'Default member role should be listed.');

    $limitedRoleId = $this->createOrganizationRole(
      client: $client,
      token: $ownerToken,
      organizationId: $organizationId,
      roleName: 'limited_stats_' . uniqid(),
      permissions: ['organization.read', 'organization.members.read'],
    );

    $this->removeRoleFromMember($client, $ownerToken, $organizationId, $memberId, $defaultMemberRoleId);
    $this->assignRoleToMember($client, $ownerToken, $organizationId, $memberId, $limitedRoleId);

    $memberToken = $this->loginAndGetUserAccessToken($client, $memberEmail, $memberPassword);

    $this->assertOrganizationDashboardDenied($client, $memberToken, $organizationId);
    $this->assertOrganizationDashboardTrendDenied($client, $memberToken, $organizationId, 'inspections');
    $this->assertOrganizationDashboardTrendDenied($client, $memberToken, $organizationId, 'equipment-created');
    $this->assertOrganizationDashboardTrendDenied($client, $memberToken, $organizationId, 'facilities-created');
    $this->assertOrganizationDashboardTrendDenied($client, $memberToken, $organizationId, 'non-conformities-opened');
    $this->assertOrganizationDashboardTrendDenied($client, $memberToken, $organizationId, 'non-conformities-resolved');
  }

  public function testCompleteOrganizationManagementFlow(): void
  {
    $client = static::createClientWithFixtures();

    $ownerEmail = 'Organization-owner-' . uniqid() . '@example.com';
    $ownerPassword = 'OwnerPassword123!';
    $memberEmail = 'Organization-member-' . uniqid() . '@example.com';
    $memberPassword = 'MemberPassword123!';

    $this->createAndActivateUser($client, $ownerEmail, $ownerPassword);
    $this->createAndActivateUser($client, $memberEmail, $memberPassword);

    $ownerToken = $this->loginAndGetUserAccessToken($client, $ownerEmail, $ownerPassword);

    $OrganizationName = 'Fireguard Organization ' . uniqid();

    // Step 1: Create Organization as owner.
    $client->request(
      method: 'POST',
      uri: '/api/organizations',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
      content: json_encode([
        'name' => $OrganizationName,
      ]) ?: '',
    );

    $createOrganizationResponse = $client->getResponse();
    $this->assertContains(
      $createOrganizationResponse->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_OK],
      'Organization creation should succeed. Response: ' . $createOrganizationResponse->getContent(),
    );

    $createOrganizationData = $this->decodeJsonResponse($createOrganizationResponse->getContent() ?: '{}');
    $organizationId = $this->extractResourceId($createOrganizationData);

    $this->assertNotNull($organizationId, 'Organization ID should be present in create response.');

    // Step 2: Owner lists Organizations.
    $client->request(
      method: 'GET',
      uri: '/api/organizations',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
    );

    $listCompaniesResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $listCompaniesResponse->getStatusCode(),
      'Organization list should succeed for owner. Response: ' . $listCompaniesResponse->getContent(),
    );

    $listCompaniesData = $this->decodeJsonResponse($listCompaniesResponse->getContent() ?: '{}');
    $organizations = $this->getCollectionMembers($listCompaniesData);
    $this->assertTrue($this->collectionContainsId($organizations, $organizationId), 'Created Organization should appear in owner list.');

    // Step 3: Owner gets Organization by id.
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
    );

    $getOrganizationResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $getOrganizationResponse->getStatusCode(),
      'Owner should be able to read Organization. Response: ' . $getOrganizationResponse->getContent(),
    );

    // Step 4: Add second user as member (default member role).
    $memberUserId = $this->findUserIdByEmail($client, $memberEmail);
    $this->assertNotNull($memberUserId, 'Created member user should exist in database.');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/members',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
      content: json_encode([
        'userId' => $memberUserId,
      ]) ?: '',
    );

    $addMemberResponse = $client->getResponse();
    $this->assertContains(
      $addMemberResponse->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_OK],
      'Adding Organization member should succeed. Response: ' . $addMemberResponse->getContent(),
    );

    $addMemberData = $this->decodeJsonResponse($addMemberResponse->getContent() ?: '{}');
    $memberId = $this->extractResourceId($addMemberData);

    $this->assertNotNull($memberId, 'Organization member ID should be present after add member.');

    // Step 5: List Organization members.
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/members',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
    );

    $listMembersResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $listMembersResponse->getStatusCode(),
      'Listing Organization members should succeed. Response: ' . $listMembersResponse->getContent(),
    );

    $listMembersData = $this->decodeJsonResponse($listMembersResponse->getContent() ?: '{}');
    $members = $this->getCollectionMembers($listMembersData);
    $this->assertTrue($this->collectionContainsId($members, $memberId), 'Added member should appear in members list.');

    // Step 6: Create custom role.
    $roleName = 'inspector_' . uniqid();
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/roles',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
      content: json_encode([
        'name' => $roleName,
        'permissions' => ['organization.members.read', 'organization.roles.read', 'organization.facilities.read'],
      ]) ?: '',
    );

    $createRoleResponse = $client->getResponse();
    $this->assertContains(
      $createRoleResponse->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_OK],
      'Creating Organization role should succeed. Response: ' . $createRoleResponse->getContent(),
    );

    $createRoleData = $this->decodeJsonResponse($createRoleResponse->getContent() ?: '{}');
    $roleId = $this->extractResourceId($createRoleData);

    $this->assertNotNull($roleId, 'Organization role ID should be present after role creation.');

    // Step 7: List roles.
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/roles',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
    );

    $listRolesResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $listRolesResponse->getStatusCode(),
      'Listing Organization roles should succeed. Response: ' . $listRolesResponse->getContent(),
    );

    $listRolesData = $this->decodeJsonResponse($listRolesResponse->getContent() ?: '{}');
    $roles = $this->getCollectionMembers($listRolesData);
    $this->assertTrue($this->collectionContainsId($roles, $roleId), 'Created role should appear in role list.');

    // Step 8: Assign role to member.
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/members/' . $memberId . '/roles',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
      content: json_encode([
        'roleId' => $roleId,
      ]) ?: '',
    );

    $assignRoleResponse = $client->getResponse();
    $this->assertContains(
      $assignRoleResponse->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_OK],
      'Assigning Organization role to member should succeed. Response: ' . $assignRoleResponse->getContent(),
    );

    $assignRoleData = $this->decodeJsonResponse($assignRoleResponse->getContent() ?: '{}');
    $assignedRoleIds = $assignRoleData['roleIds'] ?? null;

    $this->assertTrue(is_array($assignedRoleIds), 'Assign role response should expose roleIds array.');
    $this->assertContains($roleId, $assignedRoleIds, 'Assigned role should be present in roleIds response.');

    // Step 9: Member can read Organization after being added.
    $memberToken = $this->loginAndGetUserAccessToken($client, $memberEmail, $memberPassword);

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $memberToken,
      ],
    );

    $memberReadResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $memberReadResponse->getStatusCode(),
      'Added member should be able to read Organization details. Response: ' . $memberReadResponse->getContent(),
    );
  }

  public function testOrganizationDashboardEndpointsAreDeniedAcrossOrganizations(): void
  {
    $client = static::createClientWithFixtures();

    $ownerOneEmail = 'organization-stats-owner-one-' . uniqid() . '@example.com';
    $ownerOnePassword = 'OwnerOnePassword123!';
    $ownerTwoEmail = 'organization-stats-owner-two-' . uniqid() . '@example.com';
    $ownerTwoPassword = 'OwnerTwoPassword123!';

    $this->createAndActivateUser($client, $ownerOneEmail, $ownerOnePassword);
    $this->createAndActivateUser($client, $ownerTwoEmail, $ownerTwoPassword);

    $ownerOneToken = $this->loginAndGetUserAccessToken($client, $ownerOneEmail, $ownerOnePassword);
    $ownerTwoToken = $this->loginAndGetUserAccessToken($client, $ownerTwoEmail, $ownerTwoPassword);

    $organizationOneId = $this->createOrganization($client, $ownerOneToken, 'Organization One ' . uniqid());
    $organizationTwoId = $this->createOrganization($client, $ownerTwoToken, 'Organization Two ' . uniqid());

    $this->assertNotNull($organizationOneId, 'First organization should be created successfully.');
    $this->assertNotNull($organizationTwoId, 'Second organization should be created successfully.');

    $this->assertOrganizationDashboardDenied($client, $ownerOneToken, $organizationTwoId);
    $this->assertOrganizationDashboardTrendDenied($client, $ownerOneToken, $organizationTwoId, 'inspections');
    $this->assertOrganizationDashboardTrendDenied($client, $ownerOneToken, $organizationTwoId, 'equipment-created');
    $this->assertOrganizationDashboardTrendDenied($client, $ownerOneToken, $organizationTwoId, 'facilities-created');
    $this->assertOrganizationDashboardTrendDenied($client, $ownerOneToken, $organizationTwoId, 'non-conformities-opened');
    $this->assertOrganizationDashboardTrendDenied($client, $ownerOneToken, $organizationTwoId, 'non-conformities-resolved');
  }

  public function testUpdateOrganizationLegalProfile(): void
  {
    $client = static::createClientWithFixtures();

    $ownerEmail = 'organization-legal-owner-' . uniqid() . '@example.com';
    $ownerPassword = 'OwnerPassword123!';

    $this->createAndActivateUser($client, $ownerEmail, $ownerPassword);
    $ownerToken = $this->loginAndGetUserAccessToken($client, $ownerEmail, $ownerPassword);
    $organizationId = $this->createOrganization($client, $ownerToken, 'Organization Legal ' . uniqid());

    $this->assertNotNull($organizationId, 'Organization should be created successfully.');

    // Legal profile is entirely optional: a freshly created organization has none.
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
    );
    $initialData = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    $this->assertNull($initialData['country'] ?? null, 'A fresh organization should have no legal country.');
    $this->assertNull($initialData['legalType'] ?? null, 'A fresh organization should have no legal type.');

    // Reference catalog: legal types are exposed for the settings select.
    $client->request(
      method: 'GET',
      uri: '/api/organizations/legal-types',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
    );
    $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    $legalTypesData = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    $legalTypeOptions = $this->getCollectionMembers($legalTypesData);
    $this->assertNotEmpty($legalTypeOptions, 'Legal type reference catalog should not be empty.');
    $this->assertContains('limited_liability_company', array_column($legalTypeOptions, 'value'));

    // Set the legal profile.
    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . $organizationId,
      server: [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
      content: json_encode([
        'country' => 'FR',
        'legalType' => 'limited_liability_company',
        'legalName' => 'Fireguard Paris SARL',
        'registrationNumber' => 'RCS PARIS 812345678',
        'vatNumber' => 'FR12345678901',
      ]) ?: '',
    );

    $updateResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $updateResponse->getStatusCode(),
      'Updating the legal profile should succeed. Response: ' . $updateResponse->getContent(),
    );

    $updatedData = $this->decodeJsonResponse($updateResponse->getContent() ?: '{}');
    $this->assertSame('FR', $updatedData['country'] ?? null);
    $this->assertSame('limited_liability_company', $updatedData['legalType'] ?? null);
    $this->assertSame('Fireguard Paris SARL', $updatedData['legalName'] ?? null);
    $this->assertSame('RCS PARIS 812345678', $updatedData['registrationNumber'] ?? null);
    $this->assertSame('FR12345678901', $updatedData['vatNumber'] ?? null);

    // An unrecognized country is rejected.
    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . $organizationId,
      server: [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
      content: json_encode(['country' => 'ZZ']) ?: '',
    );
    $this->assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

    // Clearing fields via empty strings.
    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . $organizationId,
      server: [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
      content: json_encode([
        'country' => '',
        'legalType' => '',
        'legalName' => '',
        'registrationNumber' => '',
        'vatNumber' => '',
      ]) ?: '',
    );

    $clearedResponse = $client->getResponse();
    $this->assertSame(Response::HTTP_OK, $clearedResponse->getStatusCode());
    $clearedData = $this->decodeJsonResponse($clearedResponse->getContent() ?: '{}');
    $this->assertNull($clearedData['country'] ?? null);
    $this->assertNull($clearedData['legalType'] ?? null);
    $this->assertNull($clearedData['legalName'] ?? null);
    $this->assertNull($clearedData['registrationNumber'] ?? null);
    $this->assertNull($clearedData['vatNumber'] ?? null);
  }

  public function testOrganizationEndpointsRequireAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/organizations',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
    );

    $response = $client->getResponse();

    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
      'Organizations endpoint should require authentication. Response: ' . $response->getContent(),
    );
  }

  private function loginAndGetUserAccessToken(KernelBrowser $client, string $email, string $password): string
  {
    $client->request(
      method: 'POST',
      uri: '/api/auth/login',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'email' => $email,
        'password' => $password,
      ]) ?: '',
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'User login should succeed for E2E flow. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $token = $data['access_token'] ?? null;

    $this->assertTrue(is_string($token) && '' !== $token, 'Login response should contain access_token.');

    return $token;
  }

  private function createOrganization(KernelBrowser $client, string $token, string $name): ?string
  {
    $client->request(
      method: 'POST',
      uri: '/api/organizations',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode(['name' => $name]) ?: '',
    );

    $data = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');

    return $this->extractResourceId($data);
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
   * @param array<string, mixed> $data
   *
   * @return list<array<string, mixed>>
   */
  private function getCollectionMembers(array $data): array
  {
    $members = $data['member'] ?? [];

    if (!is_array($members)) {
      return [];
    }

    $result = [];
    foreach ($members as $member) {
      if (!is_array($member)) {
        continue;
      }

      $normalized = [];
      foreach ($member as $key => $value) {
        if (is_string($key)) {
          $normalized[$key] = $value;
        }
      }

      $result[] = $normalized;
    }

    return $result;
  }

  /**
   * @param list<array<string, mixed>> $collection
   */
  private function collectionContainsId(array $collection, string $id): bool
  {
    foreach ($collection as $item) {
      $itemId = $this->extractResourceId($item);
      if ($id === $itemId) {
        return true;
      }
    }

    return false;
  }

  /**
   * @return array<string, mixed>
   */
  private function requestOrganizationDashboard(KernelBrowser $client, string $token, string $organizationId): array
  {
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/dashboard',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Organization dashboard request should succeed. Response: ' . $response->getContent(),
    );

    return $this->decodeJsonResponse($response->getContent() ?: '{}');
  }

  /**
   * @return array<string, mixed>
   */
  private function requestOrganizationDashboardTrend(KernelBrowser $client, string $token, string $organizationId, string $metricPath): array
  {
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/dashboard/trends/' . $metricPath,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Organization dashboard trend request should succeed. Response: ' . $response->getContent(),
    );

    return $this->decodeJsonResponse($response->getContent() ?: '{}');
  }

  private function assertOrganizationDashboardDenied(KernelBrowser $client, string $token, string $organizationId): void
  {
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/dashboard',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_FORBIDDEN,
      $response->getStatusCode(),
      'Organization dashboard request should be forbidden. Response: ' . $response->getContent(),
    );
  }

  private function assertOrganizationDashboardTrendDenied(KernelBrowser $client, string $token, string $organizationId, string $metricPath): void
  {
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/dashboard/trends/' . $metricPath,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_FORBIDDEN,
      $response->getStatusCode(),
      'Organization dashboard trend request should be forbidden. Response: ' . $response->getContent(),
    );
  }

  private function addOrganizationMember(KernelBrowser $client, string $token, string $organizationId, string $userId): ?string
  {
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/members',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode(['userId' => $userId]) ?: '',
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_OK],
      'Adding Organization member should succeed. Response: ' . $response->getContent(),
    );

    return $this->extractResourceId($this->decodeJsonResponse($response->getContent() ?: '{}'));
  }

  /**
   * @return array<string, string>
   */
  private function listOrganizationRolesByName(KernelBrowser $client, string $token, string $organizationId): array
  {
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/roles',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Listing Organization roles should succeed. Response: ' . $response->getContent(),
    );

    $roles = $this->getCollectionMembers($this->decodeJsonResponse($response->getContent() ?: '{}'));
    $result = [];
    foreach ($roles as $role) {
      $roleName = $role['name'] ?? null;
      $roleId = $this->extractResourceId($role);

      if (is_string($roleName) && is_string($roleId) && '' !== $roleId) {
        $result[$roleName] = $roleId;
      }
    }

    return $result;
  }

  /**
   * @param list<string> $permissions
   */
  private function createOrganizationRole(KernelBrowser $client, string $token, string $organizationId, string $roleName, array $permissions): string
  {
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/roles',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'name' => $roleName,
        'permissions' => $permissions,
      ]) ?: '',
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_OK],
      'Creating Organization role should succeed. Response: ' . $response->getContent(),
    );

    $roleId = $this->extractResourceId($this->decodeJsonResponse($response->getContent() ?: '{}'));
    $this->assertTrue(is_string($roleId) && '' !== $roleId, 'Organization role ID should be present in create response.');

    return $roleId;
  }

  private function removeRoleFromMember(KernelBrowser $client, string $token, string $organizationId, string $memberId, string $roleId): void
  {
    $client->request(
      method: 'DELETE',
      uri: '/api/organizations/' . $organizationId . '/members/' . $memberId . '/roles/' . $roleId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_NO_CONTENT,
      $response->getStatusCode(),
      'Removing role from member should succeed. Response: ' . $response->getContent(),
    );
  }

  private function assignRoleToMember(KernelBrowser $client, string $token, string $organizationId, string $memberId, string $roleId): void
  {
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/members/' . $memberId . '/roles',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode(['roleId' => $roleId]) ?: '',
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_OK],
      'Assigning Organization role to member should succeed. Response: ' . $response->getContent(),
    );
  }
}
