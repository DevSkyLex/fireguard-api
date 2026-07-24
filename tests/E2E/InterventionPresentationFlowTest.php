<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function array_key_exists;
use function basename;
use function http_build_query;
use function is_array;
use function is_string;
use function json_encode;
use function str_contains;
use function uniqid;

/**
 * Test InterventionPresentation.
 *
 * Authenticated E2E flow tests for the Intervention Presentation endpoints
 * that the existing InterventionFlowTest / InterventionActivityFlowTest /
 * InterventionLabelFlowTest suites do not reach: the intervention-type
 * catalog, the workflow issues/changes/work-items collections, the
 * organization-scoped recurrences and templates collections, the attachment
 * collection, and the auth guards for the publication, team-assignment,
 * attachment and workflow write routes.
 *
 * @category E2E Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionPresentationFlowTest extends OAuth2WebTestCase
{
  private const string LD_JSON = 'application/ld+json';

  private const string FAKE_ID = '550e8400-e29b-41d4-a716-446655440000';

  private const string FAKE_ORG_ID = '11111111-1111-4111-8111-111111111111';

  public function testInterventionTypeCatalogIsListedForAuthenticatedUser(): void
  {
    [$client, $token] = $this->setUp_('type');

    $collection = $this->get($client, $token, '/api/intervention-types');
    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

    $members = $this->members($collection);
    self::assertNotSame([], $members, 'The intervention-type catalog must expose at least one type.');
    $first = $members[0];
    self::assertArrayHasKey('id', $first);
    self::assertArrayHasKey('label', $first);
    self::assertIsString($first['id']);
  }

  public function testInterventionIssuesCollectionForADraftIsAHydraCollection(): void
  {
    [$client, $token, $organizationId] = $this->setUpWithOrganization('issues');
    $interventionId = $this->createDraftIntervention($client, $token, $organizationId, 'Issues mission');

    $collection = $this->get($client, $token, '/api/interventions/' . $interventionId . '/issues');
    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    self::assertTrue(array_key_exists('member', $collection), 'The issues route must return a Hydra collection.');
    self::assertIsArray($collection['member']);
  }

  public function testInterventionChangesCollectionForAnInterventionIsAHydraCollection(): void
  {
    [$client, $token, $organizationId] = $this->setUpWithOrganization('changes');
    $interventionId = $this->createDraftIntervention($client, $token, $organizationId, 'Changes mission');

    $collection = $this->get($client, $token, '/api/intervention-changes?' . http_build_query([
      'intervention' => '/api/interventions/' . $interventionId,
    ]));
    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    self::assertTrue(array_key_exists('member', $collection), 'The changes route must return a Hydra collection.');
    self::assertIsArray($collection['member']);
    self::assertArrayHasKey('totalItems', $collection);
  }

  public function testInterventionChangesCollectionRequiresTheInterventionFilter(): void
  {
    [$client, $token] = $this->setUp_('changes-missing');

    $client->request('GET', '/api/intervention-changes', server: $this->headers($token));

    self::assertSame(
      Response::HTTP_BAD_REQUEST,
      $client->getResponse()->getStatusCode(),
      'Listing changes without the intervention filter must be rejected with 400.',
    );
  }

  public function testInterventionWorkItemsCollectionForAnInterventionIsAHydraCollection(): void
  {
    [$client, $token, $organizationId] = $this->setUpWithOrganization('work-items');
    $interventionId = $this->createDraftIntervention($client, $token, $organizationId, 'Work items mission');

    $collection = $this->get($client, $token, '/api/intervention-work-items?' . http_build_query([
      'intervention' => '/api/interventions/' . $interventionId,
    ]));
    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    self::assertTrue(array_key_exists('member', $collection), 'The work-items route must return a Hydra collection.');
    self::assertIsArray($collection['member']);
    self::assertArrayHasKey('totalItems', $collection);
  }

  public function testInterventionWorkItemsCollectionRequiresTheInterventionFilter(): void
  {
    [$client, $token] = $this->setUp_('work-items-missing');

    $client->request('GET', '/api/intervention-work-items', server: $this->headers($token));

    self::assertSame(
      Response::HTTP_BAD_REQUEST,
      $client->getResponse()->getStatusCode(),
      'Listing work items without the intervention filter must be rejected with 400.',
    );
  }

  public function testInterventionRecurrencesCollectionForAnOrganizationIsAHydraCollection(): void
  {
    [$client, $token, $organizationId] = $this->setUpWithOrganization('recurrences');

    $collection = $this->get($client, $token, '/api/intervention-recurrences?' . http_build_query([
      'organization' => '/api/organizations/' . $organizationId,
    ]));
    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    self::assertTrue(array_key_exists('member', $collection), 'The recurrences route must return a Hydra collection.');
    self::assertIsArray($collection['member']);
    self::assertArrayHasKey('totalItems', $collection);
  }

  public function testInterventionRecurrencesCollectionRequiresTheOrganizationFilter(): void
  {
    [$client, $token] = $this->setUp_('recurrences-missing');

    $client->request('GET', '/api/intervention-recurrences', server: $this->headers($token));

    self::assertSame(
      Response::HTTP_BAD_REQUEST,
      $client->getResponse()->getStatusCode(),
      'Listing recurrences without the organization filter must be rejected with 400.',
    );
  }

  public function testInterventionTemplatesCollectionForAnOrganizationIsAHydraCollection(): void
  {
    [$client, $token, $organizationId] = $this->setUpWithOrganization('templates');

    $collection = $this->get($client, $token, '/api/intervention-templates?' . http_build_query([
      'organization' => '/api/organizations/' . $organizationId,
    ]));
    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    self::assertTrue(array_key_exists('member', $collection), 'The templates route must return a Hydra collection.');
    self::assertIsArray($collection['member']);
    self::assertArrayHasKey('totalItems', $collection);
  }

  public function testInterventionTemplatesCollectionRequiresTheOrganizationFilter(): void
  {
    [$client, $token] = $this->setUp_('templates-missing');

    $client->request('GET', '/api/intervention-templates', server: $this->headers($token));

    self::assertSame(
      Response::HTTP_BAD_REQUEST,
      $client->getResponse()->getStatusCode(),
      'Listing templates without the organization filter must be rejected with 400.',
    );
  }

  public function testInterventionAttachmentsCollectionForADraftIsAListing(): void
  {
    [$client, $token, $organizationId] = $this->setUpWithOrganization('attachments');
    $interventionId = $this->createDraftIntervention($client, $token, $organizationId, 'Attachments mission');

    $collection = $this->get($client, $token, '/api/interventions/' . $interventionId . '/attachments');
    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    self::assertTrue(array_key_exists('member', $collection), 'The attachments route must return a Hydra collection.');
    self::assertIsArray($collection['member']);
  }

  /**
   * Guard: every uncovered Intervention Presentation route exists (not 404)
   * and rejects an unauthenticated caller with 401 or 403.
   *
   * The single-item attachment GET (`/api/intervention-attachments/{id}`) is
   * intentionally omitted: its provider performs a direct Doctrine lookup
   * before the authorization check, so a fake id yields 404 rather than a
   * guard status. It is exercised through its collection sibling above.
   */
  public function testUncoveredEndpointsRejectUnauthenticatedCallers(): void
  {
    $client = static::createClientWithFixtures();

    $interventionIri = '/api/interventions/' . self::FAKE_ID;
    $organizationIri = '/api/organizations/' . self::FAKE_ORG_ID;

    /** @var list<array{0: string, 1: string, 2: ?array<string, mixed>}> $routes */
    $routes = [
      ['GET', '/api/intervention-types', null],
      ['GET', '/api/interventions/' . self::FAKE_ID . '/issues', null],
      ['GET', '/api/intervention-changes?' . http_build_query(['intervention' => $interventionIri]), null],
      ['GET', '/api/intervention-changes/' . self::FAKE_ID, null],
      ['GET', '/api/intervention-work-items?' . http_build_query(['intervention' => $interventionIri]), null],
      ['GET', '/api/intervention-work-items/' . self::FAKE_ID, null],
      ['GET', '/api/intervention-recurrences?' . http_build_query(['organization' => $organizationIri]), null],
      ['GET', '/api/intervention-recurrences/' . self::FAKE_ID, null],
      ['GET', '/api/intervention-templates?' . http_build_query(['organization' => $organizationIri]), null],
      ['GET', '/api/intervention-templates/' . self::FAKE_ID, null],
      ['GET', '/api/interventions/' . self::FAKE_ID . '/attachments', null],
      ['GET', '/api/publications/' . self::FAKE_ID, null],
      ['POST', '/api/publications', ['intervention' => $interventionIri]],
      ['POST', '/api/interventions/' . self::FAKE_ID . '/team-assignments', ['team' => $organizationIri]],
      ['POST', '/api/intervention-changes', ['intervention' => $interventionIri]],
      ['POST', '/api/intervention-work-items', ['intervention' => $interventionIri]],
      ['POST', '/api/intervention-recurrences', ['organization' => $organizationIri]],
      ['POST', '/api/intervention-templates', ['organization' => $organizationIri]],
      ['POST', '/api/intervention-templates/' . self::FAKE_ID . '/instantiate', ['name' => 'x']],
    ];

    foreach ($routes as [$method, $uri, $body]) {
      $server = ['HTTP_ACCEPT' => self::LD_JSON];
      $content = '';
      if (null !== $body) {
        $server['CONTENT_TYPE'] = self::LD_JSON;
        $content = json_encode($body) ?: '';
      }

      $client->request(method: $method, uri: $uri, server: $server, content: $content);
      $status = $client->getResponse()->getStatusCode();

      self::assertNotSame(
        Response::HTTP_NOT_FOUND,
        $status,
        "Route {$method} {$uri} should exist.",
      );
      self::assertContains(
        $status,
        [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
        "Route {$method} {$uri} should reject unauthenticated callers. Status: {$status}",
      );
    }
  }

  /**
   * Boot a client with an authenticated, activated user (no organization).
   *
   * @return array{0: KernelBrowser, 1: string}
   */
  private function setUp_(string $suffix): array
  {
    $client = static::createClientWithFixtures();
    $email = 'intervention-presentation-' . $suffix . '-' . uniqid() . '@example.com';
    $password = 'OwnerPassword123!';
    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);

    return [$client, $token];
  }

  /**
   * Boot a client with an authenticated user owning a fresh organization.
   *
   * @return array{0: KernelBrowser, 1: string, 2: string}
   */
  private function setUpWithOrganization(string $suffix): array
  {
    [$client, $token] = $this->setUp_($suffix);
    $organizationId = $this->createOrganization($client, $token, 'Presentation Org ' . $suffix . ' ' . uniqid());
    self::assertNotNull($organizationId);

    return [$client, $token, $organizationId];
  }

  private function createDraftIntervention(
    KernelBrowser $client,
    string $token,
    string $organizationId,
    string $name,
  ): string {
    $client->request(
      method: 'POST',
      uri: '/api/interventions',
      server: $this->headers($token),
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

    $id = $this->extractResourceId(
      $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}'),
    );
    self::assertNotNull($id);

    return $id;
  }

  /**
   * @return array<string, mixed>
   */
  private function get(KernelBrowser $client, string $token, string $uri): array
  {
    $client->request('GET', $uri, server: $this->headers($token));

    return $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
  }

  /**
   * Extracts the Hydra collection members as plain arrays.
   *
   * @param array<string, mixed> $collection
   *
   * @return list<array<string, mixed>>
   */
  private function members(array $collection): array
  {
    $members = $collection['member'] ?? [];
    if (!is_array($members)) {
      return [];
    }

    $items = [];
    foreach ($members as $member) {
      if (is_array($member)) {
        /** @var array<string, mixed> $member */
        $items[] = $member;
      }
    }

    return $items;
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
      server: $this->headers($token),
      content: json_encode(['name' => $name]) ?: '',
    );

    return $this->extractResourceId(
      $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}'),
    );
  }

  /**
   * @return array<string, string>
   */
  private function headers(string $token): array
  {
    return [
      'CONTENT_TYPE' => self::LD_JSON,
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
