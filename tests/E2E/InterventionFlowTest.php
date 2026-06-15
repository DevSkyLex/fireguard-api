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
 * End-to-end characterization tests for the intervention workflow.
 *
 * Exercises the real Doctrine workflow adapter through the HTTP API: draft
 * creation (with catalog default reference pack and computed counts), the
 * draft -> planned transition, scheduling conflicts, and authentication.
 * These guard the workflow gateway behavior during refactoring.
 */
final class InterventionFlowTest extends OAuth2WebTestCase
{
  private const string LD_JSON = 'application/ld+json';
  private const string MERGE_PATCH = 'application/merge-patch+json';

  public function testCreateDraftInterventionExposesDefaultsAndCounts(): void
  {
    $client = static::createClientWithFixtures();
    $email = 'intervention-owner-' . uniqid() . '@example.com';
    $password = 'OwnerPassword123!';
    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);
    $organizationId = $this->createOrganization($client, $token, 'Intervention Org ' . uniqid());
    self::assertNotNull($organizationId);

    $intervention = $this->createDraftIntervention($client, $token, $organizationId, 'Quarterly audit');

    self::assertSame('draft', $intervention['status'] ?? null);
    self::assertSame(1, $intervention['revision'] ?? null);
    self::assertSame('inspection_campaign', $intervention['type'] ?? null);
    self::assertNull($intervention['site'] ?? null, 'A bare draft has no site.');
    self::assertSame(0, $intervention['workItemsCount'] ?? null);
    self::assertSame(0, $intervention['facilitiesCount'] ?? null);
    self::assertSame(0, $intervention['proposedChangesCount'] ?? null);
    $referencePack = $intervention['referencePack'] ?? '';
    self::assertTrue(
      is_string($referencePack) && str_contains($referencePack, '/api/reference-packs/'),
      'Reference pack defaults to the catalog default IRI.',
    );

    // The single-item view goes through the same builder.
    $interventionId = $this->extractResourceId($intervention);
    self::assertNotNull($interventionId);
    $fetched = $this->getResource($client, $token, '/api/interventions/' . $interventionId);
    self::assertSame('draft', $fetched['status'] ?? null);
    self::assertSame($interventionId, $this->extractResourceId($fetched));
  }

  public function testPlanInterventionThroughTheWorkflow(): void
  {
    $client = static::createClientWithFixtures();
    $email = 'intervention-plan-' . uniqid() . '@example.com';
    $password = 'OwnerPassword123!';
    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);
    $organizationId = $this->createOrganization($client, $token, 'Plan Org ' . uniqid());
    self::assertNotNull($organizationId);

    $intervention = $this->createDraftIntervention($client, $token, $organizationId, 'Planned mission');
    $interventionId = $this->extractResourceId($intervention);
    self::assertNotNull($interventionId);

    $memberIri = $this->firstMemberIri($client, $token, $organizationId);
    self::assertNotNull($memberIri);
    $facilityId = $this->createFacility($client, $token, $organizationId);
    self::assertNotNull($facilityId);

    $planned = $this->patch(
      $client,
      $token,
      '/api/interventions/' . $interventionId,
      1,
      [
        'site' => '/api/facilities/' . $facilityId,
        'responsible' => $memberIri,
        'plannedStartAt' => '2026-07-01T09:00:00Z',
        'dueAt' => '2026-07-02T09:00:00Z',
        'priority' => 'high',
        'status' => 'planned',
      ],
    );

    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    self::assertSame('planned', $planned['status'] ?? null);
    self::assertSame('high', $planned['priority'] ?? null);
    self::assertSame(2, $planned['revision'] ?? null);
  }

  public function testPlanWithoutScheduleReturnsConflict(): void
  {
    $client = static::createClientWithFixtures();
    $email = 'intervention-conflict-' . uniqid() . '@example.com';
    $password = 'OwnerPassword123!';
    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);
    $organizationId = $this->createOrganization($client, $token, 'Conflict Org ' . uniqid());
    self::assertNotNull($organizationId);

    $intervention = $this->createDraftIntervention($client, $token, $organizationId, 'Unschedulable');
    $interventionId = $this->extractResourceId($intervention);
    self::assertNotNull($interventionId);

    $this->patch($client, $token, '/api/interventions/' . $interventionId, 1, ['status' => 'planned']);

    self::assertSame(
      Response::HTTP_CONFLICT,
      $client->getResponse()->getStatusCode(),
      'Planning a draft without site/responsible/schedule must conflict.',
    );
  }

  public function testInterventionEndpointsRequireAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request('GET', '/api/interventions');

    self::assertContains(
      $client->getResponse()->getStatusCode(),
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
    );
  }

  /**
   * @return array<string, mixed>
   */
  private function createDraftIntervention(
    KernelBrowser $client,
    string $token,
    string $organizationId,
    string $name,
  ): array {
    $client->request(
      method: 'POST',
      uri: '/api/interventions',
      server: $this->headers($token, self::LD_JSON),
      content: json_encode([
        'organization' => '/api/organizations/' . $organizationId,
        'type' => 'inspection_campaign',
        'name' => $name,
      ]) ?: '',
    );

    self::assertSame(
      Response::HTTP_CREATED,
      $client->getResponse()->getStatusCode(),
      'Draft intervention creation should succeed. Response: ' . ($client->getResponse()->getContent() ?: ''),
    );

    return $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
  }

  /**
   * @param array<string, mixed> $payload
   *
   * @return array<string, mixed>
   */
  private function patch(
    KernelBrowser $client,
    string $token,
    string $uri,
    int $revision,
    array $payload,
  ): array {
    $headers = $this->headers($token, self::MERGE_PATCH);
    $headers['HTTP_IF_MATCH'] = '"revision-' . $revision . '"';
    $client->request(
      method: 'PATCH',
      uri: $uri,
      server: $headers,
      content: json_encode($payload) ?: '',
    );

    return $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
  }

  /**
   * @return array<string, mixed>
   */
  private function getResource(KernelBrowser $client, string $token, string $uri): array
  {
    $client->request('GET', $uri, server: $this->headers($token, self::LD_JSON));

    return $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
  }

  private function firstMemberIri(KernelBrowser $client, string $token, string $organizationId): ?string
  {
    $client->request(
      'GET',
      '/api/organizations/' . $organizationId . '/members',
      server: $this->headers($token, self::LD_JSON),
    );
    $data = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    $members = $data['member'] ?? [];
    if (!is_array($members) || [] === $members) {
      return null;
    }
    $first = $members[0];
    if (!is_array($first)) {
      return null;
    }
    $memberId = $this->extractResourceId($first);

    return null === $memberId
      ? null
      : '/api/organizations/' . $organizationId . '/members/' . $memberId;
  }

  private function createFacility(KernelBrowser $client, string $token, string $organizationId): ?string
  {
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/facilities',
      server: $this->headers($token, self::LD_JSON),
      content: json_encode(['type' => 'building', 'name' => 'Main Building']) ?: '',
    );

    return $this->extractResourceId(
      $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}'),
    );
  }

  private function loginAndGetUserAccessToken(KernelBrowser $client, string $email, string $password): string
  {
    $client->request(
      method: 'POST',
      uri: '/api/auth/login',
      server: ['CONTENT_TYPE' => self::LD_JSON, 'HTTP_ACCEPT' => self::LD_JSON],
      content: json_encode(['email' => $email, 'password' => $password]) ?: '',
    );

    $data = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    $token = $data['access_token'] ?? null;
    self::assertIsString($token, 'Login should return an access token.');
    self::assertNotSame('', $token);

    return $token;
  }

  private function createOrganization(KernelBrowser $client, string $token, string $name): ?string
  {
    $client->request(
      method: 'POST',
      uri: '/api/organizations',
      server: $this->headers($token, self::LD_JSON),
      content: json_encode(['name' => $name]) ?: '',
    );

    return $this->extractResourceId(
      $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}'),
    );
  }

  /**
   * @return array<string, string>
   */
  private function headers(string $token, string $contentType): array
  {
    return [
      'CONTENT_TYPE' => $contentType,
      'HTTP_ACCEPT' => self::LD_JSON,
      'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
    ];
  }

  /**
   * @param array<array-key, mixed> $data
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
}
