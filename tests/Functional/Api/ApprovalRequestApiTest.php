<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Approval\Infrastructure\Persistence\Doctrine\Record\ApprovalRequestRecord;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_decode;

/**
 * Test ApprovalRequestApiTest.
 *
 * The four-eyes approval HTTP contract, denial paths first: 401
 * unauthenticated, 403 for a member without `organization.approvals.decide`,
 * 403 for a decider below the policy's `minApproverRole`, 403 on
 * self-approval, 409 on an already-decided request, 409 when the deferred
 * action is no longer applicable, and 404 — never 403 — for a request or an
 * organization outside the caller's scope.
 *
 * The cross-organization 404s mirror the Maintenance hardening
 * (`fix(maintenance): return 404 for schedules outside the caller's
 * organization`): the decision handlers look a request up by path id before
 * they know who owns it, so a 403 there told an outsider that the request
 * exists while an unknown id answered 404 — an existence oracle.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApprovalRequestApiTest extends WebTestCase
{
  // #region Constants
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  private const string DUMMY_UUID_2 = '550e8400-e29b-41d4-a716-446655440001';

  private const string ORGANIZATION_ID = '880e8400-e29b-41d4-a716-446655440001';

  private const string OUTSIDER_ORGANIZATION_ID = '880e8400-e29b-41d4-a716-446655440002';

  /**
   * The organization admin: holds `*`, therefore both
   * `organization.approvals.decide` and the `organization.*` wildcard the
   * `admin` approver tier is detected by.
   */
  private const string ADMIN_USER_ID = '880e8400-e29b-41d4-a716-446655440003';

  /**
   * A member who may read the queue but not decide on it.
   */
  private const string READER_USER_ID = '880e8400-e29b-41d4-a716-446655440004';

  /**
   * A member explicitly granted `organization.approvals.decide` but NOT the
   * `organization.*` wildcard: entitled to call the endpoint, below the
   * policy's default `minApproverRole` of `admin`.
   */
  private const string BELOW_ROLE_USER_ID = '880e8400-e29b-41d4-a716-446655440005';

  /**
   * The member who initiated the gated actions.
   */
  private const string REQUESTER_USER_ID = '880e8400-e29b-41d4-a716-446655440006';

  private const string OUTSIDER_USER_ID = '880e8400-e29b-41d4-a716-446655440007';

  private const string ADMIN_MEMBER_ID = '880e8400-e29b-41d4-a716-446655440020';

  private const string REQUESTER_MEMBER_ID = '880e8400-e29b-41d4-a716-446655440023';

  /**
   * Pending, requested by REQUESTER, whose subject equipment does not exist —
   * approving it re-dispatches DecommissionEquipmentCommand, which no longer
   * applies.
   */
  private const string PENDING_REQUEST_ID = '880e8400-e29b-41d4-a716-446655440030';

  /**
   * Pending, requested by ADMIN — the self-approval case.
   */
  private const string SELF_REQUEST_ID = '880e8400-e29b-41d4-a716-446655440031';

  /**
   * Already approved: any further decision is a conflict.
   */
  private const string DECIDED_REQUEST_ID = '880e8400-e29b-41d4-a716-446655440032';

  /**
   * Pending, owned by the unrelated organization.
   */
  private const string OUTSIDER_REQUEST_ID = '880e8400-e29b-41d4-a716-446655440033';

  private const string MISSING_EQUIPMENT_ID = '880e8400-e29b-41d4-a716-446655440040';
  // #endregion

  // #region Unauthenticated
  #[Test]
  public function testListApprovalRequestsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/approval-requests');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /organizations/{organizationId}/approval-requests endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }

  #[Test]
  public function testGetApprovalRequestRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/approval-requests/' . self::DUMMY_UUID_2);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /organizations/{organizationId}/approval-requests/{requestId} endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }

  #[Test]
  public function testApproveApprovalRequestRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/organizations/' . self::DUMMY_UUID . '/approval-requests/' . self::DUMMY_UUID_2 . '/approve', server: [
      'CONTENT_TYPE' => 'application/json',
    ], content: '{}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'POST .../approve endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }

  #[Test]
  public function testRejectApprovalRequestRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/organizations/' . self::DUMMY_UUID . '/approval-requests/' . self::DUMMY_UUID_2 . '/reject', server: [
      'CONTENT_TYPE' => 'application/json',
    ], content: '{}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'POST .../reject endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }

  #[Test]
  public function testListApprovalActionTypesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/approvals/action-types');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /approvals/action-types endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }
  // #endregion

  // #region Read paths
  #[Test]
  public function testListApprovalRequestsReturnsTheOrganizationQueue(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::READER_USER_ID, 'approval-reader@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/approval-requests');

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

    $decoded = json_decode((string) $response->getContent(), true);
    self::assertIsArray($decoded);
    self::assertIsArray($decoded['member'] ?? null);
    // Exactly the three requests owned by this organization — the fourth
    // belongs to the unrelated one and must not leak into the queue.
    self::assertSame(3, $decoded['totalItems'] ?? null);
  }

  #[Test]
  public function testGetApprovalRequestReturnsTheRequest(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::READER_USER_ID, 'approval-reader@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/approval-requests/' . self::PENDING_REQUEST_ID);

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

    $decoded = json_decode((string) $response->getContent(), true);
    self::assertIsArray($decoded);
    self::assertSame(self::PENDING_REQUEST_ID, $decoded['id'] ?? null);
    self::assertSame('equipment_decommission', $decoded['actionType'] ?? null);
    self::assertSame('pending', $decoded['status'] ?? null);
    self::assertSame(self::REQUESTER_MEMBER_ID, $decoded['requestedByMemberId'] ?? null);
  }

  #[Test]
  public function testListApprovalRequestsRejectsMemberWithoutReadWith403(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::REQUESTER_USER_ID, 'approval-requester@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/approval-requests');

    self::assertSame(403, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  #[Test]
  public function testListApprovalRequestsReturns404ForAnOrganizationTheCallerIsNotAMemberOf(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'approval-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::OUTSIDER_ORGANIZATION_ID . '/approval-requests');

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A caller must get 404 (not 403) when listing the queue of an organization they are not a member of.',
    );
  }

  #[Test]
  public function testGetApprovalRequestReturns404ForARequestInAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'approval-admin@example.com');

    // The caller's own organization in the path, a request id owned by
    // another one: the organization mismatch is hidden behind a 404.
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/approval-requests/' . self::OUTSIDER_REQUEST_ID);

    self::assertSame(404, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  #[Test]
  public function testGetApprovalRequestReturns404WhenBothPathIdsBelongToAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'approval-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::OUTSIDER_ORGANIZATION_ID . '/approval-requests/' . self::OUTSIDER_REQUEST_ID);

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A caller must get 404 (not 403) for a request owned by an organization they are not a member of.',
    );
  }
  // #endregion

  // #region Decision denial paths
  #[Test]
  public function testApproveRejectsMemberWithoutDecideWith403(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::READER_USER_ID, 'approval-reader@example.com');

    $this->postDecision($client, self::PENDING_REQUEST_ID, 'approve');

    self::assertSame(403, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  #[Test]
  public function testRejectRejectsMemberWithoutDecideWith403(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::READER_USER_ID, 'approval-reader@example.com');

    $this->postDecision($client, self::PENDING_REQUEST_ID, 'reject');

    self::assertSame(403, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  #[Test]
  public function testApproveRejectsAnApproverBelowTheMinimumRoleWith403(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::BELOW_ROLE_USER_ID, 'approval-below-role@example.com');

    $this->postDecision($client, self::PENDING_REQUEST_ID, 'approve');

    // Holds organization.approvals.decide, so the permission gate passes;
    // the policy's minApproverRole of `admin` is what denies this caller.
    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A decider below the policy minApproverRole must be denied. Response: ' . $client->getResponse()->getContent(),
    );
  }

  #[Test]
  public function testRejectRejectsAnApproverBelowTheMinimumRoleWith403(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::BELOW_ROLE_USER_ID, 'approval-below-role@example.com');

    $this->postDecision($client, self::PENDING_REQUEST_ID, 'reject');

    self::assertSame(403, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  #[Test]
  public function testApproveRejectsSelfApprovalWith403(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'approval-admin@example.com');

    // SELF_REQUEST_ID was requested by this very member and the organization
    // keeps the default allowSelfApproval = false.
    $this->postDecision($client, self::SELF_REQUEST_ID, 'approve');

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'The requester must not decide on their own request. Response: ' . $client->getResponse()->getContent(),
    );
  }

  #[Test]
  public function testRejectRejectsSelfApprovalWith403(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'approval-admin@example.com');

    $this->postDecision($client, self::SELF_REQUEST_ID, 'reject');

    self::assertSame(403, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  #[Test]
  public function testApproveReturns404ForARequestInAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'approval-admin@example.com');

    $client->request('POST', '/api/organizations/' . self::OUTSIDER_ORGANIZATION_ID . '/approval-requests/' . self::OUTSIDER_REQUEST_ID . '/approve', server: [
      'CONTENT_TYPE' => 'application/ld+json',
    ], content: '{}');

    // The oracle this closes: an unknown id already answered 404 here, so a
    // 403 for a real one confirmed the request exists to an outsider.
    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A caller must get 404 (not 403) when approving a request owned by another organization.',
    );
  }

  #[Test]
  public function testRejectReturns404ForARequestInAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'approval-admin@example.com');

    $client->request('POST', '/api/organizations/' . self::OUTSIDER_ORGANIZATION_ID . '/approval-requests/' . self::OUTSIDER_REQUEST_ID . '/reject', server: [
      'CONTENT_TYPE' => 'application/ld+json',
    ], content: '{}');

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A caller must get 404 (not 403) when rejecting a request owned by another organization.',
    );
  }

  #[Test]
  public function testApproveReturns404ForAnUnknownRequestId(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'approval-admin@example.com');

    $this->postDecision($client, self::DUMMY_UUID_2, 'approve');

    self::assertSame(404, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  #[Test]
  public function testApproveReturns409WhenTheRequestIsAlreadyDecided(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'approval-admin@example.com');

    $this->postDecision($client, self::DECIDED_REQUEST_ID, 'approve');

    self::assertSame(
      expected: 409,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A second decision on a decided request is a conflict. Response: ' . $client->getResponse()->getContent(),
    );
  }

  #[Test]
  public function testRejectReturns409WhenTheRequestIsAlreadyDecided(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'approval-admin@example.com');

    $this->postDecision($client, self::DECIDED_REQUEST_ID, 'reject');

    self::assertSame(409, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  #[Test]
  public function testApproveReturns409AndCancelsWhenTheDeferredActionNoLongerApplies(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'approval-admin@example.com');

    // PENDING_REQUEST_ID defers a decommission of equipment that does not
    // exist: re-dispatching the original command re-validates the subject and
    // finds it gone.
    $this->postDecision($client, self::PENDING_REQUEST_ID, 'approve');

    self::assertSame(
      expected: 409,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A deferred action that no longer applies is a conflict. Response: ' . $client->getResponse()->getContent(),
    );

    // An approved-but-unapplied request must never exist: the request is
    // cancelled instead, with the reason recorded.
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $entityManager->clear();
    $record = $entityManager->find(ApprovalRequestRecord::class, self::PENDING_REQUEST_ID);

    self::assertInstanceOf(ApprovalRequestRecord::class, $record);
    self::assertSame('cancelled', $record->status);
    self::assertNull($record->executedAt, 'A cancelled request must not record an execution.');
    self::assertNotNull($record->executionError, 'The cancellation reason must be recorded.');
  }

  #[Test]
  public function testRejectDecidesTheRequestAndNeverExecutesTheDeferredAction(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'approval-admin@example.com');

    $client->request('POST', '/api/organizations/' . self::ORGANIZATION_ID . '/approval-requests/' . self::PENDING_REQUEST_ID . '/reject', server: [
      'CONTENT_TYPE' => 'application/ld+json',
    ], content: '{"decisionNote":"Equipment still in service."}');

    $response = $client->getResponse();
    // 200: the operation now carries `status: HttpResponse::HTTP_OK`, so the
    // wire matches the 200 `ApprovalRequestResource` always documented for
    // both decisions. It creates nothing.
    self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

    $decoded = json_decode((string) $response->getContent(), true);
    self::assertIsArray($decoded);
    self::assertSame('rejected', $decoded['status'] ?? null);
    self::assertSame(self::ADMIN_MEMBER_ID, $decoded['decisionByMemberId'] ?? null);
    self::assertSame('Equipment still in service.', $decoded['decisionNote'] ?? null);
    // API Platform omits null fields: a rejected request never executed.
    self::assertArrayNotHasKey('executedAt', $decoded);
  }
  // #endregion

  // #region Helpers
  private function postDecision(KernelBrowser $client, string $requestId, string $decision): void
  {
    $client->request('POST', '/api/organizations/' . self::ORGANIZATION_ID . '/approval-requests/' . $requestId . '/' . $decision, server: [
      'CONTENT_TYPE' => 'application/ld+json',
    ], content: '{}');
  }

  /**
   * Authenticates the client against the stateless `api` firewall (the token
   * is stored in the container, not the session).
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

  private function seed(): void
  {
    $this->seedOrganizations();
    $this->seedApprovalRequests();
  }

  private function seedOrganizations(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    foreach ([self::ORGANIZATION_ID, self::OUTSIDER_ORGANIZATION_ID] as $organizationId) {
      $existing = $entityManager->find(OrganizationRecord::class, $organizationId);
      if ($existing instanceof OrganizationRecord) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }
    }

    $now = new DateTimeImmutable('2026-08-18T00:00:00+00:00');

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Approval Test Org';
    $organization->slug = 'approval-test-org-' . self::ORGANIZATION_ID;
    $organization->ownerUserId = self::ADMIN_USER_ID;
    $organization->createdByUserId = self::ADMIN_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $outsiderOrganization = new OrganizationRecord();
    $outsiderOrganization->id = self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->name = 'Approval Outsider Org';
    $outsiderOrganization->slug = 'approval-outsider-org-' . self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->ownerUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->createdByUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->status = 'active';
    $outsiderOrganization->isActive = true;
    $outsiderOrganization->createdAt = $now;
    $outsiderOrganization->updatedAt = $now;
    $entityManager->persist($outsiderOrganization);

    // `organization.*` is what OrganizationApprovalMemberDirectoryAdapter
    // reads as the `admin` approver tier, so a role must NOT hold it to be
    // below the minimum role while still being allowed to call the endpoint.
    $roles = [
      ['880e8400-e29b-41d4-a716-446655440010', $organization, 'approval-full-access', ['*'], 'Functional-test-only role granting every permission.'],
      ['880e8400-e29b-41d4-a716-446655440011', $organization, 'approval-read-only', ['organization.read', 'organization.approvals.read'], 'Functional-test-only role with approval read access only.'],
      ['880e8400-e29b-41d4-a716-446655440012', $organization, 'approval-decide-below-admin', ['organization.read', 'organization.approvals.read', 'organization.approvals.decide'], 'Functional-test-only role allowed to decide but below the admin approver tier.'],
      ['880e8400-e29b-41d4-a716-446655440013', $organization, 'approval-requester', ['organization.read', 'organization.approvals.request'], 'Functional-test-only role that may request but neither read the queue nor decide.'],
      ['880e8400-e29b-41d4-a716-446655440014', $outsiderOrganization, 'approval-outsider-full-access', ['*'], 'Functional-test-only role for the unrelated organization.'],
    ];

    $roleRecords = [];
    foreach ($roles as [$roleId, $roleOrganization, $roleName, $permissions, $description]) {
      $role = new OrganizationRoleRecord();
      $role->id = $roleId;
      $role->organization = $roleOrganization;
      $role->name = $roleName;
      $role->permissions = $permissions;
      $role->description = $description;
      $role->isSystem = false;
      $role->createdAt = $now;
      $entityManager->persist($role);
      $roleRecords[$roleId] = $role;
    }

    $members = [
      [self::ADMIN_MEMBER_ID, $organization, self::ADMIN_USER_ID, '880e8400-e29b-41d4-a716-446655440010'],
      ['880e8400-e29b-41d4-a716-446655440021', $organization, self::READER_USER_ID, '880e8400-e29b-41d4-a716-446655440011'],
      ['880e8400-e29b-41d4-a716-446655440022', $organization, self::BELOW_ROLE_USER_ID, '880e8400-e29b-41d4-a716-446655440012'],
      [self::REQUESTER_MEMBER_ID, $organization, self::REQUESTER_USER_ID, '880e8400-e29b-41d4-a716-446655440013'],
      ['880e8400-e29b-41d4-a716-446655440024', $outsiderOrganization, self::OUTSIDER_USER_ID, '880e8400-e29b-41d4-a716-446655440014'],
    ];

    foreach ($members as [$memberId, $memberOrganization, $userId, $roleId]) {
      $member = new OrganizationMemberRecord();
      $member->id = $memberId;
      $member->organization = $memberOrganization;
      $member->userId = $userId;
      $member->isActive = true;
      $member->joinedAt = $now;
      $entityManager->persist($member);

      $assignment = new OrganizationMemberRoleRecord();
      $assignment->member = $member;
      $assignment->role = $roleRecords[$roleId];
      $assignment->assignedAt = $now;
      $entityManager->persist($assignment);
    }

    $entityManager->flush();
  }

  private function seedApprovalRequests(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    foreach ([self::PENDING_REQUEST_ID, self::SELF_REQUEST_ID, self::DECIDED_REQUEST_ID, self::OUTSIDER_REQUEST_ID] as $requestId) {
      $existing = $entityManager->find(ApprovalRequestRecord::class, $requestId);
      if ($existing instanceof ApprovalRequestRecord) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }
    }

    $now = new DateTimeImmutable('2026-08-18T00:00:00+00:00');
    $expiresAt = new DateTimeImmutable('2026-09-01T00:00:00+00:00');

    $entityManager->persist($this->approvalRequest(
      id: self::PENDING_REQUEST_ID,
      organizationId: self::ORGANIZATION_ID,
      subjectId: self::MISSING_EQUIPMENT_ID,
      status: 'pending',
      requestedByMemberId: self::REQUESTER_MEMBER_ID,
      requestedByUserId: self::REQUESTER_USER_ID,
      now: $now,
      expiresAt: $expiresAt,
    ));

    $entityManager->persist($this->approvalRequest(
      id: self::SELF_REQUEST_ID,
      organizationId: self::ORGANIZATION_ID,
      subjectId: '880e8400-e29b-41d4-a716-446655440041',
      status: 'pending',
      requestedByMemberId: self::ADMIN_MEMBER_ID,
      requestedByUserId: self::ADMIN_USER_ID,
      now: $now,
      expiresAt: $expiresAt,
    ));

    $decided = $this->approvalRequest(
      id: self::DECIDED_REQUEST_ID,
      organizationId: self::ORGANIZATION_ID,
      subjectId: '880e8400-e29b-41d4-a716-446655440042',
      status: 'approved',
      requestedByMemberId: self::REQUESTER_MEMBER_ID,
      requestedByUserId: self::REQUESTER_USER_ID,
      now: $now,
      expiresAt: $expiresAt,
    );
    $decided->decisionByMemberId = self::ADMIN_MEMBER_ID;
    $decided->decisionByUserId = self::ADMIN_USER_ID;
    $decided->decidedAt = $now;
    $decided->executedAt = $now;
    $entityManager->persist($decided);

    $entityManager->persist($this->approvalRequest(
      id: self::OUTSIDER_REQUEST_ID,
      organizationId: self::OUTSIDER_ORGANIZATION_ID,
      subjectId: '880e8400-e29b-41d4-a716-446655440043',
      status: 'pending',
      requestedByMemberId: '880e8400-e29b-41d4-a716-446655440024',
      requestedByUserId: self::OUTSIDER_USER_ID,
      now: $now,
      expiresAt: $expiresAt,
    ));

    $entityManager->flush();
  }

  private function approvalRequest(
    string $id,
    string $organizationId,
    string $subjectId,
    string $status,
    string $requestedByMemberId,
    string $requestedByUserId,
    DateTimeImmutable $now,
    DateTimeImmutable $expiresAt,
  ): ApprovalRequestRecord {
    $record = new ApprovalRequestRecord();
    $record->id = $id;
    $record->organizationId = $organizationId;
    $record->actionType = 'equipment_decommission';
    $record->subjectId = $subjectId;
    $record->status = $status;
    $record->requestedByMemberId = $requestedByMemberId;
    $record->requestedByUserId = $requestedByUserId;
    $record->payload = ['organizationId' => $organizationId, 'equipmentId' => $subjectId];
    $record->expiresAt = $expiresAt;
    $record->createdAt = $now;
    $record->updatedAt = $now;

    return $record;
  }
  // #endregion
}
