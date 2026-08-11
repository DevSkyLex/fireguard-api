<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function basename;
use function http_build_query;
use function is_array;
use function is_int;
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

  public function testWithdrawSubmissionReopensFieldWorkUntilResubmission(): void
  {
    $client = static::createClientWithFixtures();
    $email = 'intervention-withdraw-' . uniqid() . '@example.com';
    $password = 'OwnerPassword123!';
    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);
    $organizationId = $this->createOrganization($client, $token, 'Withdraw Org ' . uniqid());
    self::assertNotNull($organizationId);

    $intervention = $this->createDraftIntervention($client, $token, $organizationId, 'Withdrawable mission');
    $interventionId = $this->extractResourceId($intervention);
    self::assertNotNull($interventionId);

    $memberIri = $this->firstMemberIri($client, $token, $organizationId);
    self::assertNotNull($memberIri);
    $facilityId = $this->createFacility($client, $token, $organizationId);
    self::assertNotNull($facilityId);

    // Field work is prepared while drafting (only discovered work items may
    // appear later); the submission below freezes it.
    $client->request(
      method: 'POST',
      uri: '/api/intervention-work-items',
      server: $this->headers($token, self::LD_JSON),
      content: json_encode([
        'intervention' => '/api/interventions/' . $interventionId,
        'action' => 'inventory',
        'target' => 'Extinguishers — floor 2',
      ]) ?: '',
    );
    self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
    $workItem = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    $workItemId = $this->extractResourceId($workItem);
    self::assertNotNull($workItemId);

    $current = $this->getResource($client, $token, '/api/interventions/' . $interventionId);
    $revision = $current['revision'] ?? null;
    self::assertIsInt($revision);

    $planned = $this->patch($client, $token, '/api/interventions/' . $interventionId, $revision, [
      'site' => '/api/facilities/' . $facilityId,
      'responsible' => $memberIri,
      'plannedStartAt' => '2026-08-01T09:00:00Z',
      'dueAt' => '2026-08-02T09:00:00Z',
      'status' => 'planned',
    ]);
    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    $started = $this->patch($client, $token, '/api/interventions/' . $interventionId, self::revisionOf($planned), ['status' => 'in_progress']);
    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

    $submitted = $this->patch($client, $token, '/api/interventions/' . $interventionId, self::revisionOf($started), ['status' => 'submitted']);
    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    self::assertSame('submitted', $submitted['status'] ?? null);
    self::assertSame(
      ['changes_requested', 'in_progress'],
      $submitted['allowedTransitions'] ?? null,
      'A submitted intervention must offer withdrawal alongside the review outcome.',
    );

    // Submission freezes field work.
    $this->patch($client, $token, '/api/intervention-work-items/' . $workItemId, 1, ['status' => 'in_progress']);
    self::assertSame(
      Response::HTTP_CONFLICT,
      $client->getResponse()->getStatusCode(),
      'Work items must stay frozen while the intervention is under review.',
    );

    // The responsible withdraws the submission; work becomes mutable again.
    $withdrawn = $this->patch($client, $token, '/api/interventions/' . $interventionId, self::revisionOf($submitted), ['status' => 'in_progress']);
    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    self::assertSame('in_progress', $withdrawn['status'] ?? null);

    $this->patch($client, $token, '/api/intervention-work-items/' . $workItemId, 1, ['status' => 'in_progress']);
    self::assertSame(
      Response::HTTP_OK,
      $client->getResponse()->getStatusCode(),
      'Withdrawing the submission must reopen field work.',
    );

    // The work-item mutation above touched the intervention: re-read the
    // revision instead of trusting the pre-mutation snapshot.
    $reopened = $this->getResource($client, $token, '/api/interventions/' . $interventionId);
    $resubmitted = $this->patch($client, $token, '/api/interventions/' . $interventionId, self::revisionOf($reopened), ['status' => 'submitted']);
    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    self::assertSame('submitted', $resubmitted['status'] ?? null);
  }

  public function testReplansANonDraftInterventionButFreezesItUnderReview(): void
  {
    $client = static::createClientWithFixtures();
    $email = 'intervention-replan-' . uniqid() . '@example.com';
    $password = 'OwnerPassword123!';
    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);
    $organizationId = $this->createOrganization($client, $token, 'Replan Org ' . uniqid());
    self::assertNotNull($organizationId);

    $intervention = $this->createDraftIntervention($client, $token, $organizationId, 'Delayed mission');
    $interventionId = $this->extractResourceId($intervention);
    self::assertNotNull($interventionId);
    $memberIri = $this->firstMemberIri($client, $token, $organizationId);
    self::assertNotNull($memberIri);
    $facilityId = $this->createFacility($client, $token, $organizationId);
    self::assertNotNull($facilityId);

    $planned = $this->patch($client, $token, '/api/interventions/' . $interventionId, 1, [
      'site' => '/api/facilities/' . $facilityId,
      'responsible' => $memberIri,
      'plannedStartAt' => '2026-08-10T09:00:00Z',
      'dueAt' => '2026-08-12T09:00:00Z',
      'status' => 'planned',
    ]);
    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

    // The delayed intervention is rescheduled in place — no abandon-and-recreate.
    $replanned = $this->patch($client, $token, '/api/interventions/' . $interventionId, self::revisionOf($planned), [
      'plannedStartAt' => '2026-08-17T09:00:00Z',
      'dueAt' => '2026-08-19T09:00:00Z',
      'priority' => 'urgent',
    ]);
    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    self::assertSame('urgent', $replanned['priority'] ?? null);
    self::assertSame('planned', $replanned['status'] ?? null);

    // Clearing a planning value outside draft is refused.
    $this->patch($client, $token, '/api/interventions/' . $interventionId, self::revisionOf($replanned), ['dueAt' => null]);
    self::assertSame(Response::HTTP_CONFLICT, $client->getResponse()->getStatusCode());

    // The site is frozen after planning.
    $this->patch($client, $token, '/api/interventions/' . $interventionId, self::revisionOf($replanned), ['site' => '/api/facilities/' . $facilityId]);
    self::assertSame(Response::HTTP_CONFLICT, $client->getResponse()->getStatusCode());

    $started = $this->patch($client, $token, '/api/interventions/' . $interventionId, self::revisionOf($replanned), ['status' => 'in_progress']);
    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    $submitted = $this->patch($client, $token, '/api/interventions/' . $interventionId, self::revisionOf($started), ['status' => 'submitted']);
    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

    // Under review everything is frozen: withdraw first.
    $this->patch($client, $token, '/api/interventions/' . $interventionId, self::revisionOf($submitted), ['dueAt' => '2026-08-25T09:00:00Z']);
    self::assertSame(Response::HTTP_CONFLICT, $client->getResponse()->getStatusCode());

    // The replan left its trace on the activity feed.
    $activities = $this->getResource($client, $token, '/api/interventions/' . $interventionId . '/activities');
    $events = [];
    foreach ((array) ($activities['member'] ?? []) as $activity) {
      if (is_array($activity) && isset($activity['event'])) {
        $events[] = $activity['event'];
      }
    }
    self::assertContains('rescheduled', $events, 'A non-draft replan must append a rescheduled activity.');
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

  public function testTransitionWithoutIfMatchIsRejected(): void
  {
    $client = static::createClientWithFixtures();
    $email = 'intervention-ifmatch-' . uniqid() . '@example.com';
    $password = 'OwnerPassword123!';
    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);
    $organizationId = $this->createOrganization($client, $token, 'IfMatch Org ' . uniqid());
    self::assertNotNull($organizationId);

    $intervention = $this->createDraftIntervention($client, $token, $organizationId, 'Concurrent edit');
    $interventionId = $this->extractResourceId($intervention);
    self::assertNotNull($interventionId);

    // Mutating an existing intervention without the optimistic-concurrency
    // precondition must be refused before any state change is applied.
    $client->request(
      method: 'PATCH',
      uri: '/api/interventions/' . $interventionId,
      server: $this->headers($token, self::MERGE_PATCH),
      content: json_encode(['priority' => 'urgent']) ?: '',
    );

    self::assertSame(
      Response::HTTP_PRECONDITION_REQUIRED,
      $client->getResponse()->getStatusCode(),
      'A mutation without If-Match must be rejected with 428.',
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

  public function testPlannedStartWindowFilterBoundsTheCollection(): void
  {
    $client = static::createClientWithFixtures();
    $email = 'intervention-window-' . uniqid() . '@example.com';
    $password = 'OwnerPassword123!';
    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);
    $organizationId = $this->createOrganization($client, $token, 'Window Org ' . uniqid());
    self::assertNotNull($organizationId);

    $intervention = $this->createDraftIntervention($client, $token, $organizationId, 'Windowed mission');
    $interventionId = $this->extractResourceId($intervention);
    self::assertNotNull($interventionId);

    $this->patch(
      $client,
      $token,
      '/api/interventions/' . $interventionId,
      1,
      ['plannedStartAt' => '2026-09-15T09:00:00Z'],
    );
    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

    $organizationIri = '/api/organizations/' . $organizationId;

    $inWindow = $this->getResource($client, $token, '/api/interventions?' . http_build_query([
      'organization' => $organizationIri,
      'plannedStartAtAfter' => '2026-09-01T00:00:00Z',
      'plannedStartAtBefore' => '2026-09-30T23:59:59Z',
    ]));
    self::assertContains(
      $interventionId,
      $this->memberIds($inWindow),
      'An intervention planned inside the window must be listed.',
    );

    $outOfWindow = $this->getResource($client, $token, '/api/interventions?' . http_build_query([
      'organization' => $organizationIri,
      'plannedStartAtAfter' => '2026-10-01T00:00:00Z',
      'plannedStartAtBefore' => '2026-10-31T23:59:59Z',
    ]));
    self::assertNotContains(
      $interventionId,
      $this->memberIds($outOfWindow),
      'An intervention planned outside the window must be excluded.',
    );
  }

  public function testCreateAssignsSequentialNumberPerOrganization(): void
  {
    $client = static::createClientWithFixtures();
    $email = 'intervention-number-' . uniqid() . '@example.com';
    $password = 'OwnerPassword123!';
    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);
    $organizationId = $this->createOrganization($client, $token, 'Number Org ' . uniqid());
    self::assertNotNull($organizationId);

    $first = $this->createDraftIntervention($client, $token, $organizationId, 'First mission');
    $second = $this->createDraftIntervention($client, $token, $organizationId, 'Second mission');

    self::assertIsInt($first['number'] ?? null);
    self::assertIsInt($second['number'] ?? null);
    self::assertGreaterThan(0, $first['number']);
    self::assertSame(
      $first['number'] + 1,
      $second['number'],
      'Each new intervention takes the next per-organization number.',
    );
  }

  public function testDescriptionIsPersistedAndPatchable(): void
  {
    $client = static::createClientWithFixtures();
    $email = 'intervention-description-' . uniqid() . '@example.com';
    $password = 'OwnerPassword123!';
    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);
    $organizationId = $this->createOrganization($client, $token, 'Description Org ' . uniqid());
    self::assertNotNull($organizationId);

    $intervention = $this->createDraftIntervention(
      $client,
      $token,
      $organizationId,
      'Documented mission',
      'Initial scope notes.',
    );
    self::assertSame('Initial scope notes.', $intervention['description'] ?? null);
    $interventionId = $this->extractResourceId($intervention);
    self::assertNotNull($interventionId);

    $patched = $this->patch(
      $client,
      $token,
      '/api/interventions/' . $interventionId,
      1,
      ['description' => 'Revised scope notes.'],
    );
    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    self::assertSame('Revised scope notes.', $patched['description'] ?? null);
  }

  public function testNameFilterMatchesPartialCaseInsensitively(): void
  {
    $client = static::createClientWithFixtures();
    $email = 'intervention-search-' . uniqid() . '@example.com';
    $password = 'OwnerPassword123!';
    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);
    $organizationId = $this->createOrganization($client, $token, 'Search Org ' . uniqid());
    self::assertNotNull($organizationId);

    $matching = $this->createDraftIntervention($client, $token, $organizationId, 'Sprinkler Recharge Campaign');
    $other = $this->createDraftIntervention($client, $token, $organizationId, 'Fire door audit');
    $matchingId = $this->extractResourceId($matching);
    $otherId = $this->extractResourceId($other);
    self::assertNotNull($matchingId);
    self::assertNotNull($otherId);

    $filtered = $this->getResource($client, $token, '/api/interventions?' . http_build_query([
      'organization' => '/api/organizations/' . $organizationId,
      'name' => 'sprinkler',
    ]));
    $ids = $this->memberIds($filtered);
    self::assertContains($matchingId, $ids, 'Partial, case-insensitive name match must be listed.');
    self::assertNotContains($otherId, $ids, 'Non-matching interventions must be excluded.');
  }

  public function testPriorityFilterBoundsTheCollectionAndRejectsUnknownValues(): void
  {
    $client = static::createClientWithFixtures();
    $email = 'intervention-priority-' . uniqid() . '@example.com';
    $password = 'OwnerPassword123!';
    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);
    $organizationId = $this->createOrganization($client, $token, 'Priority Org ' . uniqid());
    self::assertNotNull($organizationId);

    $urgent = $this->createDraftIntervention($client, $token, $organizationId, 'Urgent mission');
    $normal = $this->createDraftIntervention($client, $token, $organizationId, 'Routine mission');
    $urgentId = $this->extractResourceId($urgent);
    $normalId = $this->extractResourceId($normal);
    self::assertNotNull($urgentId);
    self::assertNotNull($normalId);
    $this->patch($client, $token, '/api/interventions/' . $urgentId, 1, ['priority' => 'urgent']);
    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

    $filtered = $this->getResource($client, $token, '/api/interventions?' . http_build_query([
      'organization' => '/api/organizations/' . $organizationId,
      'priority' => 'urgent',
    ]));
    $ids = $this->memberIds($filtered);
    self::assertContains($urgentId, $ids, 'The urgent intervention must be listed.');
    self::assertNotContains($normalId, $ids, 'Other priorities must be excluded.');

    // An unknown value must be a client error, not a silently empty collection.
    $client->request(
      'GET',
      '/api/interventions?' . http_build_query([
        'organization' => '/api/organizations/' . $organizationId,
        'priority' => 'catastrophic',
      ]),
      server: $this->headers($token, self::LD_JSON),
    );
    self::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
  }

  /**
   * @return array<string, mixed>
   */
  private function createDraftIntervention(
    KernelBrowser $client,
    string $token,
    string $organizationId,
    string $name,
    ?string $description = null,
  ): array {
    $payload = [
      'organization' => '/api/organizations/' . $organizationId,
      'type' => 'inspection_campaign',
      'name' => $name,
    ];
    if (null !== $description) {
      $payload['description'] = $description;
    }
    $client->request(
      method: 'POST',
      uri: '/api/interventions',
      server: $this->headers($token, self::LD_JSON),
      content: json_encode($payload) ?: '',
    );

    self::assertSame(
      Response::HTTP_CREATED,
      $client->getResponse()->getStatusCode(),
      'Draft intervention creation should succeed. Response: ' . ($client->getResponse()->getContent() ?: ''),
    );

    return $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
  }

  /**
   * Reads the optimistic-concurrency revision out of a decoded response
   * payload, defaulting to 0 when absent or non-integer.
   *
   * @param array<string, mixed> $payload
   */
  private static function revisionOf(array $payload): int
  {
    $revision = $payload['revision'] ?? 0;

    return is_int($revision) ? $revision : 0;
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
   * Extracts the resource ids of a Hydra collection's members.
   *
   * @param array<string, mixed> $collection
   *
   * @return list<string>
   */
  private function memberIds(array $collection): array
  {
    $members = $collection['member'] ?? [];
    if (!is_array($members)) {
      return [];
    }
    $ids = [];
    foreach ($members as $member) {
      if (is_array($member)) {
        $id = $this->extractResourceId($member);
        if (is_string($id)) {
          $ids[] = $id;
        }
      }
    }

    return $ids;
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
