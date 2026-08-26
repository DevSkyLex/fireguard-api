<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_decode;
use function json_encode;

/**
 * Test CanonicalInspectionApiTest.
 *
 * The flat `PATCH`/`DELETE /api/inspections/{id}` contract, frozen while its
 * mutations moved out of `CanonicalInspectionMutationProcessor` into use
 * cases. Every status asserted here is one the processor emitted before the
 * move — this file is the proof that the refactor changed no answer.
 *
 * One HTTP request per test: the token `loginUser()` sets does not reliably
 * survive a second one in this suite, and a stray 401 would hide the
 * difference being measured.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CanonicalInspectionApiTest extends WebTestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '880e8400-e29b-41d4-a716-446655492001';

  private const string OUTSIDER_ORGANIZATION_ID = '880e8400-e29b-41d4-a716-446655492002';

  private const string ADMIN_USER_ID = '880e8400-e29b-41d4-a716-446655492003';

  private const string READ_ONLY_USER_ID = '880e8400-e29b-41d4-a716-446655492004';

  private const string OUTSIDER_USER_ID = '880e8400-e29b-41d4-a716-446655492005';

  private const string SUBMITTED_ID = '880e8400-e29b-41d4-a716-446655492010';

  private const string DRAFT_STATUS_ID = '880e8400-e29b-41d4-a716-446655492011';

  private const string CLOSED_ID = '880e8400-e29b-41d4-a716-446655492012';

  private const string CANCELLED_ID = '880e8400-e29b-41d4-a716-446655492013';

  private const string SCRATCHPAD_ID = '880e8400-e29b-41d4-a716-446655492014';

  private const string OUTSIDER_ID = '880e8400-e29b-41d4-a716-446655492015';

  private const string UNKNOWN_ID = '880e8400-e29b-41d4-a716-4466554920ff';
  // #endregion

  // #region Tests
  /**
   * Method testPatchClosesASubmittedInspectionAndBumpsTheRevision.
   *
   * @return void no return value
   */
  #[Test]
  public function testPatchClosesASubmittedInspectionAndBumpsTheRevision(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'PATCH',
      '/api/inspections/' . self::SUBMITTED_ID,
      server: $this->headers(['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"revision-1"']),
      content: (string) json_encode(['status' => 'closed']),
    );

    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $payload = $this->payload($client);
    self::assertSame('closed', $payload['status']);
    self::assertSame(2, $payload['revision']);
  }

  /**
   * Method testPatchErasesASignatureSentAsNullAndKeepsAnAbsentField.
   *
   * The merge-patch distinction the processor still owns: `"signature": null`
   * erases, an absent `result` is left alone.
   *
   * @return void no return value
   */
  #[Test]
  public function testPatchErasesASignatureSentAsNullAndKeepsAnAbsentField(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'PATCH',
      '/api/inspections/' . self::SUBMITTED_ID,
      server: $this->headers(['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"revision-1"']),
      content: (string) json_encode(['notes' => 'Updated notes', 'signature' => null]),
    );

    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $payload = $this->payload($client);
    self::assertSame('Updated notes', $payload['notes']);
    self::assertNull($payload['signature']);
    self::assertSame('pass', $payload['result']);
  }

  /**
   * Method testAnIllegalPublishedTransitionIsUnprocessable.
   *
   * 422, not 409 — the canonical surface's answer, which differs from the
   * aggregate's for the same jump. See `src/Inspection/MODULE.md`.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnIllegalPublishedTransitionIsUnprocessable(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'PATCH',
      '/api/inspections/' . self::DRAFT_STATUS_ID,
      server: $this->headers(['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"revision-1"']),
      content: (string) json_encode(['status' => 'closed']),
    );

    self::assertSame(422, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertStringContainsString('Illegal inspection status transition from draft to closed.', (string) $client->getResponse()->getContent());
  }

  /**
   * Method testANullStatusIsUnprocessable.
   *
   * @return void no return value
   */
  #[Test]
  public function testANullStatusIsUnprocessable(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'PATCH',
      '/api/inspections/' . self::SUBMITTED_ID,
      server: $this->headers(['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"revision-1"']),
      content: (string) json_encode(['status' => null]),
    );

    self::assertSame(422, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testPatchOnACancelledInspectionIsAConflict.
   *
   * @return void no return value
   */
  #[Test]
  public function testPatchOnACancelledInspectionIsAConflict(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'PATCH',
      '/api/inspections/' . self::CANCELLED_ID,
      server: $this->headers(['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"revision-1"']),
      content: (string) json_encode(['notes' => 'Anything']),
    );

    self::assertSame(409, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertStringContainsString('Closed or cancelled inspections are immutable.', (string) $client->getResponse()->getContent());
  }

  /**
   * Method testDeleteCancelsAPublishedInspection.
   *
   * @return void no return value
   */
  #[Test]
  public function testDeleteCancelsAPublishedInspection(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'DELETE',
      '/api/inspections/' . self::SUBMITTED_ID,
      server: $this->headers(['HTTP_IF_MATCH' => '"revision-1"']),
    );

    self::assertSame(204, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testDeleteIsIdempotentOnAnAlreadyCancelledInspection.
   *
   * @return void no return value
   */
  #[Test]
  public function testDeleteIsIdempotentOnAnAlreadyCancelledInspection(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'DELETE',
      '/api/inspections/' . self::CANCELLED_ID,
      server: $this->headers(['HTTP_IF_MATCH' => '"revision-1"']),
    );

    self::assertSame(204, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testDeleteHardDeletesAScratchpadRow.
   *
   * @return void no return value
   */
  #[Test]
  public function testDeleteHardDeletesAScratchpadRow(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'DELETE',
      '/api/inspections/' . self::SCRATCHPAD_ID,
      server: $this->headers(['HTTP_IF_MATCH' => '"revision-1"']),
    );

    self::assertSame(204, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testDeleteOnAClosedInspectionIsAConflict.
   *
   * @return void no return value
   */
  #[Test]
  public function testDeleteOnAClosedInspectionIsAConflict(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'DELETE',
      '/api/inspections/' . self::CLOSED_ID,
      server: $this->headers(['HTTP_IF_MATCH' => '"revision-1"']),
    );

    self::assertSame(409, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertStringContainsString('Closed inspections are immutable.', (string) $client->getResponse()->getContent());
  }

  /**
   * Method testAStaleRevisionAnswersPreconditionFailed.
   *
   * @return void no return value
   */
  #[Test]
  public function testAStaleRevisionAnswersPreconditionFailed(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'DELETE',
      '/api/inspections/' . self::SUBMITTED_ID,
      server: $this->headers(['HTTP_IF_MATCH' => '"revision-99"']),
    );

    self::assertSame(412, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testAnUnknownInspectionAnswersNotFoundEvenWithoutIfMatch.
   *
   * The ordering contract: absent answers 404 before `RevisionGuard` gets to
   * raise its 428 for the missing header.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnUnknownInspectionAnswersNotFoundEvenWithoutIfMatch(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request('DELETE', '/api/inspections/' . self::UNKNOWN_ID, server: $this->headers());

    self::assertSame(404, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testAMalformedIdentifierAnswersNotFound.
   *
   * The mutation lookups did not narrow when the identifier became a value
   * object.
   *
   * @return void no return value
   */
  #[Test]
  public function testAMalformedIdentifierAnswersNotFound(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request('DELETE', '/api/inspections/not-a-uuid', server: $this->headers());

    self::assertSame(404, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testAForeignInspectionAnswersNotFoundRatherThanForbidden.
   *
   * The row is loaded by GLOBAL id, so a 403 would confirm that id exists in
   * another tenant.
   *
   * @return void no return value
   */
  #[Test]
  public function testAForeignInspectionAnswersNotFoundRatherThanForbidden(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'DELETE',
      '/api/inspections/' . self::OUTSIDER_ID,
      server: $this->headers(['HTTP_IF_MATCH' => '"revision-1"']),
    );

    self::assertSame(404, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testAMemberWithoutWritePermissionIsForbidden.
   *
   * @return void no return value
   */
  #[Test]
  public function testAMemberWithoutWritePermissionIsForbidden(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::READ_ONLY_USER_ID, 'reader@example.com');

    $client->request(
      'DELETE',
      '/api/inspections/' . self::SUBMITTED_ID,
      server: $this->headers(['HTTP_IF_MATCH' => '"revision-1"']),
    );

    self::assertSame(403, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }
  // #endregion

  // #region Helpers
  /**
   * Method headers.
   *
   * @param array<string, string> $extra additional server parameters
   *
   * @return array<string, string> the server parameters
   */
  private function headers(array $extra = []): array
  {
    // `$extra` first: `+` keeps the LEFT operand's keys, so defaults placed
    // first would silently ignore a CONTENT_TYPE override.
    return $extra + ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];
  }

  /**
   * Method payload.
   *
   * @param KernelBrowser $client the browser holding the response
   *
   * @return array<string, mixed> the decoded body
   */
  private function payload(KernelBrowser $client): array
  {
    /** @var array<string, mixed> $decoded */
    $decoded = json_decode((string) $client->getResponse()->getContent(), true);

    return $decoded;
  }

  /**
   * Method loginAs.
   *
   * @param KernelBrowser $client the browser to authenticate
   * @param string $userId the user identifier
   * @param string $email the user email
   */
  private function loginAs(KernelBrowser $client, string $userId, string $email): void
  {
    $client->loginUser(
      new SecurityUser(id: $userId, email: $email, password: 'hashed-password', roles: ['ROLE_USER']),
      'api',
    );
  }

  /**
   * Method seed.
   *
   * Rebuilds the fixture set on every test: two organizations, three callers
   * and one inspection per canonical state.
   */
  private function seed(): void
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

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organization = $this->newOrganization(self::ORGANIZATION_ID, self::ADMIN_USER_ID, $now);
    $outsiderOrganization = $this->newOrganization(self::OUTSIDER_ORGANIZATION_ID, self::OUTSIDER_USER_ID, $now);
    $entityManager->persist($organization);
    $entityManager->persist($outsiderOrganization);

    $entityManager->persist($this->newRole('880e8400-e29b-41d4-a716-446655492040', $organization, 'canonical-full-access', ['*'], $now));
    $entityManager->persist($this->newRole('880e8400-e29b-41d4-a716-446655492041', $organization, 'canonical-read-only', ['organization.inspection.read'], $now));
    $entityManager->persist($this->newRole('880e8400-e29b-41d4-a716-446655492042', $outsiderOrganization, 'canonical-outsider', ['*'], $now));
    $entityManager->flush();

    $this->assignMember($entityManager, '880e8400-e29b-41d4-a716-446655492050', $organization, self::ADMIN_USER_ID, '880e8400-e29b-41d4-a716-446655492040', $now);
    $this->assignMember($entityManager, '880e8400-e29b-41d4-a716-446655492051', $organization, self::READ_ONLY_USER_ID, '880e8400-e29b-41d4-a716-446655492041', $now);
    $this->assignMember($entityManager, '880e8400-e29b-41d4-a716-446655492052', $outsiderOrganization, self::OUTSIDER_USER_ID, '880e8400-e29b-41d4-a716-446655492042', $now);
    $entityManager->flush();

    $entityManager->persist($this->newInspection(self::SUBMITTED_ID, $organization, 'submitted', 'published', $now));
    $entityManager->persist($this->newInspection(self::DRAFT_STATUS_ID, $organization, 'draft', 'published', $now));
    $entityManager->persist($this->newInspection(self::CLOSED_ID, $organization, 'closed', 'published', $now));
    $entityManager->persist($this->newInspection(self::CANCELLED_ID, $organization, 'cancelled', 'published', $now));
    $entityManager->persist($this->newInspection(self::SCRATCHPAD_ID, $organization, 'draft', 'draft', $now));
    $entityManager->persist($this->newInspection(self::OUTSIDER_ID, $outsiderOrganization, 'submitted', 'published', $now));
    $entityManager->flush();
    $entityManager->clear();
  }

  /**
   * Method newOrganization.
   *
   * @param string $id the organization identifier
   * @param string $ownerUserId the owner user identifier
   * @param DateTimeImmutable $now the fixed clock
   *
   * @return OrganizationRecord the organization record
   */
  private function newOrganization(string $id, string $ownerUserId, DateTimeImmutable $now): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Canonical Inspection API Test Org ' . $id;
    $organization->slug = 'canonical-inspection-api-test-' . $id;
    $organization->ownerUserId = $ownerUserId;
    $organization->createdByUserId = $ownerUserId;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;

    return $organization;
  }

  /**
   * Method newRole.
   *
   * @param string $id the role identifier
   * @param OrganizationRecord $organization the owning organization
   * @param string $name the role name
   * @param list<string> $permissions the granted permissions
   * @param DateTimeImmutable $now the fixed clock
   *
   * @return OrganizationRoleRecord the role record
   */
  private function newRole(string $id, OrganizationRecord $organization, string $name, array $permissions, DateTimeImmutable $now): OrganizationRoleRecord
  {
    $role = new OrganizationRoleRecord();
    $role->id = $id;
    $role->organization = $organization;
    $role->name = $name;
    $role->permissions = $permissions;
    $role->description = 'Functional-test-only role.';
    $role->isSystem = false;
    $role->createdAt = $now;

    return $role;
  }

  /**
   * Method assignMember.
   *
   * @param EntityManagerInterface $entityManager the main entity manager
   * @param string $memberId the membership identifier
   * @param OrganizationRecord $organization the owning organization
   * @param string $userId the user identifier
   * @param string $roleId the role to assign
   * @param DateTimeImmutable $now the fixed clock
   */
  private function assignMember(
    EntityManagerInterface $entityManager,
    string $memberId,
    OrganizationRecord $organization,
    string $userId,
    string $roleId,
    DateTimeImmutable $now,
  ): void {
    $member = new OrganizationMemberRecord();
    $member->id = $memberId;
    $member->organization = $organization;
    $member->userId = $userId;
    $member->isActive = true;
    $member->joinedAt = $now;
    $entityManager->persist($member);

    $role = $entityManager->find(OrganizationRoleRecord::class, $roleId);
    self::assertInstanceOf(OrganizationRoleRecord::class, $role);

    $assignment = new OrganizationMemberRoleRecord();
    $assignment->member = $member;
    $assignment->role = $role;
    $assignment->assignedAt = $now;
    $entityManager->persist($assignment);
  }

  /**
   * Method newInspection.
   *
   * @param string $id the inspection identifier
   * @param OrganizationRecord $organization the owning organization
   * @param string $status the lifecycle status
   * @param string $recordStatus the record status
   * @param DateTimeImmutable $now the fixed clock
   *
   * @return InspectionRecord the inspection record
   */
  private function newInspection(
    string $id,
    OrganizationRecord $organization,
    string $status,
    string $recordStatus,
    DateTimeImmutable $now,
  ): InspectionRecord {
    $inspection = new InspectionRecord();
    $inspection->id = $id;
    $inspection->organization = $organization;
    $inspection->recordStatus = $recordStatus;
    $inspection->revision = 1;
    $inspection->equipmentId = '880e8400-e29b-41d4-a716-446655492060';
    $inspection->inspectorType = 'user';
    $inspection->inspectorName = 'Test Inspector';
    $inspection->result = 'pass';
    $inspection->status = $status;
    $inspection->signature = 'Seeded signature';
    $inspection->performedAt = $now;
    $inspection->createdAt = $now;
    $inspection->updatedAt = $now;

    return $inspection;
  }
  // #endregion
}
