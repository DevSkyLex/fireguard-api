<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function array_column;
use function basename;
use function is_array;
use function is_string;
use function json_encode;
use function sort;
use function str_contains;
use function uniqid;

/**
 * End-to-end characterization tests for intervention labels (Phase B3).
 *
 * Exercises the real Doctrine label adapter and workflow gateway through the
 * HTTP API: label CRUD, the `(organization, name)` uniqueness conflict,
 * color validation, and assigning/replacing labels on an intervention via
 * `labelIds`, surfaced back as embedded `{id, name, color}` summaries on
 * `InterventionOutput.labels`.
 */
final class InterventionLabelFlowTest extends OAuth2WebTestCase
{
  private const string LD_JSON = 'application/ld+json';

  private const string MERGE_PATCH = 'application/merge-patch+json';

  public function testCreatingAndListingLabelsForAnOrganization(): void
  {
    [$client, $token, $organizationId] = $this->setUpOrganization('crud');

    $label = $this->createLabel($client, $token, $organizationId, 'Urgent', '#ff0000');
    self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
    self::assertSame('Urgent', $label['name'] ?? null);
    self::assertSame('#ff0000', $label['color'] ?? null);
    self::assertSame('/api/organizations/' . $organizationId, $label['organization'] ?? null);

    $collection = $this->getResource($client, $token, '/api/intervention-labels?organization=' . $organizationId);
    $items = $this->members($collection);
    $names = array_column($items, 'name');
    self::assertContains('Urgent', $names);
  }

  public function testDuplicateNameInTheSameOrganizationIsRejectedWithConflict(): void
  {
    [$client, $token, $organizationId] = $this->setUpOrganization('duplicate');

    $this->createLabel($client, $token, $organizationId, 'Urgent', '#ff0000');
    self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

    $this->createLabel($client, $token, $organizationId, 'Urgent', '#00ff00');
    self::assertSame(
      Response::HTTP_CONFLICT,
      $client->getResponse()->getStatusCode(),
      'A duplicate (organization, name) label must be rejected with 409.',
    );
  }

  public function testAnInvalidColorIsRejectedAsUnprocessable(): void
  {
    [$client, $token, $organizationId] = $this->setUpOrganization('invalid-color');

    $this->createLabel($client, $token, $organizationId, 'Broken', 'not-a-color');

    self::assertSame(
      Response::HTTP_UNPROCESSABLE_ENTITY,
      $client->getResponse()->getStatusCode(),
      'An invalid color must be rejected with 422.',
    );
  }

  public function testMissingOrganizationFilterIsRejectedAsBadRequest(): void
  {
    [$client, $token] = $this->setUpOrganization('missing-filter');

    $client->request('GET', '/api/intervention-labels', server: $this->headers($token, self::LD_JSON));

    self::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
  }

  public function testUpdatingALabelPatchesOnlyTheProvidedFields(): void
  {
    [$client, $token, $organizationId] = $this->setUpOrganization('update');

    $label = $this->createLabel($client, $token, $organizationId, 'Draft name', '#ff0000');
    $labelId = $this->extractResourceId($label);
    self::assertNotNull($labelId);

    $client->request(
      method: 'PATCH',
      uri: '/api/intervention-labels/' . $labelId,
      server: $this->headers($token, self::MERGE_PATCH),
      content: json_encode(['color' => '#0000ff']) ?: '',
    );

    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    $updated = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertSame('Draft name', $updated['name'] ?? null, 'A PATCH omitting name must leave it untouched.');
    self::assertSame('#0000ff', $updated['color'] ?? null);
  }

  public function testDeletingALabelRemovesItFromTheCollectionAndFromInterventionOutput(): void
  {
    [$client, $token, $organizationId] = $this->setUpOrganization('delete');

    $label = $this->createLabel($client, $token, $organizationId, 'Removable', '#123456');
    $labelId = $this->extractResourceId($label);
    self::assertNotNull($labelId);

    $intervention = $this->createDraftIntervention($client, $token, $organizationId, 'Labeled mission', [$labelId]);
    $interventionId = $this->extractResourceId($intervention);
    self::assertNotNull($interventionId);
    self::assertSame(['Removable'], $this->labelNames($intervention));

    $client->request(
      method: 'DELETE',
      uri: '/api/intervention-labels/' . $labelId,
      server: $this->headers($token, self::LD_JSON),
    );
    self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

    $collection = $this->getResource($client, $token, '/api/intervention-labels?organization=' . $organizationId);
    self::assertSame([], $this->members($collection));

    $fetched = $this->getResource($client, $token, '/api/interventions/' . $interventionId);
    self::assertSame(
      [],
      $fetched['labels'] ?? null,
      'Deleting a label must remove it from the intervention output without deleting the intervention.',
    );
  }

  public function testAssigningLabelsOnCreateAndReplacingThemOnPatch(): void
  {
    [$client, $token, $organizationId] = $this->setUpOrganization('assign');

    $first = $this->createLabel($client, $token, $organizationId, 'First', '#111111');
    $second = $this->createLabel($client, $token, $organizationId, 'Second', '#222222');
    $firstId = $this->extractResourceId($first);
    $secondId = $this->extractResourceId($second);
    self::assertNotNull($firstId);
    self::assertNotNull($secondId);

    $intervention = $this->createDraftIntervention($client, $token, $organizationId, 'Multi-label mission', [$firstId, $secondId]);
    $interventionId = $this->extractResourceId($intervention);
    self::assertNotNull($interventionId);
    $names = $this->labelNames($intervention);
    sort($names);
    self::assertSame(['First', 'Second'], $names);

    $client->request(
      method: 'PATCH',
      uri: '/api/interventions/' . $interventionId,
      server: $this->headers($token, self::MERGE_PATCH, revision: 1),
      content: json_encode(['labelIds' => [$secondId]]) ?: '',
    );
    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    $patched = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertSame(['Second'], $this->labelNames($patched), 'PATCH labelIds must replace the full set.');
  }

  public function testAssigningALabelFromAnotherOrganizationIsRejectedAsUnprocessable(): void
  {
    // A single session (one browser client, one authenticated owner) that
    // owns two distinct organizations: the label lives under the second
    // organization, and must be rejected when assigned to an intervention
    // scoped to the first, even though the caller has write access to both.
    [$client, $token, $organizationId] = $this->setUpOrganization('cross-org');
    $otherOrganizationId = $this->createOrganization($client, $token, 'Label Org cross-org-other ' . uniqid());
    self::assertNotNull($otherOrganizationId);

    $foreignLabel = $this->createLabel($client, $token, $otherOrganizationId, 'Foreign label', '#654321');
    self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
    $foreignLabelId = $this->extractResourceId($foreignLabel);
    self::assertNotNull($foreignLabelId);

    $client->request(
      method: 'POST',
      uri: '/api/interventions',
      server: $this->headers($token, self::LD_JSON),
      content: json_encode([
        'organization' => '/api/organizations/' . $organizationId,
        'type' => 'inspection_campaign',
        'name' => 'Cross-org attempt',
        'labelIds' => [$foreignLabelId],
      ]) ?: '',
    );

    self::assertSame(
      Response::HTTP_UNPROCESSABLE_ENTITY,
      $client->getResponse()->getStatusCode(),
      'Assigning a label from another organization must be rejected with 422.',
    );
  }

  /**
   * @return array{0: KernelBrowser, 1: string, 2: string}
   */
  private function setUpOrganization(string $suffix): array
  {
    $client = static::createClientWithFixtures();
    $email = 'intervention-label-' . $suffix . '-' . uniqid() . '@example.com';
    $password = 'OwnerPassword123!';
    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);
    $organizationId = $this->createOrganization($client, $token, 'Label Org ' . $suffix . ' ' . uniqid());
    self::assertNotNull($organizationId);

    return [$client, $token, $organizationId];
  }

  /**
   * @return array<string, mixed>
   */
  private function createLabel(KernelBrowser $client, string $token, string $organizationId, string $name, string $color): array
  {
    $client->request(
      method: 'POST',
      uri: '/api/intervention-labels',
      server: $this->headers($token, self::LD_JSON),
      content: json_encode([
        'organization' => '/api/organizations/' . $organizationId,
        'name' => $name,
        'color' => $color,
      ]) ?: '',
    );

    return $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
  }

  /**
   * @param list<string> $labelIds
   *
   * @return array<string, mixed>
   */
  private function createDraftIntervention(
    KernelBrowser $client,
    string $token,
    string $organizationId,
    string $name,
    array $labelIds = [],
  ): array {
    $client->request(
      method: 'POST',
      uri: '/api/interventions',
      server: $this->headers($token, self::LD_JSON),
      content: json_encode([
        'organization' => '/api/organizations/' . $organizationId,
        'type' => 'inspection_campaign',
        'name' => $name,
        'labelIds' => $labelIds,
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

  /**
   * Extracts the `name` field of each embedded `InterventionOutput.labels`
   * entry as a plain string list.
   *
   * @param array<string, mixed> $intervention
   *
   * @return list<string>
   */
  private function labelNames(array $intervention): array
  {
    $labels = $intervention['labels'] ?? [];
    if (!is_array($labels)) {
      return [];
    }

    $names = [];
    foreach ($labels as $label) {
      if (is_array($label) && is_string($label['name'] ?? null)) {
        $names[] = $label['name'];
      }
    }

    return $names;
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
  private function headers(string $token, string $contentType, ?int $revision = null): array
  {
    $headers = [
      'CONTENT_TYPE' => $contentType,
      'HTTP_ACCEPT' => self::LD_JSON,
      'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
    ];
    if (null !== $revision) {
      $headers['HTTP_IF_MATCH'] = '"revision-' . $revision . '"';
    }

    return $headers;
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
