<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Persistence\Doctrine\Record\{FacilityMetadataFieldRecord, FacilityRecord};
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

use function array_values;
use function json_decode;
use function json_encode;

/**
 * Test FacilityApiTest.
 *
 * Contract tests for both facility resource families: the legacy org-scoped
 * `/api/organizations/{organizationId}/facilities/...` surface
 * (`FacilityResource`) and the canonical, offline-sync-friendly
 * `/api/facilities` surface (`CanonicalFacilityResource`). Denial paths mirror
 * the split proven elsewhere in the module: a member of the OWNING
 * organization who lacks `organization.facilities.{read,write}` gets **403**;
 * a caller with no active membership in the owning organization gets **404**,
 * byte-identical to the response for a facility id that does not exist —
 * `OrganizationAuthorizationPort::resolveAccess()` is what carries the
 * distinction.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  private const string ORGANIZATION_ID = '760e8400-e29b-41d4-a716-446655480001';

  private const string ADMIN_USER_ID = '760e8400-e29b-41d4-a716-446655480002';

  private const string ADMIN_MEMBER_ID = '760e8400-e29b-41d4-a716-446655480022';

  private const string READ_ONLY_USER_ID = '760e8400-e29b-41d4-a716-446655480003';

  private const string NO_ACCESS_USER_ID = '760e8400-e29b-41d4-a716-446655480004';

  private const string OUTSIDER_ORGANIZATION_ID = '760e8400-e29b-41d4-a716-446655480005';

  private const string OUTSIDER_USER_ID = '760e8400-e29b-41d4-a716-446655480006';

  private const string ROOT_FACILITY_ID = '760e8400-e29b-41d4-a716-446655480010';

  private const string CHILD_FACILITY_ID = '760e8400-e29b-41d4-a716-446655480011';

  private const string OTHER_ROOT_FACILITY_ID = '760e8400-e29b-41d4-a716-446655480012';

  private const string ARCHIVED_FACILITY_ID = '760e8400-e29b-41d4-a716-446655480013';

  private const string OUTSIDER_FACILITY_ID = '760e8400-e29b-41d4-a716-446655480014';

  private const string MOVE_PARENT_ID = '760e8400-e29b-41d4-a716-446655480020';

  private const string MOVE_CHILD_ID = '760e8400-e29b-41d4-a716-446655480021';

  private const string ARCHIVE_PARENT_ID = '760e8400-e29b-41d4-a716-446655480030';

  private const string ARCHIVE_CHILD_ID = '760e8400-e29b-41d4-a716-446655480031';

  private const string ARCHIVE_INTERVENTION_FACILITY_ID = '760e8400-e29b-41d4-a716-446655480032';

  private const string ARCHIVE_INTERVENTION_ID = '760e8400-e29b-41d4-a716-446655480033';

  // #region Authentication

  #[Test]
  public function testCreateFacilityRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/organizations/' . self::DUMMY_UUID . '/facilities');

    self::assertContains(
      needle: $client->getResponse()->getStatusCode(),
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /organizations/{organizationId}/facilities.',
    );
  }

  #[Test]
  public function testListFacilitiesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/facilities');

    self::assertContains(
      needle: $client->getResponse()->getStatusCode(),
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /organizations/{organizationId}/facilities.',
    );
  }

  #[Test]
  public function testGetFacilityRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/facilities/' . self::DUMMY_UUID);

    self::assertContains(
      needle: $client->getResponse()->getStatusCode(),
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /organizations/{organizationId}/facilities/{facilityId}.',
    );
  }

  #[Test]
  public function testArchiveFacilityRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/organizations/' . self::DUMMY_UUID . '/facilities/' . self::DUMMY_UUID . '/archive');

    self::assertContains(
      needle: $client->getResponse()->getStatusCode(),
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /organizations/{organizationId}/facilities/{facilityId}/archive.',
    );
  }

  #[Test]
  public function testMoveFacilityRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/organizations/' . self::DUMMY_UUID . '/facilities/' . self::DUMMY_UUID . '/move');

    self::assertContains(
      needle: $client->getResponse()->getStatusCode(),
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /organizations/{organizationId}/facilities/{facilityId}/move.',
    );
  }

  #[Test]
  public function testListFacilitiesWithHasCoordinatesFilterRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/facilities?hasCoordinates=true');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/{organizationId}/facilities?hasCoordinates=true endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /organizations/{organizationId}/facilities?hasCoordinates=true, got ' . $statusCode,
    );
  }

  // #endregion

  // #region Metadata schema (organization-defined typed fields)

  /**
   * Covers the FacilityMetadataSchemaGuard integration on the create
   * facility path: with an organization-defined typed schema in place, a
   * metadata value that parses as the definition's type is accepted.
   */
  #[Test]
  public function testCreateFacilityAcceptsMetadataMatchingTheOrganizationSchema(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedMetadataField('760e8400-e29b-41d4-a716-446655480050', 'surface-m2');

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-admin@example.com');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/facilities',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'type' => 'building',
        'name' => 'Warehouse With Schema',
        'metadata' => ['surface-m2' => 450],
      ]),
    );

    $response = $client->getResponse();
    self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
  }

  /**
   * With the same organization schema, a value that does not parse as the
   * definition's type (a string where "number" is expected) is rejected —
   * mapped centrally to 422 by FacilityMetadataValidationExceptionSubscriber.
   */
  #[Test]
  public function testCreateFacilityRejectsMetadataFailingTheOrganizationSchema(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedMetadataField('760e8400-e29b-41d4-a716-446655480051', 'surface-m2');

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-admin@example.com');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/facilities',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'type' => 'building',
        'name' => 'Warehouse With Bad Metadata',
        'metadata' => ['surface-m2' => 'not-a-number'],
      ]),
    );

    $response = $client->getResponse();
    self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
    $decoded = json_decode((string) $response->getContent(), true);
    self::assertIsArray($decoded);
    $detail = $decoded['detail'] ?? '';
    self::assertIsString($detail);
    self::assertStringContainsString('surface-m2', $detail);
  }

  /**
   * A metadata key with no matching definition is untouched free-form
   * usage: the back-compat rule the schema feature is built on.
   */
  #[Test]
  public function testCreateFacilityAllowsUnschemadMetadataKeys(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedMetadataField('760e8400-e29b-41d4-a716-446655480052', 'surface-m2');

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-admin@example.com');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/facilities',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'type' => 'building',
        'name' => 'Warehouse With Free-Form Key',
        'metadata' => ['some-legacy-key' => 'whatever'],
      ]),
    );

    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  #[Test]
  public function testPatchingLevelIndexSurvivesTheNextDetailRead(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-admin@example.com');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/facilities',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode(['type' => 'floor', 'name' => 'Mezzanine']),
    );
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $created = json_decode((string) $client->getResponse()->getContent(), true);
    self::assertIsArray($created);
    self::assertIsString($created['id']);
    $facilityId = $created['id'];

    // Set it on an existing record: the create path writes a fresh row through
    // the mapper, the update path copies field by field onto the managed one.
    // Only a read AFTER a write can tell the two apart.
    static::ensureKernelShutdown();
    $patchClient = static::createClient();
    $this->loginAs($patchClient, self::ADMIN_USER_ID, 'facility-admin@example.com');
    $patchClient->request(
      method: 'PATCH',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . $facilityId,
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode(['levelIndex' => 4]),
    );
    self::assertSame(200, $patchClient->getResponse()->getStatusCode(), (string) $patchClient->getResponse()->getContent());
    $patched = json_decode((string) $patchClient->getResponse()->getContent(), true);
    self::assertIsArray($patched);
    self::assertSame(4, $patched['levelIndex']);

    static::ensureKernelShutdown();
    $readClient = static::createClient();
    $this->loginAs($readClient, self::ADMIN_USER_ID, 'facility-admin@example.com');
    $readClient->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . $facilityId);
    self::assertSame(200, $readClient->getResponse()->getStatusCode());
    $reread = json_decode((string) $readClient->getResponse()->getContent(), true);
    self::assertIsArray($reread);
    self::assertSame(4, $reread['levelIndex'], 'A patched level index must survive persistence, not just echo back in the response.');

    // And clearing it must reach the row too, not merely echo an omission.
    static::ensureKernelShutdown();
    $clearClient = static::createClient();
    $this->loginAs($clearClient, self::ADMIN_USER_ID, 'facility-admin@example.com');
    $clearClient->request(
      method: 'PATCH',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . $facilityId,
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode(['levelIndex' => null]),
    );
    self::assertSame(200, $clearClient->getResponse()->getStatusCode(), (string) $clearClient->getResponse()->getContent());

    static::ensureKernelShutdown();
    $finalClient = static::createClient();
    $this->loginAs($finalClient, self::ADMIN_USER_ID, 'facility-admin@example.com');
    $finalClient->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . $facilityId);
    $cleared = json_decode((string) $finalClient->getResponse()->getContent(), true);
    self::assertIsArray($cleared);
    self::assertArrayNotHasKey('levelIndex', $cleared, 'A cleared level index is null, and API Platform omits a null DTO property.');
  }

  #[Test]
  public function testCreateFacilityPersistsLevelIndexAndReturnsItOnTheDetailAndInTheCollection(): void
  {
    $client = static::createClient();
    $this->seedOrganization();

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-admin@example.com');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/facilities',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'type' => 'floor',
        'name' => 'First Basement',
        'levelIndex' => -1,
      ]),
    );

    $response = $client->getResponse();
    self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
    $created = json_decode((string) $response->getContent(), true);
    self::assertIsArray($created);
    self::assertSame(-1, $created['levelIndex']);
    self::assertIsString($created['id']);
    $facilityId = $created['id'];

    static::ensureKernelShutdown();
    $detailClient = static::createClient();
    $this->loginAs($detailClient, self::ADMIN_USER_ID, 'facility-admin@example.com');
    $detailClient->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . $facilityId);

    self::assertSame(200, $detailClient->getResponse()->getStatusCode());
    $detail = json_decode((string) $detailClient->getResponse()->getContent(), true);
    self::assertIsArray($detail);
    self::assertSame(-1, $detail['levelIndex'], 'The field must not be dropped on the detail read.');

    static::ensureKernelShutdown();
    $listClient = static::createClient();
    $this->loginAs($listClient, self::ADMIN_USER_ID, 'facility-admin@example.com');
    $listClient->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities?type=floor');

    $members = $this->decodeCollection($listClient);
    $match = null;
    foreach ($members as $item) {
      self::assertIsArray($item);
      if ($facilityId === ($item['id'] ?? null)) {
        $match = $item;
      }
    }

    self::assertNotNull($match, 'The created facility must appear in the collection.');
    self::assertSame(-1, $match['levelIndex'], 'The field must not be absent in the collection read.');
  }

  #[Test]
  public function testCreateFacilityRejectsLevelIndexOutOfRange(): void
  {
    $client = static::createClient();
    $this->seedOrganization();

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-admin@example.com');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/facilities',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'type' => 'floor',
        'name' => 'Too Deep',
        'levelIndex' => -101,
      ]),
    );

    self::assertSame(422, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  // #endregion

  // #region 403 — authenticated but not entitled

  #[Test]
  public function testListFacilitiesRejectsMemberWithoutReadPermission(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilityTree();

    $this->loginAs($client, self::NO_ACCESS_USER_ID, 'facility-no-access@example.com');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities');

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A member without organization.facilities.read must get 403.',
    );
  }

  #[Test]
  public function testCreateFacilityRejectsMemberWithoutWritePermission(): void
  {
    $client = static::createClient();
    $this->seedOrganization();

    $this->loginAs($client, self::READ_ONLY_USER_ID, 'facility-read-only@example.com');
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/facilities',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode(['type' => 'site', 'name' => 'Should Not Be Created']),
    );

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A member without organization.facilities.write must get 403.',
    );
  }

  #[Test]
  public function testArchiveFacilityRejectsMemberWithoutWritePermission(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilityTree();

    $this->loginAs($client, self::READ_ONLY_USER_ID, 'facility-read-only@example.com');
    $client->request('POST', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::OTHER_ROOT_FACILITY_ID . '/archive');

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A member without organization.facilities.write must get 403 archiving a facility.',
    );
  }

  #[Test]
  public function testMoveFacilityRejectsMemberWithoutWritePermission(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilityTree();

    $this->loginAs($client, self::READ_ONLY_USER_ID, 'facility-read-only@example.com');
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::CHILD_FACILITY_ID . '/move',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode(['parentFacilityId' => null]),
    );

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A member without organization.facilities.write must get 403 moving a facility.',
    );
  }

  // #endregion

  // #region 404 — cross-tenant isolation

  #[Test]
  public function testGetFacilityReturns404ForACallerFromAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilityTree();

    $this->loginAs($client, self::OUTSIDER_USER_ID, 'facility-outsider@example.com');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::ROOT_FACILITY_ID);

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A caller outside the owning organization must get 404, not 403.',
    );
  }

  #[Test]
  public function testListFacilitiesReturns404ForACallerFromAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilityTree();

    $this->loginAs($client, self::OUTSIDER_USER_ID, 'facility-outsider@example.com');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities');

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A caller outside the owning organization must get 404 listing another organization\'s facilities.',
    );
  }

  #[Test]
  public function testArchiveFacilityReturns404ForACallerFromAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilityTree();

    $this->loginAs($client, self::OUTSIDER_USER_ID, 'facility-outsider@example.com');
    $client->request('POST', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::OTHER_ROOT_FACILITY_ID . '/archive');

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A caller outside the owning organization must get 404 archiving another organization\'s facility.',
    );
  }

  // #endregion

  // #region Legacy list filters

  #[Test]
  public function testListFacilitiesFiltersByRootsOnly(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilityTree();

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-admin@example.com');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities?rootsOnly=true');

    $decoded = $this->decodeCollection($client);
    $ids = $this->memberIds($decoded);

    self::assertContains(self::ROOT_FACILITY_ID, $ids);
    self::assertContains(self::OTHER_ROOT_FACILITY_ID, $ids);
    self::assertNotContains(self::CHILD_FACILITY_ID, $ids, 'rootsOnly must exclude a facility with a parent.');
  }

  #[Test]
  public function testListFacilitiesFiltersByType(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilityTree();

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-admin@example.com');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities?type=building');

    $decoded = $this->decodeCollection($client);
    $ids = $this->memberIds($decoded);

    self::assertContains(self::CHILD_FACILITY_ID, $ids);
    self::assertNotContains(self::ROOT_FACILITY_ID, $ids, 'type=building must exclude a site-type facility.');

    foreach ($decoded as $item) {
      self::assertIsArray($item);
      self::assertSame('building', $item['type'] ?? null, 'Every returned facility must match the type filter.');
    }
  }

  #[Test]
  public function testListFacilitiesFiltersByStatus(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilityTree();

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-admin@example.com');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities?status=archived');

    $decoded = $this->decodeCollection($client);
    $ids = $this->memberIds($decoded);

    self::assertContains(self::ARCHIVED_FACILITY_ID, $ids);
    self::assertNotContains(self::ROOT_FACILITY_ID, $ids, 'status=archived must exclude an active facility.');
  }

  #[Test]
  public function testListFacilitiesFiltersByIncludeArchived(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilityTree();

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-admin@example.com');

    // Default (includeArchived=false): the archived facility is excluded.
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities');
    $defaultIds = $this->memberIds($this->decodeCollection($client));
    self::assertNotContains(self::ARCHIVED_FACILITY_ID, $defaultIds, 'Archived facilities must be excluded by default.');

    static::ensureKernelShutdown();
    $includeClient = static::createClient();
    $this->loginAs($includeClient, self::ADMIN_USER_ID, 'facility-admin@example.com');
    $includeClient->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities?includeArchived=true');
    $includeIds = $this->memberIds($this->decodeCollection($includeClient));
    self::assertContains(self::ARCHIVED_FACILITY_ID, $includeIds, 'includeArchived=true must surface the archived facility.');
  }

  #[Test]
  public function testListFacilitiesFiltersByParentFacilityId(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedFacilityTree();

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-admin@example.com');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities?parentFacilityId=' . self::ROOT_FACILITY_ID);

    $decoded = $this->decodeCollection($client);
    $ids = $this->memberIds($decoded);

    self::assertSame([self::CHILD_FACILITY_ID], $ids, 'parentFacilityId must narrow the list to direct children only.');
  }

  // #endregion

  // #region Move endpoint

  #[Test]
  public function testMoveFacilitySucceeds(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedMoveFixtures();

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-admin@example.com');
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::MOVE_CHILD_ID . '/move',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode(['parentFacilityId' => null]),
    );

    $response = $client->getResponse();
    // 200: `move` is a state transition, not a creation. The operation now
    // spells out `status: HttpResponse::HTTP_OK`, matching what its own
    // `openapi` block always documented.
    self::assertSame(200, $response->getStatusCode(), 'Detaching a facility from its parent should succeed. Response: ' . $response->getContent());
    $decoded = json_decode((string) $response->getContent(), true);
    self::assertIsArray($decoded);
    // A null field is omitted from the JSON-LD payload rather than serialized
    // as null, so the absence of the key IS the "no parent" signal.
    self::assertArrayNotHasKey('parentFacilityId', $decoded, 'The facility must now be parentless.');
  }

  #[Test]
  public function testMoveFacilityRejectsHierarchyCycle(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedMoveFixtures();

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-admin@example.com');
    // MOVE_CHILD_ID is currently a child of MOVE_PARENT_ID; moving the parent
    // under its own child would create a cycle.
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::MOVE_PARENT_ID . '/move',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode(['parentFacilityId' => self::MOVE_CHILD_ID]),
    );

    self::assertSame(
      expected: 400,
      actual: $client->getResponse()->getStatusCode(),
      message: 'Moving a facility under its own descendant must be rejected with 400. Response: ' . $client->getResponse()->getContent(),
    );
  }

  #[Test]
  public function testMoveFacilityRejectsParentInAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedMoveFixtures();

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-admin@example.com');
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::MOVE_CHILD_ID . '/move',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: (string) json_encode(['parentFacilityId' => self::OUTSIDER_FACILITY_ID]),
    );

    self::assertSame(
      expected: 400,
      actual: $client->getResponse()->getStatusCode(),
      message: 'Moving a facility under a parent from another organization must be rejected with 400. Response: ' . $client->getResponse()->getContent(),
    );
  }

  // #endregion

  // #region Archive guard

  #[Test]
  public function testArchiveFacilityRejectsWhenActiveChildExists(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedArchiveGuardFixtures();

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-admin@example.com');
    $client->request('POST', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::ARCHIVE_PARENT_ID . '/archive');

    self::assertSame(
      expected: 409,
      actual: $client->getResponse()->getStatusCode(),
      message: 'Archiving a facility with an active child must be refused with 409. Response: ' . $client->getResponse()->getContent(),
    );
  }

  #[Test]
  public function testArchiveFacilityRejectsWhenActiveInterventionExists(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedInterventionArchiveGuardFixtures('planned');

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-admin@example.com');
    $client->request('POST', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::ARCHIVE_INTERVENTION_FACILITY_ID . '/archive');

    self::assertSame(
      expected: 409,
      actual: $client->getResponse()->getStatusCode(),
      message: 'Archiving a facility with an active intervention must be refused with 409. Response: ' . $client->getResponse()->getContent(),
    );
  }

  #[Test]
  public function testCanonicalFacilityDeleteRejectsWhenActiveInterventionExists(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedInterventionArchiveGuardFixtures('in_progress');

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-admin@example.com');
    $client->request('DELETE', '/api/facilities/' . self::ARCHIVE_INTERVENTION_FACILITY_ID, server: ['HTTP_IF_MATCH' => '"revision-1"']);

    self::assertSame(
      expected: 409,
      actual: $client->getResponse()->getStatusCode(),
      message: 'Canonical DELETE on a facility with an active intervention must be refused with 409. Response: ' . $client->getResponse()->getContent(),
    );
  }

  #[Test]
  public function testArchiveFacilitySucceedsOncePublishedInterventionNoLongerBlocks(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedInterventionArchiveGuardFixtures('published');

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-admin@example.com');
    $client->request('POST', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::ARCHIVE_INTERVENTION_FACILITY_ID . '/archive');

    self::assertSame(
      expected: Response::HTTP_OK,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A published (closed) intervention must not block archiving. Response: ' . $client->getResponse()->getContent(),
    );
  }

  #[Test]
  public function testArchiveFacilitySucceedsOnceAbandonedInterventionNoLongerBlocks(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedInterventionArchiveGuardFixtures('abandoned');

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-admin@example.com');
    $client->request('POST', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::ARCHIVE_INTERVENTION_FACILITY_ID . '/archive');

    self::assertSame(
      expected: Response::HTTP_OK,
      actual: $client->getResponse()->getStatusCode(),
      message: 'An abandoned (closed) intervention must not block archiving. Response: ' . $client->getResponse()->getContent(),
    );
  }

  // #endregion

  // #region Canonical resource

  #[Test]
  public function testCanonicalFacilityPutUpsertByClientIdReturns201(): void
  {
    $client = static::createClient();
    $this->seedOrganization();

    $clientId = '760e8400-e29b-41d4-a716-446655480099';

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-admin@example.com');
    $client->request(
      method: 'PUT',
      uri: '/api/facilities/' . $clientId,
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_IF_NONE_MATCH' => '*'],
      content: (string) json_encode([
        'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
        'type' => 'site',
        'name' => 'Offline Created Facility',
      ]),
    );

    $response = $client->getResponse();
    self::assertSame(201, $response->getStatusCode(), 'A client-UUID PUT create must return 201. Response: ' . $response->getContent());
    $decoded = json_decode((string) $response->getContent(), true);
    self::assertIsArray($decoded);
    self::assertSame($clientId, $decoded['id'] ?? null, 'The created facility must be addressable at the client-supplied id.');

    // A repeat PUT at the same client id is a create-only precondition
    // (If-None-Match: *), not a silent overwrite: it must be rejected rather
    // than mutate or duplicate the record, and the original stays intact.
    static::ensureKernelShutdown();
    $repeatClient = static::createClient();
    $this->loginAs($repeatClient, self::ADMIN_USER_ID, 'facility-admin@example.com');
    $repeatClient->request(
      method: 'PUT',
      uri: '/api/facilities/' . $clientId,
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_IF_NONE_MATCH' => '*'],
      content: (string) json_encode([
        'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
        'type' => 'building',
        'name' => 'Should Not Overwrite',
      ]),
    );
    self::assertSame(
      expected: 412,
      actual: $repeatClient->getResponse()->getStatusCode(),
      message: 'A repeat create-only PUT at the same client id must be rejected, not silently reapplied.',
    );

    static::ensureKernelShutdown();
    $getClient = static::createClient();
    $this->loginAs($getClient, self::ADMIN_USER_ID, 'facility-admin@example.com');
    $getClient->request('GET', '/api/facilities/' . $clientId);
    $getDecoded = json_decode((string) $getClient->getResponse()->getContent(), true);
    self::assertIsArray($getDecoded);
    self::assertSame('Offline Created Facility', $getDecoded['name'] ?? null, 'The rejected repeat PUT must not have mutated the original record.');
  }

  #[Test]
  public function testCanonicalFacilityDeleteReturns204AndIsIdempotent(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $facilityId = $this->seedCanonicalPublishedFacility();

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-admin@example.com');
    $client->request('DELETE', '/api/facilities/' . $facilityId, server: ['HTTP_IF_MATCH' => '"revision-1"']);
    self::assertSame(204, $client->getResponse()->getStatusCode(), 'DELETE on a published facility must return 204.');

    static::ensureKernelShutdown();
    $getClient = static::createClient();
    $this->loginAs($getClient, self::ADMIN_USER_ID, 'facility-admin@example.com');
    $getClient->request('GET', '/api/facilities/' . $facilityId);
    $decoded = json_decode((string) $getClient->getResponse()->getContent(), true);
    self::assertIsArray($decoded);
    self::assertSame('archived', $decoded['status'] ?? null, 'DELETE on a published facility must be equivalent to archiving it.');
    self::assertIsInt($decoded['revision'] ?? null);
    $revisionAfterArchive = $decoded['revision'];

    // A repeat DELETE on an already-archived facility is an idempotent no-op.
    static::ensureKernelShutdown();
    $secondDeleteClient = static::createClient();
    $this->loginAs($secondDeleteClient, self::ADMIN_USER_ID, 'facility-admin@example.com');
    $secondDeleteClient->request('DELETE', '/api/facilities/' . $facilityId, server: ['HTTP_IF_MATCH' => '"revision-' . $revisionAfterArchive . '"']);
    self::assertSame(204, $secondDeleteClient->getResponse()->getStatusCode(), 'A repeat DELETE on an archived facility must remain a no-op 204.');
  }

  #[Test]
  public function testCanonicalFacilityRecordStatusFilterNarrowsToDraft(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $publishedId = $this->seedCanonicalPublishedFacility();
    $draftId = $this->seedCanonicalDraftFacility();

    $this->loginAs($client, self::ADMIN_USER_ID, 'facility-admin@example.com');
    $client->request('GET', '/api/facilities?organization=/api/organizations/' . self::ORGANIZATION_ID . '&recordStatus=draft');

    $decoded = $this->decodeCollection($client);
    $ids = $this->memberIds($decoded);

    self::assertContains($draftId, $ids);
    self::assertNotContains($publishedId, $ids, 'recordStatus=draft must exclude published records.');
  }

  // #endregion

  // #region Fixtures

  /**
   * Method loginAs.
   *
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

  /**
   * Method decodeCollection.
   *
   * @return list<mixed>
   */
  private function decodeCollection(KernelBrowser $client): array
  {
    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), 'Expected a successful collection response. Response: ' . $response->getContent());
    $decoded = json_decode((string) $response->getContent(), true);
    self::assertIsArray($decoded);
    self::assertIsArray($decoded['member']);

    return array_values($decoded['member']);
  }

  /**
   * Method memberIds.
   *
   * @param list<mixed> $members
   *
   * @return list<string>
   */
  private function memberIds(array $members): array
  {
    $ids = [];
    foreach ($members as $item) {
      self::assertIsArray($item);
      self::assertIsString($item['id'] ?? null);
      $ids[] = $item['id'];
    }

    return $ids;
  }

  /**
   * Method seedOrganization.
   *
   * Seeds (idempotently) an organization with an admin member (permissions
   * `['*']`), a read-only member (`organization.facilities.read` only, no
   * write), a no-access member (`organization.read` only, no facility
   * access), plus a second, unrelated organization with its own member — the
   * "outside scope" caller.
   */
  private function seedOrganization(): void
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

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Facility API Test Org';
    $organization->slug = 'facility-api-test-org-' . self::ORGANIZATION_ID;
    $organization->ownerUserId = self::ADMIN_USER_ID;
    $organization->createdByUserId = self::ADMIN_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $outsiderOrganization = new OrganizationRecord();
    $outsiderOrganization->id = self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->name = 'Facility API Test Outsider Org';
    $outsiderOrganization->slug = 'facility-api-test-outsider-org-' . self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->ownerUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->createdByUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->status = 'active';
    $outsiderOrganization->isActive = true;
    $outsiderOrganization->createdAt = $now;
    $outsiderOrganization->updatedAt = $now;
    $entityManager->persist($outsiderOrganization);

    $adminRole = new OrganizationRoleRecord();
    $adminRole->id = '760e8400-e29b-41d4-a716-446655480040';
    $adminRole->organization = $organization;
    $adminRole->name = 'facility-full-access';
    $adminRole->permissions = ['*'];
    $adminRole->description = 'Functional-test-only role granting every permission.';
    $adminRole->isSystem = false;
    $adminRole->createdAt = $now;
    $entityManager->persist($adminRole);

    $readOnlyRole = new OrganizationRoleRecord();
    $readOnlyRole->id = '760e8400-e29b-41d4-a716-446655480041';
    $readOnlyRole->organization = $organization;
    $readOnlyRole->name = 'facility-read-only';
    $readOnlyRole->permissions = ['organization.facilities.read'];
    $readOnlyRole->description = 'Functional-test-only role without facility write access.';
    $readOnlyRole->isSystem = false;
    $readOnlyRole->createdAt = $now;
    $entityManager->persist($readOnlyRole);

    $noAccessRole = new OrganizationRoleRecord();
    $noAccessRole->id = '760e8400-e29b-41d4-a716-446655480042';
    $noAccessRole->organization = $organization;
    $noAccessRole->name = 'facility-no-access';
    $noAccessRole->permissions = ['organization.read'];
    $noAccessRole->description = 'Functional-test-only role without any facility access.';
    $noAccessRole->isSystem = false;
    $noAccessRole->createdAt = $now;
    $entityManager->persist($noAccessRole);

    $outsiderRole = new OrganizationRoleRecord();
    $outsiderRole->id = '760e8400-e29b-41d4-a716-446655480043';
    $outsiderRole->organization = $outsiderOrganization;
    $outsiderRole->name = 'facility-outsider-full-access';
    $outsiderRole->permissions = ['*'];
    $outsiderRole->description = 'Functional-test-only role for the unrelated organization.';
    $outsiderRole->isSystem = false;
    $outsiderRole->createdAt = $now;
    $entityManager->persist($outsiderRole);

    $adminMember = new OrganizationMemberRecord();
    $adminMember->id = self::ADMIN_MEMBER_ID;
    $adminMember->organization = $organization;
    $adminMember->userId = self::ADMIN_USER_ID;
    $adminMember->isActive = true;
    $adminMember->joinedAt = $now;
    $entityManager->persist($adminMember);

    $adminAssignment = new OrganizationMemberRoleRecord();
    $adminAssignment->member = $adminMember;
    $adminAssignment->role = $adminRole;
    $adminAssignment->assignedAt = $now;
    $entityManager->persist($adminAssignment);

    $readOnlyMember = new OrganizationMemberRecord();
    $readOnlyMember->id = '760e8400-e29b-41d4-a716-446655480044';
    $readOnlyMember->organization = $organization;
    $readOnlyMember->userId = self::READ_ONLY_USER_ID;
    $readOnlyMember->isActive = true;
    $readOnlyMember->joinedAt = $now;
    $entityManager->persist($readOnlyMember);

    $readOnlyAssignment = new OrganizationMemberRoleRecord();
    $readOnlyAssignment->member = $readOnlyMember;
    $readOnlyAssignment->role = $readOnlyRole;
    $readOnlyAssignment->assignedAt = $now;
    $entityManager->persist($readOnlyAssignment);

    $noAccessMember = new OrganizationMemberRecord();
    $noAccessMember->id = '760e8400-e29b-41d4-a716-446655480045';
    $noAccessMember->organization = $organization;
    $noAccessMember->userId = self::NO_ACCESS_USER_ID;
    $noAccessMember->isActive = true;
    $noAccessMember->joinedAt = $now;
    $entityManager->persist($noAccessMember);

    $noAccessAssignment = new OrganizationMemberRoleRecord();
    $noAccessAssignment->member = $noAccessMember;
    $noAccessAssignment->role = $noAccessRole;
    $noAccessAssignment->assignedAt = $now;
    $entityManager->persist($noAccessAssignment);

    $outsiderMember = new OrganizationMemberRecord();
    $outsiderMember->id = '760e8400-e29b-41d4-a716-446655480046';
    $outsiderMember->organization = $outsiderOrganization;
    $outsiderMember->userId = self::OUTSIDER_USER_ID;
    $outsiderMember->isActive = true;
    $outsiderMember->joinedAt = $now;
    $entityManager->persist($outsiderMember);

    $outsiderAssignment = new OrganizationMemberRoleRecord();
    $outsiderAssignment->member = $outsiderMember;
    $outsiderAssignment->role = $outsiderRole;
    $outsiderAssignment->assignedAt = $now;
    $entityManager->persist($outsiderAssignment);

    $entityManager->flush();
  }

  /**
   * Method seedFacilityTree.
   *
   * Seeds (idempotently) a root/child pair, a second unrelated root, and an
   * archived facility for {@see self::ORGANIZATION_ID}, plus a lone facility
   * for {@see self::OUTSIDER_ORGANIZATION_ID}.
   */
  private function seedFacilityTree(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    foreach ([self::ROOT_FACILITY_ID, self::CHILD_FACILITY_ID, self::OTHER_ROOT_FACILITY_ID, self::ARCHIVED_FACILITY_ID, self::OUTSIDER_FACILITY_ID] as $facilityId) {
      $existing = $entityManager->find(FacilityRecord::class, $facilityId);
      if ($existing instanceof FacilityRecord) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }
    }

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    /** @var OrganizationRecord $organization */
    $organization = $entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);
    /** @var OrganizationRecord $outsiderOrganization */
    $outsiderOrganization = $entityManager->getReference(OrganizationRecord::class, self::OUTSIDER_ORGANIZATION_ID);

    $root = $this->newFacilityRecord(self::ROOT_FACILITY_ID, $organization, 'site', 'Root Site', 'active', null, $now);
    $entityManager->persist($root);

    $child = $this->newFacilityRecord(self::CHILD_FACILITY_ID, $organization, 'building', 'Child Building', 'active', $root, $now);
    $entityManager->persist($child);

    $otherRoot = $this->newFacilityRecord(self::OTHER_ROOT_FACILITY_ID, $organization, 'site', 'Other Root Site', 'active', null, $now);
    $entityManager->persist($otherRoot);

    $archived = $this->newFacilityRecord(self::ARCHIVED_FACILITY_ID, $organization, 'site', 'Archived Site', 'archived', null, $now);
    $entityManager->persist($archived);

    $outsiderFacility = $this->newFacilityRecord(self::OUTSIDER_FACILITY_ID, $outsiderOrganization, 'site', 'Outsider Site', 'active', null, $now);
    $entityManager->persist($outsiderFacility);

    $entityManager->flush();
  }

  /**
   * Method seedMoveFixtures.
   *
   * Seeds (idempotently) a parent/child pair for {@see self::ORGANIZATION_ID}
   * and a lone facility for {@see self::OUTSIDER_ORGANIZATION_ID}.
   */
  private function seedMoveFixtures(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    foreach ([self::MOVE_PARENT_ID, self::MOVE_CHILD_ID, self::OUTSIDER_FACILITY_ID] as $facilityId) {
      $existing = $entityManager->find(FacilityRecord::class, $facilityId);
      if ($existing instanceof FacilityRecord) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }
    }

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    /** @var OrganizationRecord $organization */
    $organization = $entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);
    /** @var OrganizationRecord $outsiderOrganization */
    $outsiderOrganization = $entityManager->getReference(OrganizationRecord::class, self::OUTSIDER_ORGANIZATION_ID);

    $parent = $this->newFacilityRecord(self::MOVE_PARENT_ID, $organization, 'site', 'Move Parent Site', 'active', null, $now);
    $entityManager->persist($parent);

    $child = $this->newFacilityRecord(self::MOVE_CHILD_ID, $organization, 'building', 'Move Child Building', 'active', $parent, $now);
    $entityManager->persist($child);

    $outsiderFacility = $this->newFacilityRecord(self::OUTSIDER_FACILITY_ID, $outsiderOrganization, 'site', 'Outsider Site', 'active', null, $now);
    $entityManager->persist($outsiderFacility);

    $entityManager->flush();
  }

  /**
   * Method seedArchiveGuardFixtures.
   *
   * Seeds (idempotently) a parent facility with one active child, exercising
   * `FacilityArchivalGuard::assertNoActiveDependents()`.
   */
  private function seedArchiveGuardFixtures(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    foreach ([self::ARCHIVE_PARENT_ID, self::ARCHIVE_CHILD_ID] as $facilityId) {
      $existing = $entityManager->find(FacilityRecord::class, $facilityId);
      if ($existing instanceof FacilityRecord) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }
    }

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    /** @var OrganizationRecord $organization */
    $organization = $entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $parent = $this->newFacilityRecord(self::ARCHIVE_PARENT_ID, $organization, 'site', 'Archive Guard Parent', 'active', null, $now);
    $entityManager->persist($parent);

    $child = $this->newFacilityRecord(self::ARCHIVE_CHILD_ID, $organization, 'building', 'Archive Guard Active Child', 'active', $parent, $now);
    $entityManager->persist($child);

    $entityManager->flush();
  }

  /**
   * Method seedInterventionArchiveGuardFixtures.
   *
   * Seeds (idempotently) a facility targeted, as its site, by one intervention
   * in the given workflow status — exercising
   * `FacilityArchivalGuard::assertNoActiveDependents()`'s intervention check.
   *
   * @param string $interventionStatus the intervention's workflow status
   */
  private function seedInterventionArchiveGuardFixtures(string $interventionStatus): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $existingIntervention = $entityManager->find(InterventionRecord::class, self::ARCHIVE_INTERVENTION_ID);
    if ($existingIntervention instanceof InterventionRecord) {
      $entityManager->remove($existingIntervention);
      $entityManager->flush();
    }

    $existingFacility = $entityManager->find(FacilityRecord::class, self::ARCHIVE_INTERVENTION_FACILITY_ID);
    if ($existingFacility instanceof FacilityRecord) {
      $entityManager->remove($existingFacility);
      $entityManager->flush();
    }

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    /** @var OrganizationRecord $organization */
    $organization = $entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $facility = $this->newFacilityRecord(self::ARCHIVE_INTERVENTION_FACILITY_ID, $organization, 'site', 'Archive Guard Intervention Site', 'active', null, $now);
    $entityManager->persist($facility);

    $intervention = new InterventionRecord();
    $intervention->id = self::ARCHIVE_INTERVENTION_ID;
    $intervention->organization = $organization;
    $intervention->name = 'Archive Guard Intervention';
    $intervention->number = 1;
    $intervention->status = $interventionStatus;
    $intervention->siteId = self::ARCHIVE_INTERVENTION_FACILITY_ID;
    $intervention->responsibleId = self::ADMIN_USER_ID;
    $intervention->createdAt = $now;
    $intervention->updatedAt = $now;
    $entityManager->persist($intervention);

    $entityManager->flush();
  }

  /**
   * Method seedCanonicalPublishedFacility.
   *
   * Seeds one published canonical facility for {@see self::ORGANIZATION_ID}
   * and returns its identifier.
   */
  private function seedCanonicalPublishedFacility(): string
  {
    return $this->seedCanonicalFacility('published', null);
  }

  /**
   * Method seedCanonicalDraftFacility.
   *
   * Seeds one draft canonical facility (an intervention scratchpad) for
   * {@see self::ORGANIZATION_ID} and returns its identifier. The intervention
   * id is not backed by a real intervention row — `intervention_id` carries
   * no foreign key, matching the mapping in {@see FacilityRecord}.
   */
  private function seedCanonicalDraftFacility(): string
  {
    return $this->seedCanonicalFacility('draft', '760e8400-e29b-41d4-a716-446655480098');
  }

  /**
   * Method seedCanonicalFacility.
   */
  private function seedCanonicalFacility(string $recordStatus, ?string $interventionId): string
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $id = 'draft' === $recordStatus ? '760e8400-e29b-41d4-a716-446655480096' : '760e8400-e29b-41d4-a716-446655480097';

    $existing = $entityManager->find(FacilityRecord::class, $id);
    if ($existing instanceof FacilityRecord) {
      $entityManager->remove($existing);
      $entityManager->flush();
    }

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    /** @var OrganizationRecord $organization */
    $organization = $entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $record = $this->newFacilityRecord($id, $organization, 'site', 'Canonical ' . $recordStatus . ' Facility', 'active', null, $now);
    $record->recordStatus = $recordStatus;
    $record->interventionId = $interventionId;
    $entityManager->persist($record);
    $entityManager->flush();

    return $id;
  }

  /**
   * Method newFacilityRecord.
   */
  private function newFacilityRecord(
    string $id,
    OrganizationRecord $organization,
    string $type,
    string $name,
    string $status,
    ?FacilityRecord $parent,
    DateTimeImmutable $now,
  ): FacilityRecord {
    $record = new FacilityRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->parentFacility = $parent;
    $record->type = $type;
    $record->name = $name;
    $record->status = $status;
    $record->recordStatus = 'published';
    $record->revision = 1;
    $record->metadata = [];
    $record->createdAt = $now;
    $record->updatedAt = $now;

    return $record;
  }

  /**
   * Method seedMetadataField.
   *
   * Seeds one `number`-typed metadata field definition for the main test
   * organization (call `seedOrganization()` first — it recreates the
   * organization and cascade-deletes any previous definitions).
   */
  private function seedMetadataField(string $id, string $key): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $organization = $entityManager->find(OrganizationRecord::class, self::ORGANIZATION_ID);
    self::assertInstanceOf(OrganizationRecord::class, $organization);

    $now = new DateTimeImmutable('2026-08-16T00:00:00+00:00');

    $record = new FacilityMetadataFieldRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->key = $key;
    $record->label = 'Label for ' . $key;
    $record->fieldType = 'number';
    $record->options = [];
    $record->facilityType = null;
    $record->required = false;
    $record->unit = null;
    $record->createdAt = $now;
    $record->updatedAt = $now;
    $entityManager->persist($record);
    $entityManager->flush();
  }

  // #endregion
}
