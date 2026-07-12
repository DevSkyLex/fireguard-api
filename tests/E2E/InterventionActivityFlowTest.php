<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function array_column;
use function array_map;
use function basename;
use function is_array;
use function is_string;
use function json_encode;
use function sort;
use function str_contains;
use function uniqid;

/**
 * End-to-end characterization tests for the intervention comment and
 * activity feed (Phase B2).
 *
 * Exercises the real Doctrine activity adapter and workflow gateway through
 * the HTTP API: the `created` system activity recorded at intervention
 * creation, the `status_changed` system activity recorded on a real
 * draft -> planned transition, member comments, and `commentsCount` on the
 * intervention output.
 */
final class InterventionActivityFlowTest extends OAuth2WebTestCase
{
  private const string LD_JSON = 'application/ld+json';

  private const string MERGE_PATCH = 'application/merge-patch+json';

  public function testCreatingAnInterventionRecordsACreatedSystemActivity(): void
  {
    [$client, $token, $organizationId] = $this->setUpOrganization('created');

    $intervention = $this->createDraftIntervention($client, $token, $organizationId, 'Created activity mission');
    $interventionId = $this->extractResourceId($intervention);
    self::assertNotNull($interventionId);

    $activities = $this->getResource($client, $token, '/api/interventions/' . $interventionId . '/activities');
    $items = $this->members($activities);
    self::assertNotSame([], $items, 'A freshly created intervention must have at least one activity.');

    $events = array_column($items, 'event');
    self::assertContains('created', $events, 'Intervention creation must record a created system activity.');

    $created = $items[0];
    self::assertSame('system', $created['kind'] ?? null);
    self::assertSame('created', $created['event'] ?? null);
    self::assertNull($created['body'] ?? null, 'A created system activity carries no body.');
  }

  public function testStatusTransitionRecordsAStatusChangedActivityWithFromTo(): void
  {
    [$client, $token, $organizationId] = $this->setUpOrganization('status-changed');

    $intervention = $this->createDraftIntervention($client, $token, $organizationId, 'Status change mission');
    $interventionId = $this->extractResourceId($intervention);
    self::assertNotNull($interventionId);

    $memberIri = $this->firstMemberIri($client, $token, $organizationId);
    self::assertNotNull($memberIri);
    $facilityId = $this->createFacility($client, $token, $organizationId);
    self::assertNotNull($facilityId);

    $this->patch(
      $client,
      $token,
      '/api/interventions/' . $interventionId,
      1,
      [
        'site' => '/api/facilities/' . $facilityId,
        'responsible' => $memberIri,
        'plannedStartAt' => '2026-07-01T09:00:00Z',
        'dueAt' => '2026-07-02T09:00:00Z',
        'status' => 'planned',
      ],
    );
    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

    $activities = $this->getResource($client, $token, '/api/interventions/' . $interventionId . '/activities');
    $items = $this->members($activities);

    $statusChanged = null;
    foreach ($items as $item) {
      if ('status_changed' === ($item['event'] ?? null)) {
        $statusChanged = $item;
      }
    }
    self::assertNotNull($statusChanged, 'A real status transition must record a status_changed activity.');
    self::assertSame('system', $statusChanged['kind'] ?? null);
    $payload = $statusChanged['payload'] ?? null;
    self::assertIsArray($payload, 'A status_changed activity must carry a from/to payload.');
    self::assertSame('draft', $payload['from'] ?? null);
    self::assertSame('planned', $payload['to'] ?? null);
  }

  public function testActivitiesAreOrderedOldestFirst(): void
  {
    [$client, $token, $organizationId] = $this->setUpOrganization('ordering');

    $intervention = $this->createDraftIntervention($client, $token, $organizationId, 'Ordering mission');
    $interventionId = $this->extractResourceId($intervention);
    self::assertNotNull($interventionId);

    $this->postComment($client, $token, $interventionId, 'First comment');
    $this->postComment($client, $token, $interventionId, 'Second comment');

    $activities = $this->getResource($client, $token, '/api/interventions/' . $interventionId . '/activities');
    $items = $this->members($activities);
    $createdAts = array_map(static function (array $item): string {
      $createdAt = $item['createdAt'] ?? '';

      return is_string($createdAt) ? $createdAt : '';
    }, $items);
    $sorted = $createdAts;
    sort($sorted);
    self::assertSame($sorted, $createdAts, 'Activities must be returned oldest-first.');

    $bodies = array_column($items, 'body');
    self::assertContains('First comment', $bodies);
    self::assertContains('Second comment', $bodies);
  }

  public function testActiveMemberCanPostACommentAndCommentsCountIncrements(): void
  {
    [$client, $token, $organizationId] = $this->setUpOrganization('comment');

    $intervention = $this->createDraftIntervention($client, $token, $organizationId, 'Comment mission');
    $interventionId = $this->extractResourceId($intervention);
    self::assertNotNull($interventionId);
    self::assertSame(0, $intervention['commentsCount'] ?? null);

    $comment = $this->postComment($client, $token, $interventionId, 'Please double-check the panel wiring.');
    self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
    self::assertSame('comment', $comment['kind'] ?? null);
    self::assertSame('comment', $comment['event'] ?? null);
    self::assertSame('Please double-check the panel wiring.', $comment['body'] ?? null);
    self::assertIsString($comment['actor'] ?? null, 'A comment must carry an actor member IRI.');
    self::assertStringContainsString('/api/organizations/' . $organizationId . '/members/', (string) $comment['actor']);

    $fetched = $this->getResource($client, $token, '/api/interventions/' . $interventionId);
    self::assertSame(1, $fetched['commentsCount'] ?? null, 'commentsCount must increment after a comment is posted.');
  }

  public function testBlankCommentBodyIsRejectedWithUnprocessableEntity(): void
  {
    [$client, $token, $organizationId] = $this->setUpOrganization('blank');

    $intervention = $this->createDraftIntervention($client, $token, $organizationId, 'Blank comment mission');
    $interventionId = $this->extractResourceId($intervention);
    self::assertNotNull($interventionId);

    $client->request(
      method: 'POST',
      uri: '/api/interventions/' . $interventionId . '/comments',
      server: $this->headers($token, self::LD_JSON),
      content: json_encode(['body' => '   ']) ?: '',
    );

    self::assertSame(
      Response::HTTP_UNPROCESSABLE_ENTITY,
      $client->getResponse()->getStatusCode(),
      'A blank comment body must be rejected with 422.',
    );
  }

  public function testActivitiesCollectionIsScopedToTheIntervention(): void
  {
    [$client, $token, $organizationId] = $this->setUpOrganization('scoping');

    $first = $this->createDraftIntervention($client, $token, $organizationId, 'Scoped mission one');
    $second = $this->createDraftIntervention($client, $token, $organizationId, 'Scoped mission two');
    $firstId = $this->extractResourceId($first);
    $secondId = $this->extractResourceId($second);
    self::assertNotNull($firstId);
    self::assertNotNull($secondId);

    $this->postComment($client, $token, $firstId, 'Comment on the first intervention only.');

    $secondActivities = $this->getResource($client, $token, '/api/interventions/' . $secondId . '/activities');
    $secondItems = $this->members($secondActivities);
    $secondBodies = array_column($secondItems, 'body');
    self::assertNotContains(
      'Comment on the first intervention only.',
      $secondBodies,
      'Activities must be scoped to their own intervention.',
    );
    foreach ($secondItems as $item) {
      $intervention = $item['intervention'] ?? '';
      self::assertIsString($intervention);
      self::assertStringContainsString($secondId, $intervention);
    }
  }

  /**
   * @return array{0: KernelBrowser, 1: string, 2: string}
   */
  private function setUpOrganization(string $suffix): array
  {
    $client = static::createClientWithFixtures();
    $email = 'intervention-activity-' . $suffix . '-' . uniqid() . '@example.com';
    $password = 'OwnerPassword123!';
    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);
    $organizationId = $this->createOrganization($client, $token, 'Activity Org ' . $suffix . ' ' . uniqid());
    self::assertNotNull($organizationId);

    return [$client, $token, $organizationId];
  }

  /**
   * @return array<string, mixed>
   */
  private function postComment(KernelBrowser $client, string $token, string $interventionId, string $body): array
  {
    $client->request(
      method: 'POST',
      uri: '/api/interventions/' . $interventionId . '/comments',
      server: $this->headers($token, self::LD_JSON),
      content: json_encode(['body' => $body]) ?: '',
    );

    return $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
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
