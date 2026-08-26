<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, InspectionResponseRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_decode;
use function json_encode;

/**
 * Test InspectionResponseApiTest.
 *
 * The `/inspection-responses` contract, frozen while its mutations moved out
 * of `InspectionResponseProcessor` into use cases. Every status and every
 * `hydra:description` asserted here is one the processor emitted before the
 * move — this file is the proof that the refactor changed no answer.
 *
 * The one deliberate narrowing is
 * {@see testPutWithAMalformedIdentifierIsRejected}, and it is asserted rather
 * than assumed.
 *
 * One HTTP request per test: the token `loginUser()` sets does not reliably
 * survive a second one in this suite, and a stray 401 would hide the
 * difference being measured.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InspectionResponseApiTest extends WebTestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '870e8400-e29b-41d4-a716-446655491001';

  private const string OUTSIDER_ORGANIZATION_ID = '870e8400-e29b-41d4-a716-446655491002';

  private const string ADMIN_USER_ID = '870e8400-e29b-41d4-a716-446655491003';

  private const string READ_ONLY_USER_ID = '870e8400-e29b-41d4-a716-446655491004';

  private const string OUTSIDER_USER_ID = '870e8400-e29b-41d4-a716-446655491005';

  private const string INSPECTION_ID = '870e8400-e29b-41d4-a716-446655491010';

  private const string OUTSIDER_INSPECTION_ID = '870e8400-e29b-41d4-a716-446655491011';

  private const string DRAFT_RESPONSE_ID = '870e8400-e29b-41d4-a716-446655491020';

  private const string PUBLISHED_RESPONSE_ID = '870e8400-e29b-41d4-a716-446655491021';

  private const string OUTSIDER_RESPONSE_ID = '870e8400-e29b-41d4-a716-446655491022';

  private const string KNOWN_CLIENT_ID = '870e8400-e29b-41d4-a716-446655491030';

  private const string UNKNOWN_RESPONSE_ID = '870e8400-e29b-41d4-a716-4466554910ff';
  // #endregion

  // #region Tests
  /**
   * Method testPostCreatesAPublishedResponse.
   *
   * @return void no return value
   */
  #[Test]
  public function testPostCreatesAPublishedResponse(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request('POST', '/api/inspection-responses', server: $this->jsonHeaders(), content: (string) json_encode([
      'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
      'inspection' => '/api/inspections/' . self::INSPECTION_ID,
      'itemKey' => 'pressure',
      'value' => ['ok' => true],
    ]));

    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $payload = $this->payload($client);
    self::assertSame('published', $payload['recordStatus']);
    self::assertSame(1, $payload['revision']);
    self::assertSame('pressure', $payload['itemKey']);
    self::assertNull($payload['intervention']);
  }

  /**
   * Method testPostWithAKnownClientIdentifierIsAConflict.
   *
   * @return void no return value
   */
  #[Test]
  public function testPostWithAKnownClientIdentifierIsAConflict(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request('POST', '/api/inspection-responses', server: $this->jsonHeaders(), content: (string) json_encode([
      'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
      'inspection' => '/api/inspections/' . self::INSPECTION_ID,
      'itemKey' => 'pressure',
      'clientId' => self::KNOWN_CLIENT_ID,
    ]));

    self::assertSame(409, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testPutWithAKnownClientIdentifierIsPreconditionFailed.
   *
   * Same domain failure as the POST above, different status — the split the
   * processor's single remaining catch exists for.
   *
   * @return void no return value
   */
  #[Test]
  public function testPutWithAKnownClientIdentifierIsPreconditionFailed(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'PUT',
      '/api/inspection-responses/' . self::KNOWN_CLIENT_ID,
      server: $this->jsonHeaders(['HTTP_IF_NONE_MATCH' => '*']),
      content: (string) json_encode([
        'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
        'inspection' => '/api/inspections/' . self::INSPECTION_ID,
        'itemKey' => 'pressure',
      ]),
    );

    self::assertSame(412, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testPutWithAMalformedIdentifierIsRejected.
   *
   * **A deliberate narrowing, decided 2026-08-26.** The URI identifier is now
   * an `InspectionResponseId` value object, so a malformed one answers 400
   * where it used to be persisted verbatim and answer 201. `clientId` already
   * carried `#[Assert\Uuid]` for the same field in the POST body; the PUT
   * route bypassed it because the processor overwrote `clientId` AFTER
   * validation had run.
   *
   * Reads did NOT narrow — see {@see testPatchOnAMalformedIdentifierAnswersNotFound}.
   *
   * @return void no return value
   */
  #[Test]
  public function testPutWithAMalformedIdentifierIsRejected(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'PUT',
      '/api/inspection-responses/not-a-uuid',
      server: $this->jsonHeaders(['HTTP_IF_NONE_MATCH' => '*']),
      content: (string) json_encode([
        'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
        'inspection' => '/api/inspections/' . self::INSPECTION_ID,
        'itemKey' => 'pressure',
      ]),
    );

    self::assertSame(400, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testPatchBumpsTheRevisionOfADraft.
   *
   * @return void no return value
   */
  #[Test]
  public function testPatchBumpsTheRevisionOfADraft(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'PATCH',
      '/api/inspection-responses/' . self::DRAFT_RESPONSE_ID,
      server: $this->jsonHeaders(['HTTP_IF_MATCH' => '"revision-1"', 'CONTENT_TYPE' => 'application/merge-patch+json']),
      content: (string) json_encode(['value' => ['ok' => false]]),
    );

    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $payload = $this->payload($client);
    self::assertSame(2, $payload['revision']);
    self::assertSame(['ok' => false], $payload['value']);
  }

  /**
   * Method testPatchOnAPublishedResponseIsAConflict.
   *
   * @return void no return value
   */
  #[Test]
  public function testPatchOnAPublishedResponseIsAConflict(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'PATCH',
      '/api/inspection-responses/' . self::PUBLISHED_RESPONSE_ID,
      server: $this->jsonHeaders(['HTTP_IF_MATCH' => '"revision-1"', 'CONTENT_TYPE' => 'application/merge-patch+json']),
      content: (string) json_encode(['value' => ['ok' => false]]),
    );

    self::assertSame(409, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertStringContainsString('Published inspection responses are immutable.', (string) $client->getResponse()->getContent());
  }

  /**
   * Method testDeleteRemovesADraft.
   *
   * @return void no return value
   */
  #[Test]
  public function testDeleteRemovesADraft(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'DELETE',
      '/api/inspection-responses/' . self::DRAFT_RESPONSE_ID,
      server: $this->jsonHeaders(['HTTP_IF_MATCH' => '"revision-1"']),
    );

    self::assertSame(204, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testDeleteOnAPublishedResponseIsAConflict.
   *
   * @return void no return value
   */
  #[Test]
  public function testDeleteOnAPublishedResponseIsAConflict(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'DELETE',
      '/api/inspection-responses/' . self::PUBLISHED_RESPONSE_ID,
      server: $this->jsonHeaders(['HTTP_IF_MATCH' => '"revision-1"']),
    );

    self::assertSame(409, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertStringContainsString('Published inspection responses cannot be deleted.', (string) $client->getResponse()->getContent());
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
      '/api/inspection-responses/' . self::DRAFT_RESPONSE_ID,
      server: $this->jsonHeaders(['HTTP_IF_MATCH' => '"revision-99"']),
    );

    self::assertSame(412, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testPatchOnAnUnknownResponseAnswersNotFoundEvenWithoutIfMatch.
   *
   * The ordering contract: absent answers 404 before `RevisionGuard` gets to
   * raise its 428 for the missing header. Reading `If-Match` before the row
   * is the natural way to break this.
   *
   * @return void no return value
   */
  #[Test]
  public function testPatchOnAnUnknownResponseAnswersNotFoundEvenWithoutIfMatch(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'PATCH',
      '/api/inspection-responses/' . self::UNKNOWN_RESPONSE_ID,
      server: $this->jsonHeaders(['CONTENT_TYPE' => 'application/merge-patch+json']),
      content: (string) json_encode(['value' => null]),
    );

    self::assertSame(404, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testPatchOnAMalformedIdentifierAnswersNotFound.
   *
   * The mutation lookups did NOT narrow with the value object: a malformed id
   * is still "no such response", never "bad request".
   *
   * @return void no return value
   */
  #[Test]
  public function testPatchOnAMalformedIdentifierAnswersNotFound(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'PATCH',
      '/api/inspection-responses/not-a-uuid',
      server: $this->jsonHeaders(['CONTENT_TYPE' => 'application/merge-patch+json']),
      content: (string) json_encode(['value' => null]),
    );

    self::assertSame(404, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testAForeignResponseAnswersNotFoundRatherThanForbidden.
   *
   * 403 would confirm the row exists, which is the enumeration oracle closed
   * across this module on 2026-08-26.
   *
   * @return void no return value
   */
  #[Test]
  public function testAForeignResponseAnswersNotFoundRatherThanForbidden(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'DELETE',
      '/api/inspection-responses/' . self::OUTSIDER_RESPONSE_ID,
      server: $this->jsonHeaders(['HTTP_IF_MATCH' => '"revision-1"']),
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
      '/api/inspection-responses/' . self::DRAFT_RESPONSE_ID,
      server: $this->jsonHeaders(['HTTP_IF_MATCH' => '"revision-1"']),
    );

    self::assertSame(403, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }
  // #endregion

  // #region Helpers
  /**
   * Method jsonHeaders.
   *
   * @param array<string, string> $extra additional server parameters
   *
   * @return array<string, string> the server parameters
   */
  private function jsonHeaders(array $extra = []): array
  {
    // `$extra` first: `+` keeps the LEFT operand's keys, so putting the
    // defaults first would silently ignore a CONTENT_TYPE override — which is
    // exactly how the PATCH cases first answered 415.
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
   * Rebuilds the whole fixture set on every test: two organizations, three
   * callers, two inspections and three responses.
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

    $entityManager->persist($this->newRole('870e8400-e29b-41d4-a716-446655491040', $organization, 'response-full-access', ['*'], $now));
    $entityManager->persist($this->newRole('870e8400-e29b-41d4-a716-446655491041', $organization, 'response-read-only', ['organization.inspection.read'], $now));
    $entityManager->persist($this->newRole('870e8400-e29b-41d4-a716-446655491042', $outsiderOrganization, 'response-outsider', ['*'], $now));
    $entityManager->flush();

    $this->assignMember($entityManager, '870e8400-e29b-41d4-a716-446655491050', $organization, self::ADMIN_USER_ID, '870e8400-e29b-41d4-a716-446655491040', $now);
    $this->assignMember($entityManager, '870e8400-e29b-41d4-a716-446655491051', $organization, self::READ_ONLY_USER_ID, '870e8400-e29b-41d4-a716-446655491041', $now);
    $this->assignMember($entityManager, '870e8400-e29b-41d4-a716-446655491052', $outsiderOrganization, self::OUTSIDER_USER_ID, '870e8400-e29b-41d4-a716-446655491042', $now);
    $entityManager->flush();

    $entityManager->persist($this->newInspection(self::INSPECTION_ID, $organization, $now));
    $entityManager->persist($this->newInspection(self::OUTSIDER_INSPECTION_ID, $outsiderOrganization, $now));
    $entityManager->flush();

    $entityManager->persist($this->newResponse(self::DRAFT_RESPONSE_ID, $organization, self::INSPECTION_ID, 'draft', null, $now));
    $entityManager->persist($this->newResponse(self::PUBLISHED_RESPONSE_ID, $organization, self::INSPECTION_ID, 'published', self::KNOWN_CLIENT_ID, $now));
    $entityManager->persist($this->newResponse(self::OUTSIDER_RESPONSE_ID, $outsiderOrganization, self::OUTSIDER_INSPECTION_ID, 'draft', null, $now));
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
    $organization->name = 'Inspection Response API Test Org ' . $id;
    $organization->slug = 'inspection-response-api-test-' . $id;
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
   * @param DateTimeImmutable $now the fixed clock
   *
   * @return InspectionRecord the inspection record
   */
  private function newInspection(string $id, OrganizationRecord $organization, DateTimeImmutable $now): InspectionRecord
  {
    $inspection = new InspectionRecord();
    $inspection->id = $id;
    $inspection->organization = $organization;
    $inspection->equipmentId = '870e8400-e29b-41d4-a716-446655491060';
    $inspection->inspectorType = 'user';
    $inspection->inspectorName = 'Test Inspector';
    $inspection->result = 'fail';
    $inspection->status = 'submitted';
    $inspection->performedAt = $now;
    $inspection->createdAt = $now;
    $inspection->updatedAt = $now;

    return $inspection;
  }

  /**
   * Method newResponse.
   *
   * @param string $id the response identifier
   * @param OrganizationRecord $organization the owning organization
   * @param string $inspectionId the inspection identifier
   * @param string $recordStatus the lifecycle status
   * @param ?string $clientId the offline client identifier
   * @param DateTimeImmutable $now the fixed clock
   *
   * @return InspectionResponseRecord the response record
   */
  private function newResponse(
    string $id,
    OrganizationRecord $organization,
    string $inspectionId,
    string $recordStatus,
    ?string $clientId,
    DateTimeImmutable $now,
  ): InspectionResponseRecord {
    $response = new InspectionResponseRecord();
    $response->id = $id;
    $response->organization = $organization;
    $response->inspectionId = $inspectionId;
    $response->clientId = $clientId;
    $response->recordStatus = $recordStatus;
    $response->revision = 1;
    $response->itemKey = 'pressure';
    $response->value = ['ok' => true];
    $response->createdAt = $now;
    $response->updatedAt = $now;

    return $response;
  }
  // #endregion
}
