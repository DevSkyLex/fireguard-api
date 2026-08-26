<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_decode;
use function json_encode;

/**
 * Test CanonicalFacilityApiTest.
 *
 * The flat `PATCH`/`DELETE /api/facilities/{id}` contract, frozen while its
 * mutations moved out of `CanonicalFacilityMutationProcessor` into use cases.
 * Every status asserted here is one the processor emitted before the move.
 *
 * One HTTP request per test: the token `loginUser()` sets does not reliably
 * survive a second one in this suite, and a stray 401 would hide the
 * difference being measured.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CanonicalFacilityApiTest extends WebTestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '8a0e8400-e29b-41d4-a716-446655494001';

  private const string OUTSIDER_ORGANIZATION_ID = '8a0e8400-e29b-41d4-a716-446655494002';

  private const string ADMIN_USER_ID = '8a0e8400-e29b-41d4-a716-446655494003';

  private const string READ_ONLY_USER_ID = '8a0e8400-e29b-41d4-a716-446655494004';

  private const string OUTSIDER_USER_ID = '8a0e8400-e29b-41d4-a716-446655494005';

  private const string ROOT_ID = '8a0e8400-e29b-41d4-a716-446655494010';

  private const string CHILD_ID = '8a0e8400-e29b-41d4-a716-446655494011';

  private const string ARCHIVED_ID = '8a0e8400-e29b-41d4-a716-446655494012';

  private const string ARCHIVED_CHILD_ID = '8a0e8400-e29b-41d4-a716-446655494013';

  private const string SCRATCHPAD_ID = '8a0e8400-e29b-41d4-a716-446655494014';

  private const string SCRATCHPAD_PARENT_ID = '8a0e8400-e29b-41d4-a716-446655494015';

  private const string SCRATCHPAD_CHILD_ID = '8a0e8400-e29b-41d4-a716-446655494016';

  private const string OUTSIDER_FACILITY_ID = '8a0e8400-e29b-41d4-a716-446655494017';

  private const string UNKNOWN_ID = '8a0e8400-e29b-41d4-a716-4466554940ff';
  // #endregion

  // #region Tests
  /**
   * Method testPatchRenamesAFacilityAndBumpsTheRevision.
   *
   * @return void no return value
   */
  #[Test]
  public function testPatchRenamesAFacilityAndBumpsTheRevision(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'PATCH',
      '/api/facilities/' . self::CHILD_ID,
      server: $this->headers(['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"revision-1"']),
      content: (string) json_encode(['name' => '  Renamed child  ']),
    );

    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $payload = $this->payload($client);
    self::assertSame('Renamed child', $payload['name'], 'The name is trimmed.');
    self::assertSame(2, $payload['revision']);
  }

  /**
   * Method testPatchErasesACodeSentAsNullAndKeepsAnAbsentField.
   *
   * @return void no return value
   */
  #[Test]
  public function testPatchErasesACodeSentAsNullAndKeepsAnAbsentField(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'PATCH',
      '/api/facilities/' . self::CHILD_ID,
      server: $this->headers(['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"revision-1"']),
      content: (string) json_encode(['code' => null]),
    );

    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $payload = $this->payload($client);
    self::assertNull($payload['code']);
    self::assertSame('1 Rue de la Paix', $payload['address']);
  }

  /**
   * Method testANullNameIsUnprocessable.
   *
   * @return void no return value
   */
  #[Test]
  public function testANullNameIsUnprocessable(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'PATCH',
      '/api/facilities/' . self::CHILD_ID,
      server: $this->headers(['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"revision-1"']),
      content: (string) json_encode(['name' => null]),
    );

    self::assertSame(422, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertStringContainsString('Facility name cannot be null.', (string) $client->getResponse()->getContent());
  }

  /**
   * Method testASingleCoordinateIsUnprocessable.
   *
   * @return void no return value
   */
  #[Test]
  public function testASingleCoordinateIsUnprocessable(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'PATCH',
      '/api/facilities/' . self::CHILD_ID,
      server: $this->headers(['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"revision-1"']),
      content: (string) json_encode(['latitude' => 48.85]),
    );

    self::assertSame(422, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertStringContainsString('Facility latitude and longitude must be provided together.', (string) $client->getResponse()->getContent());
  }

  /**
   * Method testAParentThatIsADescendantIsUnprocessable.
   *
   * @return void no return value
   */
  #[Test]
  public function testAParentThatIsADescendantIsUnprocessable(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'PATCH',
      '/api/facilities/' . self::ROOT_ID,
      server: $this->headers(['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"revision-1"']),
      content: (string) json_encode(['parent' => '/api/facilities/' . self::CHILD_ID]),
    );

    self::assertSame(422, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertStringContainsString('Parent facility would create a hierarchy cycle.', (string) $client->getResponse()->getContent());
  }

  /**
   * Method testAParentFromAnotherOrganizationIsUnprocessable.
   *
   * @return void no return value
   */
  #[Test]
  public function testAParentFromAnotherOrganizationIsUnprocessable(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'PATCH',
      '/api/facilities/' . self::CHILD_ID,
      server: $this->headers(['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"revision-1"']),
      content: (string) json_encode(['parent' => '/api/facilities/' . self::OUTSIDER_FACILITY_ID]),
    );

    self::assertSame(422, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertStringContainsString('Parent facility is invalid.', (string) $client->getResponse()->getContent());
  }

  /**
   * Method testAnArchivedParentIsUnprocessable.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnArchivedParentIsUnprocessable(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'PATCH',
      '/api/facilities/' . self::CHILD_ID,
      server: $this->headers(['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"revision-1"']),
      content: (string) json_encode(['parent' => '/api/facilities/' . self::ARCHIVED_ID]),
    );

    self::assertSame(422, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertStringContainsString('Parent facility is archived.', (string) $client->getResponse()->getContent());
  }

  /**
   * Method testRestoringUnderAnArchivedParentIsUnprocessable.
   *
   * The patch never mentions `parent`: the guard has to resolve the parent
   * the facility already hangs from.
   *
   * @return void no return value
   */
  #[Test]
  public function testRestoringUnderAnArchivedParentIsUnprocessable(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'PATCH',
      '/api/facilities/' . self::ARCHIVED_CHILD_ID,
      server: $this->headers(['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"revision-1"']),
      content: (string) json_encode(['status' => 'active']),
    );

    self::assertSame(422, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertStringContainsString('Cannot restore a facility while its parent is archived.', (string) $client->getResponse()->getContent());
  }

  /**
   * Method testArchivingAFacilityWithALiveChildIsAConflict.
   *
   * @return void no return value
   */
  #[Test]
  public function testArchivingAFacilityWithALiveChildIsAConflict(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'PATCH',
      '/api/facilities/' . self::ROOT_ID,
      server: $this->headers(['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"revision-1"']),
      content: (string) json_encode(['status' => 'archived']),
    );

    self::assertSame(409, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testDeleteArchivesAPublishedFacility.
   *
   * @return void no return value
   */
  #[Test]
  public function testDeleteArchivesAPublishedFacility(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'DELETE',
      '/api/facilities/' . self::CHILD_ID,
      server: $this->headers(['HTTP_IF_MATCH' => '"revision-1"']),
    );

    self::assertSame(204, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testDeleteIsIdempotentOnAnAlreadyArchivedFacility.
   *
   * @return void no return value
   */
  #[Test]
  public function testDeleteIsIdempotentOnAnAlreadyArchivedFacility(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'DELETE',
      '/api/facilities/' . self::ARCHIVED_ID,
      server: $this->headers(['HTTP_IF_MATCH' => '"revision-1"']),
    );

    self::assertSame(204, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testDeleteHardDeletesAChildlessScratchpadRow.
   *
   * @return void no return value
   */
  #[Test]
  public function testDeleteHardDeletesAChildlessScratchpadRow(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'DELETE',
      '/api/facilities/' . self::SCRATCHPAD_ID,
      server: $this->headers(['HTTP_IF_MATCH' => '"revision-1"']),
    );

    self::assertSame(204, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testDeletingAScratchpadWithChildrenIsAConflict.
   *
   * The foreign key is `ON DELETE SET NULL`: without this guard the sub-tree
   * would be silently promoted to root.
   *
   * @return void no return value
   */
  #[Test]
  public function testDeletingAScratchpadWithChildrenIsAConflict(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'DELETE',
      '/api/facilities/' . self::SCRATCHPAD_PARENT_ID,
      server: $this->headers(['HTTP_IF_MATCH' => '"revision-1"']),
    );

    self::assertSame(409, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertStringContainsString(
      'Cannot delete a facility that still has child facilities; move or remove them first.',
      (string) $client->getResponse()->getContent(),
    );
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
      '/api/facilities/' . self::CHILD_ID,
      server: $this->headers(['HTTP_IF_MATCH' => '"revision-99"']),
    );

    self::assertSame(412, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testAnUnknownFacilityAnswersNotFoundEvenWithoutIfMatch.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnUnknownFacilityAnswersNotFoundEvenWithoutIfMatch(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request('DELETE', '/api/facilities/' . self::UNKNOWN_ID, server: $this->headers());

    self::assertSame(404, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testAMalformedIdentifierAnswersNotFound.
   *
   * @return void no return value
   */
  #[Test]
  public function testAMalformedIdentifierAnswersNotFound(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request('DELETE', '/api/facilities/not-a-uuid', server: $this->headers());

    self::assertSame(404, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testAForeignFacilityAnswersNotFoundRatherThanForbidden.
   *
   * @return void no return value
   */
  #[Test]
  public function testAForeignFacilityAnswersNotFoundRatherThanForbidden(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'DELETE',
      '/api/facilities/' . self::OUTSIDER_FACILITY_ID,
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
      '/api/facilities/' . self::CHILD_ID,
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
   * Rebuilds the fixture set on every test: two organizations, three callers,
   * and a small hierarchy covering each canonical state.
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

    $entityManager->persist($this->newRole('8a0e8400-e29b-41d4-a716-446655494040', $organization, 'canonical-facility-full', ['*'], $now));
    $entityManager->persist($this->newRole('8a0e8400-e29b-41d4-a716-446655494041', $organization, 'canonical-facility-read', ['organization.facilities.read'], $now));
    $entityManager->persist($this->newRole('8a0e8400-e29b-41d4-a716-446655494042', $outsiderOrganization, 'canonical-facility-outsider', ['*'], $now));
    $entityManager->flush();

    $this->assignMember($entityManager, '8a0e8400-e29b-41d4-a716-446655494050', $organization, self::ADMIN_USER_ID, '8a0e8400-e29b-41d4-a716-446655494040', $now);
    $this->assignMember($entityManager, '8a0e8400-e29b-41d4-a716-446655494051', $organization, self::READ_ONLY_USER_ID, '8a0e8400-e29b-41d4-a716-446655494041', $now);
    $this->assignMember($entityManager, '8a0e8400-e29b-41d4-a716-446655494052', $outsiderOrganization, self::OUTSIDER_USER_ID, '8a0e8400-e29b-41d4-a716-446655494042', $now);
    $entityManager->flush();

    $root = $this->newFacility(self::ROOT_ID, $organization, 'active', 'published', null, $now);
    $archived = $this->newFacility(self::ARCHIVED_ID, $organization, 'archived', 'published', null, $now);
    $scratchpadParent = $this->newFacility(self::SCRATCHPAD_PARENT_ID, $organization, 'active', 'draft', null, $now);
    $entityManager->persist($root);
    $entityManager->persist($archived);
    $entityManager->persist($scratchpadParent);
    $entityManager->persist($this->newFacility(self::OUTSIDER_FACILITY_ID, $outsiderOrganization, 'active', 'published', null, $now));
    $entityManager->flush();

    $entityManager->persist($this->newFacility(self::CHILD_ID, $organization, 'active', 'published', $root, $now, 'B-1', '1 Rue de la Paix'));
    $entityManager->persist($this->newFacility(self::ARCHIVED_CHILD_ID, $organization, 'archived', 'published', $archived, $now));
    $entityManager->persist($this->newFacility(self::SCRATCHPAD_ID, $organization, 'active', 'draft', null, $now));
    $entityManager->persist($this->newFacility(self::SCRATCHPAD_CHILD_ID, $organization, 'active', 'draft', $scratchpadParent, $now));
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
    $organization->name = 'Canonical Facility API Test Org ' . $id;
    $organization->slug = 'canonical-facility-api-test-' . $id;
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
   * Method newFacility.
   *
   * @param string $id the facility identifier
   * @param OrganizationRecord $organization the owning organization
   * @param string $status the facility status
   * @param string $recordStatus the record status
   * @param ?FacilityRecord $parent the parent facility
   * @param DateTimeImmutable $now the fixed clock
   * @param ?string $code the human-facing code
   * @param ?string $address the postal address
   *
   * @return FacilityRecord the facility record
   */
  private function newFacility(
    string $id,
    OrganizationRecord $organization,
    string $status,
    string $recordStatus,
    ?FacilityRecord $parent,
    DateTimeImmutable $now,
    ?string $code = null,
    ?string $address = null,
  ): FacilityRecord {
    $facility = new FacilityRecord();
    $facility->id = $id;
    $facility->organization = $organization;
    $facility->recordStatus = $recordStatus;
    $facility->revision = 1;
    $facility->parentFacility = $parent;
    $facility->type = null === $parent ? 'site' : 'building';
    $facility->name = 'Canonical Facility Test ' . $id;
    $facility->code = $code;
    $facility->address = $address;
    $facility->status = $status;
    $facility->metadata = [];
    $facility->createdAt = $now;
    $facility->updatedAt = $now;

    return $facility;
  }
  // #endregion
}
