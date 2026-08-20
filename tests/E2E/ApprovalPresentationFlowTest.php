<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function basename;
use function is_array;
use function is_string;
use function json_encode;
use function str_contains;
use function uniqid;

/**
 * Test ApprovalPresentationFlow.
 *
 * Drives the four-eyes gate end to end over HTTP: an organization enables
 * approval for `equipment_decommission`, a requester's decommission is
 * deferred with HTTP 202 instead of applying, and a second, admin-tier member
 * approves (the equipment is decommissioned) or rejects (it is not).
 *
 * @category E2E Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApprovalPresentationFlowTest extends OAuth2WebTestCase
{
  private const string FAKE_REQUEST_ID = '00000000-0000-4000-8000-000000000000';

  private const string FAKE_ORGANIZATION_ID = '11111111-1111-4111-8111-111111111111';

  /**
   * Everything the requester needs to create the deferred action, and
   * nothing that would let them decide it: no `organization.*` wildcard, so
   * neither `organization.approvals.decide` nor the `admin` approver tier.
   *
   * @var list<string>
   */
  private const array REQUESTER_PERMISSIONS = [
    'organization.read',
    'organization.equipment.read',
    'organization.equipment.write',
    'organization.approvals.read',
    'organization.approvals.request',
  ];

  // #region Full deferred flows
  /**
   * The approve half of the gate: 202 defers the decommission, the approver
   * decides, and only then is the equipment actually decommissioned.
   */
  public function testDecommissionIsDeferredThenApprovedAndApplied(): void
  {
    $client = static::createClientWithFixtures();
    $context = $this->seedGatedOrganization($client);

    $equipmentId = $this->createEquipment($client, $context['ownerToken'], $context['organizationId']);

    $deferred = $this->decommission($client, $context['requesterToken'], $context['organizationId'], $equipmentId);
    $requestId = $deferred['approvalRequestId'] ?? null;

    $this->assertTrue(is_string($requestId) && '' !== $requestId, 'The 202 must carry the pending approval request id.');
    $this->assertSame('pending_approval', $deferred['status'] ?? null);
    $this->assertSame('pending', $deferred['approvalStatus'] ?? null);

    // The gate must not have applied anything yet.
    $this->assertSame(
      'in_stock',
      $this->getEquipment($client, $context['ownerToken'], $context['organizationId'], $equipmentId)['status'] ?? null,
      'A deferred decommission must leave the equipment untouched.',
    );

    // The requester cannot decide their own request, four-eyes being the
    // whole point: they hold neither `decide` nor the admin tier.
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $context['organizationId'] . '/approval-requests/' . $requestId . '/approve',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $context['requesterToken'],
      ],
      content: '{}',
    );
    $this->assertSame(
      Response::HTTP_FORBIDDEN,
      $client->getResponse()->getStatusCode(),
      'The requester must not be able to approve their own request. Response: ' . $client->getResponse()->getContent(),
    );

    $approved = $this->decide($client, $context['ownerToken'], $context['organizationId'], $requestId, 'approve', 'Unit written off.');

    $this->assertSame('approved', $approved['status'] ?? null);
    $this->assertSame('Unit written off.', $approved['decisionNote'] ?? null);
    $this->assertTrue(is_string($approved['executedAt'] ?? null), 'An approved request must record its execution timestamp.');

    $this->assertSame(
      'decommissioned',
      $this->getEquipment($client, $context['ownerToken'], $context['organizationId'], $equipmentId)['status'] ?? null,
      'Approving must re-execute the deferred decommission.',
    );
  }

  /**
   * The reject half: the deferred action is never executed.
   */
  public function testDecommissionIsDeferredThenRejectedAndNeverApplied(): void
  {
    $client = static::createClientWithFixtures();
    $context = $this->seedGatedOrganization($client);

    $equipmentId = $this->createEquipment($client, $context['ownerToken'], $context['organizationId']);
    $deferred = $this->decommission($client, $context['requesterToken'], $context['organizationId'], $equipmentId);
    $requestId = $deferred['approvalRequestId'] ?? null;
    $this->assertTrue(is_string($requestId) && '' !== $requestId, 'The 202 must carry the pending approval request id.');

    $rejected = $this->decide($client, $context['ownerToken'], $context['organizationId'], $requestId, 'reject', 'Still in service.');

    $this->assertSame('rejected', $rejected['status'] ?? null);
    $this->assertArrayNotHasKey('executedAt', $rejected, 'A rejected request must never record an execution.');

    $this->assertSame(
      'in_stock',
      $this->getEquipment($client, $context['ownerToken'], $context['organizationId'], $equipmentId)['status'] ?? null,
      'Rejecting must leave the equipment untouched.',
    );

    // A decided request cannot be decided twice.
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $context['organizationId'] . '/approval-requests/' . $requestId . '/approve',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $context['ownerToken'],
      ],
      content: '{}',
    );
    $this->assertSame(
      Response::HTTP_CONFLICT,
      $client->getResponse()->getStatusCode(),
      'Approving an already-rejected request must conflict. Response: ' . $client->getResponse()->getContent(),
    );
  }

  /**
   * The queue read, now with a real pending request in it.
   */
  public function testPendingRequestAppearsInTheOrganizationQueue(): void
  {
    $client = static::createClientWithFixtures();
    $context = $this->seedGatedOrganization($client);

    $equipmentId = $this->createEquipment($client, $context['ownerToken'], $context['organizationId']);
    $deferred = $this->decommission($client, $context['requesterToken'], $context['organizationId'], $equipmentId);
    $requestId = $deferred['approvalRequestId'] ?? null;
    $this->assertTrue(is_string($requestId) && '' !== $requestId);

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $context['organizationId'] . '/approval-requests?status=pending',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $context['ownerToken'],
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

    $members = $this->getCollectionMembers($this->decodeJsonResponse($response->getContent() ?: '{}'));
    $this->assertCount(1, $members, 'The queue should hold exactly the one pending request.');
    $this->assertSame($requestId, $members[0]['id'] ?? null);
    $this->assertSame('equipment_decommission', $members[0]['actionType'] ?? null);
    $this->assertSame($equipmentId, $members[0]['subjectId'] ?? null);

    // And the single-request read agrees with the listing.
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $context['organizationId'] . '/approval-requests/' . $requestId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $context['ownerToken'],
      ],
    );

    $detail = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    $this->assertSame($requestId, $detail['id'] ?? null);
    $this->assertSame('pending', $detail['status'] ?? null);
  }
  // #endregion

  // #region Guards
  /**
   * Happy path: the reference catalog of gateable action types is readable by
   * any authenticated user (GET /api/approvals/action-types, ROLE_USER).
   */
  public function testAuthenticatedUserListsApprovalActionTypeCatalog(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'approval-catalog-' . uniqid() . '@example.com';
    $password = 'CatalogPassword123!';
    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);

    $client->request(
      method: 'GET',
      uri: '/api/approvals/action-types',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Approval action type catalog should be readable. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $members = $this->getCollectionMembers($data);
    $this->assertNotEmpty($members, 'Approval action type catalog should not be empty.');

    $first = $members[0];
    $this->assertArrayHasKey('value', $first);
    $this->assertArrayHasKey('label', $first);
    $this->assertTrue(is_string($first['value'] ?? null), 'Action type value should be a string.');
    $this->assertTrue(is_string($first['label'] ?? null), 'Action type label should be a string.');
  }

  /**
   * Guard: the action type catalog requires authentication.
   */
  public function testApprovalActionTypeCatalogRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/approvals/action-types',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $response = $client->getResponse();
    $this->assertNotSame(
      Response::HTTP_NOT_FOUND,
      $response->getStatusCode(),
      'Action type catalog route should exist.',
    );
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
      'Action type catalog should be guarded. Response: ' . $response->getContent(),
    );
  }

  /**
   * Happy path: an organization admin (organization.* wildcard, hence
   * organization.approvals.read) can list the approval request queue.
   * The freshly created organization has no pending requests, so the list is
   * empty but structurally a Hydra collection.
   */
  public function testOrganizationAdminListsApprovalRequests(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'approval-owner-' . uniqid() . '@example.com';
    $password = 'OwnerPassword123!';
    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);

    $organizationId = $this->createOrganization($client, $token, 'Approval Queue Org ' . uniqid());
    $this->assertNotNull($organizationId, 'Organization should be created successfully.');

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/approval-requests',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Organization admin should be able to list approval requests. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $this->assertArrayHasKey('member', $data, 'Approval request list should be a Hydra collection.');
    $this->assertTrue(is_array($data['member'] ?? null), 'Hydra member should be an array.');
    $this->assertArrayHasKey('totalItems', $data, 'Approval request list should expose totalItems.');
  }

  /**
   * Guard: listing approval requests requires authentication.
   */
  public function testListApprovalRequestsRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . self::FAKE_ORGANIZATION_ID . '/approval-requests',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $response = $client->getResponse();
    $this->assertNotSame(
      Response::HTTP_NOT_FOUND,
      $response->getStatusCode(),
      'List approval requests route should exist.',
    );
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
      'Listing approval requests should be guarded. Response: ' . $response->getContent(),
    );
  }

  /**
   * Guard: getting a single approval request requires authentication.
   */
  public function testGetApprovalRequestRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . self::FAKE_ORGANIZATION_ID . '/approval-requests/' . self::FAKE_REQUEST_ID,
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $response = $client->getResponse();
    $this->assertNotSame(
      Response::HTTP_NOT_FOUND,
      $response->getStatusCode(),
      'Get approval request route should exist.',
    );
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
      'Getting an approval request should be guarded. Response: ' . $response->getContent(),
    );
  }

  /**
   * Guard: approving an approval request requires authentication.
   */
  public function testApproveApprovalRequestRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::FAKE_ORGANIZATION_ID . '/approval-requests/' . self::FAKE_REQUEST_ID . '/approve',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode(['decisionNote' => 'ok']) ?: '',
    );

    $response = $client->getResponse();
    $this->assertNotSame(
      Response::HTTP_NOT_FOUND,
      $response->getStatusCode(),
      'Approve approval request route should exist.',
    );
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
      'Approving an approval request should be guarded. Response: ' . $response->getContent(),
    );
  }

  /**
   * Guard: rejecting an approval request requires authentication.
   */
  public function testRejectApprovalRequestRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::FAKE_ORGANIZATION_ID . '/approval-requests/' . self::FAKE_REQUEST_ID . '/reject',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode(['decisionNote' => 'no']) ?: '',
    );

    $response = $client->getResponse();
    $this->assertNotSame(
      Response::HTTP_NOT_FOUND,
      $response->getStatusCode(),
      'Reject approval request route should exist.',
    );
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
      'Rejecting an approval request should be guarded. Response: ' . $response->getContent(),
    );
  }
  // #endregion

  // #region Seeding helpers
  /**
   * Builds an organization whose `equipment_decommission` action is gated,
   * with an owner (admin tier, may decide) and a second member holding only
   * the requester permissions.
   *
   * @return array{organizationId: string, ownerToken: string, requesterToken: string}
   */
  private function seedGatedOrganization(KernelBrowser $client): array
  {
    $suffix = uniqid();

    $ownerEmail = 'approval-owner-' . $suffix . '@example.com';
    $requesterEmail = 'approval-requester-' . $suffix . '@example.com';
    $password = 'ApprovalPassword123!';

    $this->createAndActivateUser($client, $ownerEmail, $password);
    $this->createAndActivateUser($client, $requesterEmail, $password);

    $ownerToken = $this->loginAndGetUserAccessToken($client, $ownerEmail, $password);
    $requesterToken = $this->loginAndGetUserAccessToken($client, $requesterEmail, $password);

    $organizationId = $this->createOrganization($client, $ownerToken, 'Approval Gate Org ' . $suffix);
    $this->assertTrue(is_string($organizationId) && '' !== $organizationId, 'Organization should be created.');

    $requesterUserId = $this->currentUserId($client, $requesterToken);
    $memberId = $this->addOrganizationMember($client, $ownerToken, $organizationId, $requesterUserId);

    // Replace the default `member` system role in one call, so the requester
    // never holds anything beyond REQUESTER_PERMISSIONS.
    $roleId = $this->createOrganizationRole($client, $ownerToken, $organizationId, 'approval_requester_' . $suffix);
    $this->setMemberRoles($client, $ownerToken, $organizationId, $memberId, [$roleId]);

    $this->enableDecommissionApproval($client, $ownerToken, $organizationId);

    return [
      'organizationId' => $organizationId,
      'ownerToken' => $ownerToken,
      'requesterToken' => $requesterToken,
    ];
  }

  private function currentUserId(KernelBrowser $client, string $token): string
  {
    $client->request(
      method: 'GET',
      uri: '/api/me',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), 'GET /api/me should succeed. Response: ' . $response->getContent());

    $id = $this->decodeJsonResponse($response->getContent() ?: '{}')['id'] ?? null;
    $this->assertTrue(is_string($id) && '' !== $id, 'GET /api/me should expose the user id.');

    return $id;
  }

  private function addOrganizationMember(KernelBrowser $client, string $token, string $organizationId, string $userId): string
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
      'Adding an organization member should succeed. Response: ' . $response->getContent(),
    );

    $memberId = $this->extractResourceId($this->decodeJsonResponse($response->getContent() ?: '{}'));
    $this->assertTrue(is_string($memberId) && '' !== $memberId, 'The member id should be returned.');

    return $memberId;
  }

  private function createOrganizationRole(KernelBrowser $client, string $token, string $organizationId, string $name): string
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
        'name' => $name,
        'permissions' => self::REQUESTER_PERMISSIONS,
        'description' => 'E2E-only role: may request a gated action, may not decide one.',
      ]) ?: '',
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_OK],
      'Creating an organization role should succeed. Response: ' . $response->getContent(),
    );

    $roleId = $this->extractResourceId($this->decodeJsonResponse($response->getContent() ?: '{}'));
    $this->assertTrue(is_string($roleId) && '' !== $roleId, 'The role id should be returned.');

    return $roleId;
  }

  /**
   * @param list<string> $roleIds
   */
  private function setMemberRoles(KernelBrowser $client, string $token, string $organizationId, string $memberId, array $roleIds): void
  {
    $client->request(
      method: 'PUT',
      uri: '/api/organizations/' . $organizationId . '/members/' . $memberId . '/roles',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode(['roleIds' => $roleIds]) ?: '',
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Replacing the member roles should succeed. Response: ' . $response->getContent(),
    );
  }

  private function enableDecommissionApproval(KernelBrowser $client, string $token, string $organizationId): void
  {
    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . $organizationId,
      server: [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'approval' => [
          'actionRules' => [
            'equipment_decommission' => ['enabled' => true, 'minApproverRole' => 'admin'],
          ],
          'allowSelfApproval' => false,
          'approvalTtlDays' => 14,
        ],
      ]) ?: '',
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Enabling the decommission approval policy should succeed. Response: ' . $response->getContent(),
    );
  }

  private function createEquipment(KernelBrowser $client, string $token, string $organizationId): string
  {
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/equipment',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'type' => 'fire_extinguisher',
        'brand' => 'Sicli',
        'model' => 'Pro 6',
        'serialNumber' => 'APPROVAL-EXT-' . uniqid(),
        'locationLabel' => 'Approval flow - corridor',
      ]) ?: '',
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_CREATED,
      $response->getStatusCode(),
      'Creating equipment should succeed. Response: ' . $response->getContent(),
    );

    $equipmentId = $this->extractResourceId($this->decodeJsonResponse($response->getContent() ?: '{}'));
    $this->assertTrue(is_string($equipmentId) && '' !== $equipmentId, 'The equipment id should be returned.');

    return $equipmentId;
  }

  /**
   * Posts the gated decommission and asserts it was deferred with HTTP 202.
   *
   * @return array<string, mixed> the deferred response body
   */
  private function decommission(KernelBrowser $client, string $token, string $organizationId, string $equipmentId): array
  {
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/equipment/' . $equipmentId . '/decommission',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: '{}',
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_ACCEPTED,
      $response->getStatusCode(),
      'A gated decommission must answer 202, not apply. Response: ' . $response->getContent(),
    );

    return $this->decodeJsonResponse($response->getContent() ?: '{}');
  }

  /**
   * @return array<string, mixed> the decided approval request
   */
  private function decide(
    KernelBrowser $client,
    string $token,
    string $organizationId,
    string $requestId,
    string $decision,
    string $note,
  ): array {
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/approval-requests/' . $requestId . '/' . $decision,
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode(['decisionNote' => $note]) ?: '',
    );

    $response = $client->getResponse();
    // 201 rather than 200: the decision operations are bare API Platform
    // `Post`s with no `status:`, so they answer Created even though they
    // create nothing. `ApprovalRequestResource` documents 200 — a drift left
    // as-is here, since reconciling it changes the wire for the frontend.
    $this->assertSame(
      Response::HTTP_CREATED,
      $response->getStatusCode(),
      'Deciding an approval request should succeed. Response: ' . $response->getContent(),
    );

    return $this->decodeJsonResponse($response->getContent() ?: '{}');
  }

  /**
   * @return array<string, mixed> the equipment read model
   */
  private function getEquipment(KernelBrowser $client, string $token, string $organizationId, string $equipmentId): array
  {
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/equipment/' . $equipmentId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Reading the equipment should succeed. Response: ' . $response->getContent(),
    );

    return $this->decodeJsonResponse($response->getContent() ?: '{}');
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

    return $this->extractResourceId($this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}'));
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
  // #endregion
}
