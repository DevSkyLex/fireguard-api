<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function basename;
use function is_array;
use function is_string;
use function json_encode;
use function str_contains;
use function uniqid;

/**
 * Test WebhookWriteFlow.
 *
 * Drives the authenticated write/read HTTP surface of the Webhook module end
 * to end (create, list, update, rotate secret, ping, list deliveries,
 * redeliver, delete) so every Presentation processor and provider behind those
 * operations is exercised, plus per-route unauthenticated guard checks.
 *
 * @category E2E Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WebhookWriteFlowTest extends OAuth2WebTestCase
{
  /**
   * The seeded organization owner. Its admin role carries `organization.*`, so
   * it satisfies both `organization.webhooks.read` and
   * `organization.webhooks.manage`.
   */
  private const string ADMIN_EMAIL = 'admin@fireguard.local';

  private const string ADMIN_PASSWORD = 'Admin123!';

  /**
   * A well-formed but non-existent UUID: the security layer rejects an
   * unauthenticated caller before the resource is ever loaded, so the ids need
   * not correspond to real rows for the route-guard assertions.
   */
  private const string RANDOM_UUID = '00000000-0000-4000-8000-000000000000';

  /**
   * Full authenticated lifecycle. Each step routes to a distinct Presentation
   * processor/provider; the subscription is deleted last so every earlier step
   * has a live subscription (and, for redelivery, a real delivery row) to act
   * on.
   */
  public function testAuthenticatedWebhookWriteFlowCoversProcessorsAndProviders(): void
  {
    $client = static::createClientWithFixtures();

    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);
    $organizationId = OrganizationFixtures::ORGANIZATION_ID;

    // Step 1: create a subscription. Response reveals the plaintext secret once.
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/webhooks',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'url' => 'https://webhooks.example.com/fireguard-' . uniqid(),
        'eventTypes' => ['inspection.submitted', 'equipment.commissioned'],
        'description' => 'Write-flow subscription',
      ]) ?: '',
    );

    $createResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_CREATED,
      $createResponse->getStatusCode(),
      'Creating a webhook subscription should succeed. Response: ' . $createResponse->getContent(),
    );

    $createData = $this->decodeJsonResponse($createResponse->getContent() ?: '{}');
    $webhookId = $this->extractResourceId($createData);
    $this->assertTrue(is_string($webhookId) && '' !== $webhookId, 'Create response should expose the subscription id.');
    $firstSecret = $createData['secret'] ?? null;
    $this->assertTrue(is_string($firstSecret) && '' !== $firstSecret, 'Create response should reveal the plaintext secret once.');

    // Step 2: list subscriptions -> ListWebhookSubscriptionsProvider.
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/webhooks',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $listResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $listResponse->getStatusCode(),
      'Listing webhook subscriptions should succeed. Response: ' . $listResponse->getContent(),
    );
    $subscriptions = $this->getCollectionMembers($this->decodeJsonResponse($listResponse->getContent() ?: '{}'));
    $this->assertTrue($this->collectionContainsId($subscriptions, $webhookId), 'The created subscription should appear in the list.');

    // Step 3: partial update -> UpdateWebhookSubscriptionProcessor.
    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . $organizationId . '/webhooks/' . $webhookId,
      server: [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'description' => 'Write-flow subscription (updated)',
        'isActive' => false,
      ]) ?: '',
    );

    $updateResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $updateResponse->getStatusCode(),
      'Updating a webhook subscription should succeed. Response: ' . $updateResponse->getContent(),
    );
    $updateData = $this->decodeJsonResponse($updateResponse->getContent() ?: '{}');
    $this->assertSame('Write-flow subscription (updated)', $updateData['description'] ?? null, 'The description should be updated.');
    $this->assertSame(false, $updateData['isActive'] ?? null, 'The subscription should be deactivated.');

    // Step 4: rotate the signing secret -> RotateWebhookSecretProcessor.
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/webhooks/' . $webhookId . '/rotate-secret',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: '{}',
    );

    $rotateResponse = $client->getResponse();
    $this->assertContains(
      $rotateResponse->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Rotating the webhook secret should succeed. Response: ' . $rotateResponse->getContent(),
    );
    $rotateData = $this->decodeJsonResponse($rotateResponse->getContent() ?: '{}');
    $rotatedSecret = $rotateData['secret'] ?? null;
    $this->assertTrue(is_string($rotatedSecret) && '' !== $rotatedSecret, 'Rotation should reveal a new plaintext secret.');
    $this->assertNotSame($firstSecret, $rotatedSecret, 'The rotated secret should differ from the original.');

    // Step 5: ping the subscription -> PingWebhookSubscriptionProcessor. This
    // reserves a persisted delivery row (the webhook transport is in-memory in
    // test, so nothing is actually POSTed out).
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/webhooks/' . $webhookId . '/ping',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: '{}',
    );

    $pingResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_ACCEPTED,
      $pingResponse->getStatusCode(),
      'Pinging a webhook subscription should be accepted. Response: ' . $pingResponse->getContent(),
    );
    $pingData = $this->decodeJsonResponse($pingResponse->getContent() ?: '{}');
    $deliveryId = $pingData['deliveryId'] ?? null;
    $this->assertTrue(is_string($deliveryId) && '' !== $deliveryId, 'Ping response should expose the enqueued deliveryId.');
    $this->assertSame('queued', $pingData['status'] ?? null, 'Ping response should report a queued delivery.');

    // Step 6: list deliveries -> ListWebhookDeliveriesProvider.
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/webhooks/' . $webhookId . '/deliveries',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $deliveriesResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $deliveriesResponse->getStatusCode(),
      'Listing webhook deliveries should succeed. Response: ' . $deliveriesResponse->getContent(),
    );
    $deliveries = $this->getCollectionMembers($this->decodeJsonResponse($deliveriesResponse->getContent() ?: '{}'));
    $this->assertNotEmpty($deliveries, 'The ping should have produced a delivery in the log.');
    $this->assertTrue($this->collectionContainsId($deliveries, $deliveryId), 'The enqueued delivery should appear in the delivery log.');

    // Step 7: redeliver the delivery -> RedeliverWebhookProcessor.
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/webhooks/' . $webhookId . '/deliveries/' . $deliveryId . '/redeliver',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: '{}',
    );

    $redeliverResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_ACCEPTED,
      $redeliverResponse->getStatusCode(),
      'Redelivering a webhook delivery should be accepted. Response: ' . $redeliverResponse->getContent(),
    );
    $redeliverData = $this->decodeJsonResponse($redeliverResponse->getContent() ?: '{}');
    $this->assertSame($deliveryId, $redeliverData['deliveryId'] ?? null, 'Redelivery should reference the same delivery.');
    $this->assertSame('queued', $redeliverData['status'] ?? null, 'Redelivery response should report a queued delivery.');

    // Step 8: delete the subscription -> DeleteWebhookSubscriptionProcessor.
    $client->request(
      method: 'DELETE',
      uri: '/api/organizations/' . $organizationId . '/webhooks/' . $webhookId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $deleteResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_NO_CONTENT,
      $deleteResponse->getStatusCode(),
      'Deleting a webhook subscription should succeed. Response: ' . $deleteResponse->getContent(),
    );
  }

  /**
   * Each write/read route routing to a target processor/provider exists and is
   * guarded: an unauthenticated caller is rejected with 401/403, never 404.
   */
  public function testWebhookWriteRoutesRejectUnauthenticatedCallers(): void
  {
    $client = static::createClientWithFixtures();

    $org = self::RANDOM_UUID;
    $webhook = self::RANDOM_UUID;
    $delivery = self::RANDOM_UUID;

    $guardedRoutes = [
      ['GET', '/api/organizations/' . $org . '/webhooks'],
      ['PATCH', '/api/organizations/' . $org . '/webhooks/' . $webhook],
      ['DELETE', '/api/organizations/' . $org . '/webhooks/' . $webhook],
      ['POST', '/api/organizations/' . $org . '/webhooks/' . $webhook . '/rotate-secret'],
      ['POST', '/api/organizations/' . $org . '/webhooks/' . $webhook . '/ping'],
      ['GET', '/api/organizations/' . $org . '/webhooks/' . $webhook . '/deliveries'],
      ['POST', '/api/organizations/' . $org . '/webhooks/' . $webhook . '/deliveries/' . $delivery . '/redeliver'],
    ];

    foreach ($guardedRoutes as [$method, $uri]) {
      $contentType = 'PATCH' === $method ? 'application/merge-patch+json' : 'application/ld+json';

      $client->request(
        method: $method,
        uri: $uri,
        server: [
          'CONTENT_TYPE' => $contentType,
          'HTTP_ACCEPT' => 'application/ld+json',
        ],
        content: '{}',
      );

      $response = $client->getResponse();

      $this->assertNotSame(
        Response::HTTP_NOT_FOUND,
        $response->getStatusCode(),
        "Route {$method} {$uri} should exist. Response: " . $response->getContent(),
      );
      $this->assertContains(
        $response->getStatusCode(),
        [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
        "Route {$method} {$uri} should require authentication. Response: " . $response->getContent(),
      );
    }
  }

  private function loginAndGetUserAccessToken(KernelBrowser $client, string $email, string $password): string
  {
    $client->request(
      method: 'POST',
      uri: '/api/auth/login',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'email' => $email,
        'password' => $password,
      ]) ?: '',
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'User login should succeed for E2E flow. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $token = $data['access_token'] ?? null;

    $this->assertTrue(is_string($token) && '' !== $token, 'Login response should contain access_token.');

    return $token;
  }

  /**
   * @param array<string, mixed> $data
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

  /**
   * @param array<string, mixed> $data
   *
   * @return list<array<string, mixed>>
   */
  private function getCollectionMembers(array $data): array
  {
    $members = $data['member'] ?? [];

    if (!is_array($members)) {
      return [];
    }

    $result = [];
    foreach ($members as $member) {
      if (!is_array($member)) {
        continue;
      }

      $normalized = [];
      foreach ($member as $key => $value) {
        if (is_string($key)) {
          $normalized[$key] = $value;
        }
      }

      $result[] = $normalized;
    }

    return $result;
  }

  /**
   * @param list<array<string, mixed>> $collection
   */
  private function collectionContainsId(array $collection, string $id): bool
  {
    foreach ($collection as $item) {
      if ($this->extractResourceId($item) === $id) {
        return true;
      }
    }

    return false;
  }
}
