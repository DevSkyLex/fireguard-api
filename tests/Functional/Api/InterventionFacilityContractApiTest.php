<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

use function json_decode;
use function json_encode;

/**
 * Test InterventionFacilityContractApiTest.
 *
 * End-to-end contract coverage for the intervention<->facility relationship
 * that {@see \Intervention\Application\Service\InterventionIssueFinder} and
 * {@see \Facility\Presentation\Api\Processor\Facility\CreateFacilityProcessor}
 * establish together:
 *
 * - a `site_setup` intervention with no facility reports the
 *   "At least one facility is required." blocker on
 *   `GET /interventions/{id}/issues`;
 * - `POST /facilities` with an `intervention` IRI attaches the created
 *   facility (draft `recordStatus`) inside a transaction and clears that
 *   blocker, letting the intervention run the real workflow
 *   (draft -> planned -> in_progress -> submitted) through to a
 *   `RequestPublicationHandler` call that no longer raises it;
 * - attaching a facility to an intervention that is no longer mutable
 *   (`submitted`, `published`, `abandoned`) is refused with 409, straight
 *   from {@see \Intervention\Application\Service\InterventionResourceManager::mutationPermission()};
 * - an intervention outside the caller's organization scope answers 404,
 *   the same isolation proof {@see FacilityApiTest} establishes for the
 *   legacy org-scoped facility surface.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionFacilityContractApiTest extends WebTestCase
{
  private const string ORGANIZATION_ID = '9a0e8400-e29b-41d4-a716-446655480001';

  private const string ADMIN_USER_ID = '9a0e8400-e29b-41d4-a716-446655480002';

  private const string ADMIN_MEMBER_ID = '9a0e8400-e29b-41d4-a716-446655480003';

  private const string ADMIN_ROLE_ID = '9a0e8400-e29b-41d4-a716-446655480004';

  private const string OUTSIDER_ORGANIZATION_ID = '9a0e8400-e29b-41d4-a716-446655480005';

  private const string OUTSIDER_USER_ID = '9a0e8400-e29b-41d4-a716-446655480006';

  private const string OUTSIDER_MEMBER_ID = '9a0e8400-e29b-41d4-a716-446655480007';

  private const string OUTSIDER_ROLE_ID = '9a0e8400-e29b-41d4-a716-446655480008';

  private const string ADMIN_EMAIL = 'intervention-facility-contract-admin@example.com';

  private const string OUTSIDER_EMAIL = 'intervention-facility-contract-outsider@example.com';

  /**
   * Method nextInterventionNumber.
   *
   * `interventions` has a per-organization unique (organization_id, number)
   * constraint; each seeded fixture needs its own number since they share
   * {@see self::ORGANIZATION_ID}.
   */
  private static int $interventionNumberSequence = 900000;

  // #region Nominal flow

  /**
   * The full contract: create a `site_setup` intervention, see the facility
   * blocker on the issues gate, resolve it with a canonical facility
   * attachment, see the blocker clear, then drive the intervention through
   * the real workflow (draft -> planned -> in_progress -> submitted) and
   * confirm `RequestPublicationHandler` no longer raises the blocker.
   */
  #[Test]
  public function testFacilityAttachmentResolvesTheSiteSetupBlockerThroughSubmissionAndPublicationRequest(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->loginAs($client, self::ADMIN_USER_ID, self::ADMIN_EMAIL);

    $client->request(
      method: 'POST',
      uri: '/api/interventions',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
        'name' => 'Site Setup Contract Intervention',
      ]),
    );
    $createResponse = $client->getResponse();
    self::assertSame(201, $createResponse->getStatusCode(), (string) $createResponse->getContent());
    $created = json_decode((string) $createResponse->getContent(), true);
    self::assertIsArray($created);
    self::assertSame('site_setup', $created['type'] ?? null, 'The intervention must resolve to the site_setup type — either chosen explicitly or via the finder\'s fallback.');
    self::assertIsString($created['id'] ?? null);
    $interventionId = $created['id'];
    self::assertSame(1, $created['revision'] ?? null);

    // GET issues: the facility blocker must be present before any facility exists.
    static::ensureKernelShutdown();
    $issuesBeforeClient = static::createClient();
    $this->loginAs($issuesBeforeClient, self::ADMIN_USER_ID, self::ADMIN_EMAIL);
    $issuesBeforeClient->request('GET', '/api/interventions/' . $interventionId . '/issues');
    self::assertSame(200, $issuesBeforeClient->getResponse()->getStatusCode(), (string) $issuesBeforeClient->getResponse()->getContent());
    $issuesBefore = $this->decodeIssues($issuesBeforeClient);
    self::assertTrue(
      $this->hasIssue($issuesBefore, 'blocker', 'At least one facility is required.'),
      'A site_setup intervention with no facility must report the facility blocker.',
    );

    // POST /api/facilities with the intervention IRI: attaches in a transaction, draft recordStatus.
    static::ensureKernelShutdown();
    $facilityClient = static::createClient();
    $this->loginAs($facilityClient, self::ADMIN_USER_ID, self::ADMIN_EMAIL);
    $facilityClient->request(
      method: 'POST',
      uri: '/api/facilities',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
        'intervention' => '/api/interventions/' . $interventionId,
        'type' => 'site',
        'name' => 'Contract Site',
      ]),
    );
    $facilityResponse = $facilityClient->getResponse();
    self::assertSame(201, $facilityResponse->getStatusCode(), (string) $facilityResponse->getContent());
    $facility = json_decode((string) $facilityResponse->getContent(), true);
    self::assertIsArray($facility);
    self::assertSame(
      '/api/interventions/' . $interventionId,
      $facility['intervention'] ?? null,
      'The created facility must carry the intervention IRI in the response.',
    );
    self::assertSame('draft', $facility['recordStatus'] ?? null, 'A facility attached at creation to an intervention defaults to draft.');
    self::assertIsString($facility['id'] ?? null);
    $facilityId = $facility['id'];

    // GET issues again: the facility blocker must have cleared.
    static::ensureKernelShutdown();
    $issuesAfterClient = static::createClient();
    $this->loginAs($issuesAfterClient, self::ADMIN_USER_ID, self::ADMIN_EMAIL);
    $issuesAfterClient->request('GET', '/api/interventions/' . $interventionId . '/issues');
    self::assertSame(200, $issuesAfterClient->getResponse()->getStatusCode());
    $issuesAfter = $this->decodeIssues($issuesAfterClient);
    self::assertFalse(
      $this->hasIssue($issuesAfter, 'blocker', 'At least one facility is required.'),
      'Once a facility is attached, the facility blocker must clear.',
    );

    // Drive the real workflow: draft -> planned -> in_progress -> submitted.
    // Attaching the facility bumped the intervention's own revision (the
    // resource-gateway adapter touches the parent intervention on assign),
    // so the starting revision must be read back rather than assumed to
    // still be 1.
    static::ensureKernelShutdown();
    $refreshClient = static::createClient();
    $this->loginAs($refreshClient, self::ADMIN_USER_ID, self::ADMIN_EMAIL);
    $refreshClient->request('GET', '/api/interventions/' . $interventionId);
    self::assertSame(200, $refreshClient->getResponse()->getStatusCode());
    $refreshed = json_decode((string) $refreshClient->getResponse()->getContent(), true);
    self::assertIsArray($refreshed);
    self::assertIsInt($refreshed['revision'] ?? null);
    $revision = $refreshed['revision'];

    foreach (['planned', 'in_progress', 'submitted'] as $status) {
      // Planning requires a site, a responsible member, and both dates —
      // the intervention's own workflow invariant, independent of the
      // facility contract under test; supply them on the first hop.
      $payload = 'planned' === $status
        ? [
          'status' => $status,
          'site' => '/api/facilities/' . $facilityId,
          'responsible' => self::ADMIN_MEMBER_ID,
          'plannedStartAt' => '2026-06-02T09:00:00+00:00',
          'dueAt' => '2026-06-03T18:00:00+00:00',
        ]
        : ['status' => $status];

      static::ensureKernelShutdown();
      $stepClient = static::createClient();
      $this->loginAs($stepClient, self::ADMIN_USER_ID, self::ADMIN_EMAIL);
      $stepClient->request(
        method: 'PATCH',
        uri: '/api/interventions/' . $interventionId,
        server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json', 'HTTP_IF_MATCH' => '"revision-' . $revision . '"'],
        content: (string) json_encode($payload),
      );
      $stepResponse = $stepClient->getResponse();
      self::assertSame(200, $stepResponse->getStatusCode(), 'Transition to ' . $status . ' must succeed. Response: ' . $stepResponse->getContent());
      $stepDecoded = json_decode((string) $stepResponse->getContent(), true);
      self::assertIsArray($stepDecoded);
      self::assertSame($status, $stepDecoded['status'] ?? null);
      self::assertIsInt($stepDecoded['revision'] ?? null);
      $revision = $stepDecoded['revision'];
    }

    // RequestPublication (PublicationProcessor -> RequestPublicationHandler):
    // the gate must no longer raise the facility blocker (which would surface
    // as InterventionBlockedException -> 422).
    static::ensureKernelShutdown();
    $publicationClient = static::createClient();
    $this->loginAs($publicationClient, self::ADMIN_USER_ID, self::ADMIN_EMAIL);
    $publicationClient->request(
      method: 'POST',
      uri: '/api/publications',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'intervention' => '/api/interventions/' . $interventionId,
        'interventionRevision' => $revision,
      ]),
    );
    $publicationResponse = $publicationClient->getResponse();
    self::assertSame(
      Response::HTTP_ACCEPTED,
      $publicationResponse->getStatusCode(),
      'RequestPublicationHandler must accept the publication once the facility blocker is resolved, '
        . 'not answer 422 (InterventionBlockedException). Response: ' . $publicationResponse->getContent(),
    );
  }

  // #endregion

  // #region Bonus: draft facility surfaces on the intervention-scoped list

  #[Test]
  public function testListFacilitiesFilteredByInterventionReturnsTheAttachedDraftFacility(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->loginAs($client, self::ADMIN_USER_ID, self::ADMIN_EMAIL);

    $interventionId = $this->createDraftIntervention($client, 'Draft List Intervention');

    static::ensureKernelShutdown();
    $facilityClient = static::createClient();
    $this->loginAs($facilityClient, self::ADMIN_USER_ID, self::ADMIN_EMAIL);
    $facilityClient->request(
      method: 'POST',
      uri: '/api/facilities',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
        'intervention' => '/api/interventions/' . $interventionId,
        'type' => 'site',
        'name' => 'Listable Draft Site',
      ]),
    );
    self::assertSame(201, $facilityClient->getResponse()->getStatusCode(), (string) $facilityClient->getResponse()->getContent());
    $facility = json_decode((string) $facilityClient->getResponse()->getContent(), true);
    self::assertIsArray($facility);
    $facilityId = $facility['id'];

    static::ensureKernelShutdown();
    $listClient = static::createClient();
    $this->loginAs($listClient, self::ADMIN_USER_ID, self::ADMIN_EMAIL);
    $listClient->request('GET', '/api/facilities?intervention=/api/interventions/' . $interventionId);
    self::assertSame(200, $listClient->getResponse()->getStatusCode(), (string) $listClient->getResponse()->getContent());
    $decoded = json_decode((string) $listClient->getResponse()->getContent(), true);
    self::assertIsArray($decoded);
    self::assertIsArray($decoded['member']);

    $ids = [];
    foreach ($decoded['member'] as $item) {
      self::assertIsArray($item);
      $ids[] = $item['id'] ?? null;
      if (($item['id'] ?? null) === $facilityId) {
        self::assertSame('draft', $item['recordStatus'] ?? null, 'recordStatus must default to draft when filtering by intervention.');
      }
    }
    self::assertContains($facilityId, $ids, 'GET /facilities?intervention={iri} must surface the attached draft facility.');
  }

  // #endregion

  // #region 409 — the intervention is no longer mutable

  /**
   * @return array<string, array{0: string}>
   */
  public static function immutableInterventionStatusProvider(): array
  {
    return [
      'submitted' => ['submitted'],
      'published' => ['published'],
      'abandoned' => ['abandoned'],
    ];
  }

  #[Test]
  #[DataProvider('immutableInterventionStatusProvider')]
  public function testCreateFacilityRejectsAttachmentToAnImmutableIntervention(string $interventionStatus): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $interventionId = $this->seedInterventionWithStatus($interventionStatus, self::ORGANIZATION_ID, '9a0e8400-e29b-41d4-a716-4466554800' . match ($interventionStatus) {
      'submitted' => '10',
      'published' => '11',
      default => '12',
    });

    $this->loginAs($client, self::ADMIN_USER_ID, self::ADMIN_EMAIL);
    $client->request(
      method: 'POST',
      uri: '/api/facilities',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
        'intervention' => '/api/interventions/' . $interventionId,
        'type' => 'site',
        'name' => 'Should Not Attach',
      ]),
    );

    self::assertSame(
      409,
      $client->getResponse()->getStatusCode(),
      'Attaching a facility to an intervention in status "' . $interventionStatus . '" must be refused with 409. Response: ' . $client->getResponse()->getContent(),
    );
  }

  // #endregion

  // #region 404 — cross-tenant isolation

  #[Test]
  public function testCreateFacilityReturns404ForAnInterventionOutsideTheCallerOrganizationScope(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->loginAs($client, self::ADMIN_USER_ID, self::ADMIN_EMAIL);
    $interventionId = $this->createDraftIntervention($client, 'Owning Org Intervention');

    // OUTSIDER_USER has a membership in an unrelated organization only — not
    // in ORGANIZATION_ID, the intervention's own scope.
    static::ensureKernelShutdown();
    $outsiderClient = static::createClient();
    $this->loginAs($outsiderClient, self::OUTSIDER_USER_ID, self::OUTSIDER_EMAIL);
    $outsiderClient->request(
      method: 'POST',
      uri: '/api/facilities',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
        'intervention' => '/api/interventions/' . $interventionId,
        'type' => 'site',
        'name' => 'Should Not Be Created',
      ]),
    );

    self::assertSame(
      404,
      $outsiderClient->getResponse()->getStatusCode(),
      'A caller outside the intervention\'s organization scope must get 404, not 403 or 409. Response: ' . $outsiderClient->getResponse()->getContent(),
    );
  }

  // #endregion

  // #region 404 — cross-organization oracle (organization = attacker's own, intervention = victim's)

  /**
   * The oracle this closes: a caller who names an intervention belonging to
   * ANOTHER organization while claiming an `organization` IRI they are
   * genuinely a member of used to leak the victim intervention's existence
   * and workflow status through the HTTP status code alone — 409 for an
   * immutable status (`submitted`/`published`/`abandoned`), 403 for a
   * mutable one gated by {@see \Intervention\Application\Service\InterventionMemberPolicy}
   * (`planned`/`in_progress`), 409 "must belong to the same organization"
   * from {@see \Intervention\Application\Service\InterventionResourceManager::attach()}
   * for `draft`. All six must now answer a uniform 404, resolved inside
   * {@see \Intervention\Application\Service\InterventionResourceManager::mutationPermission()}
   * BEFORE any status-derived branch is reached.
   *
   * @return array<string, array{0: string}>
   */
  public static function crossOrganizationInterventionStatusProvider(): array
  {
    return [
      'draft' => ['draft'],
      'planned' => ['planned'],
      'in_progress' => ['in_progress'],
      'submitted' => ['submitted'],
      'published' => ['published'],
      'abandoned' => ['abandoned'],
    ];
  }

  #[Test]
  #[DataProvider('crossOrganizationInterventionStatusProvider')]
  public function testCreateFacilityReturns404WhenOrganizationIsTheCallersOwnButInterventionBelongsToAnotherOrganization(string $interventionStatus): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $interventionId = $this->seedInterventionWithStatus(
      $interventionStatus,
      self::ORGANIZATION_ID,
      '9a0e8400-e29b-41d4-a716-446655480' . match ($interventionStatus) {
        'draft' => '020',
        'planned' => '021',
        'in_progress' => '022',
        'submitted' => '023',
        'published' => '024',
        default => '025',
      },
    );

    // OUTSIDER_USER names OUTSIDER_ORGANIZATION_ID — their own, genuine
    // membership — but points `intervention` at a row owned by ORGANIZATION_ID.
    $this->loginAs($client, self::OUTSIDER_USER_ID, self::OUTSIDER_EMAIL);
    $client->request(
      method: 'POST',
      uri: '/api/facilities',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'organization' => '/api/organizations/' . self::OUTSIDER_ORGANIZATION_ID,
        'intervention' => '/api/interventions/' . $interventionId,
        'type' => 'site',
        'name' => 'Should Not Be Created',
      ]),
    );

    self::assertSame(
      404,
      $client->getResponse()->getStatusCode(),
      'An intervention id belonging to another organization must answer a uniform 404 regardless of its '
        . 'workflow status ("' . $interventionStatus . '"), never 403 or 409. Response: ' . $client->getResponse()->getContent(),
    );
  }

  #[Test]
  public function testCreateEquipmentReturns404WhenOrganizationIsTheCallersOwnButInterventionBelongsToAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $interventionId = $this->seedInterventionWithStatus('draft', self::ORGANIZATION_ID, '9a0e8400-e29b-41d4-a716-446655480030');

    $this->loginAs($client, self::OUTSIDER_USER_ID, self::OUTSIDER_EMAIL);
    $client->request(
      method: 'POST',
      uri: '/api/equipment',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'organization' => '/api/organizations/' . self::OUTSIDER_ORGANIZATION_ID,
        'intervention' => '/api/interventions/' . $interventionId,
        'type' => 'fire_extinguisher',
      ]),
    );

    self::assertSame(
      404,
      $client->getResponse()->getStatusCode(),
      'POST /api/equipment must answer 404, not 403 or 409, for an intervention id belonging to another '
        . 'organization. Response: ' . $client->getResponse()->getContent(),
    );
  }

  #[Test]
  public function testCreateInspectionReturns404WhenOrganizationIsTheCallersOwnButInterventionBelongsToAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $interventionId = $this->seedInterventionWithStatus('draft', self::ORGANIZATION_ID, '9a0e8400-e29b-41d4-a716-446655480031');

    $this->loginAs($client, self::OUTSIDER_USER_ID, self::OUTSIDER_EMAIL);
    $client->request(
      method: 'POST',
      uri: '/api/inspections',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'organization' => '/api/organizations/' . self::OUTSIDER_ORGANIZATION_ID,
        'intervention' => '/api/interventions/' . $interventionId,
        'equipmentId' => '9a0e8400-e29b-41d4-a716-446655480032',
        'result' => 'pass',
        'performedAt' => '2026-06-01T10:00:00+00:00',
        'inspectorType' => 'external',
        'inspectorName' => 'Outsider Inspector',
      ]),
    );

    self::assertSame(
      404,
      $client->getResponse()->getStatusCode(),
      'POST /api/inspections must answer 404, not 403 or 409, for an intervention id belonging to another '
        . 'organization. Response: ' . $client->getResponse()->getContent(),
    );
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
   * Method createDraftIntervention.
   *
   * Creates a `site_setup` draft intervention through the real API and
   * returns its id. The caller must already be logged in as
   * {@see self::ADMIN_USER_ID} on the given client.
   */
  private function createDraftIntervention(KernelBrowser $client, string $name): string
  {
    $client->request(
      method: 'POST',
      uri: '/api/interventions',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
        'name' => $name,
      ]),
    );
    $response = $client->getResponse();
    self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
    $decoded = json_decode((string) $response->getContent(), true);
    self::assertIsArray($decoded);
    self::assertIsString($decoded['id'] ?? null);

    return $decoded['id'];
  }

  /**
   * Method decodeIssues.
   *
   * @return list<array<mixed>>
   */
  private function decodeIssues(KernelBrowser $client): array
  {
    $decoded = json_decode((string) $client->getResponse()->getContent(), true);
    self::assertIsArray($decoded);
    self::assertIsArray($decoded['member']);

    $issues = [];
    foreach ($decoded['member'] as $issue) {
      self::assertIsArray($issue);
      $issues[] = $issue;
    }

    return $issues;
  }

  /**
   * Method hasIssue.
   *
   * @param list<array<mixed>> $issues
   */
  private function hasIssue(array $issues, string $severity, string $message): bool
  {
    foreach ($issues as $issue) {
      if (($issue['severity'] ?? null) === $severity && ($issue['message'] ?? null) === $message) {
        return true;
      }
    }

    return false;
  }

  /**
   * Method seedOrganization.
   *
   * Seeds (idempotently) an organization with a full-access admin member,
   * plus a second, unrelated organization with its own full-access member —
   * the "outside scope" caller.
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
    $organization->name = 'Intervention Facility Contract Org';
    $organization->slug = 'intervention-facility-contract-org-' . self::ORGANIZATION_ID;
    $organization->ownerUserId = self::ADMIN_USER_ID;
    $organization->createdByUserId = self::ADMIN_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $outsiderOrganization = new OrganizationRecord();
    $outsiderOrganization->id = self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->name = 'Intervention Facility Contract Outsider Org';
    $outsiderOrganization->slug = 'intervention-facility-contract-outsider-org-' . self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->ownerUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->createdByUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->status = 'active';
    $outsiderOrganization->isActive = true;
    $outsiderOrganization->createdAt = $now;
    $outsiderOrganization->updatedAt = $now;
    $entityManager->persist($outsiderOrganization);

    $adminRole = new OrganizationRoleRecord();
    $adminRole->id = self::ADMIN_ROLE_ID;
    $adminRole->organization = $organization;
    $adminRole->name = 'ifc-contract-full-access';
    $adminRole->permissions = ['*'];
    $adminRole->description = 'Functional-test-only role granting every permission.';
    $adminRole->isSystem = false;
    $adminRole->createdAt = $now;
    $entityManager->persist($adminRole);

    $outsiderRole = new OrganizationRoleRecord();
    $outsiderRole->id = self::OUTSIDER_ROLE_ID;
    $outsiderRole->organization = $outsiderOrganization;
    $outsiderRole->name = 'ifc-contract-outsider-full-access';
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

    $outsiderMember = new OrganizationMemberRecord();
    $outsiderMember->id = self::OUTSIDER_MEMBER_ID;
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
   * Method seedInterventionWithStatus.
   *
   * Seeds (idempotently) an intervention in the given workflow status,
   * exercising {@see \Intervention\Application\Service\InterventionResourceManager::mutationPermission()}'s
   * immutable-state guard directly, without driving the full state machine
   * through the API for every status under test.
   */
  private function seedInterventionWithStatus(string $status, string $organizationId, string $interventionId): string
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $existing = $entityManager->find(InterventionRecord::class, $interventionId);
    if ($existing instanceof InterventionRecord) {
      $entityManager->remove($existing);
      $entityManager->flush();
    }

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    /** @var OrganizationRecord $organization */
    $organization = $entityManager->getReference(OrganizationRecord::class, $organizationId);

    $intervention = new InterventionRecord();
    $intervention->id = $interventionId;
    $intervention->organization = $organization;
    $intervention->type = 'site_setup';
    $intervention->name = 'Immutable Contract Intervention (' . $status . ')';
    $intervention->number = $this->nextInterventionNumber();
    $intervention->status = $status;
    $intervention->responsibleId = self::ADMIN_USER_ID;
    $intervention->createdAt = $now;
    $intervention->updatedAt = $now;
    $entityManager->persist($intervention);
    $entityManager->flush();

    return $interventionId;
  }

  private function nextInterventionNumber(): int
  {
    return ++self::$interventionNumberSequence;
  }

  // #endregion
}
