<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function is_array;
use function is_string;
use function json_encode;
use function uniqid;

/**
 * Test WebhookPresentationFlow.
 *
 * @category E2E Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WebhookPresentationFlowTest extends OAuth2WebTestCase
{
  /**
   * A random, well-formed UUID used only to exercise unauthenticated route
   * guards — the security layer fires before the resource is ever loaded, so
   * the id need not correspond to real data.
   */
  private const string RANDOM_UUID = '00000000-0000-4000-8000-000000000000';

  /**
   * The reference catalog of subscribable event types is exposed to any
   * authenticated user.
   */
  public function testWebhookEventTypesCatalogIsExposedToAuthenticatedUsers(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'webhook-catalog-' . uniqid() . '@example.com';
    $password = 'CatalogPassword123!';

    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);

    $client->request(
      method: 'GET',
      uri: '/api/webhooks/event-types',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Listing webhook event types should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $eventTypes = $this->getCollectionMembers($data);
    $this->assertNotEmpty($eventTypes, 'The event type reference catalog should not be empty.');

    $first = $eventTypes[0];
    $this->assertArrayHasKey('value', $first, 'Each event type row should expose a value.');
    $this->assertArrayHasKey('label', $first, 'Each event type row should expose a label.');
  }

  /**
   * Every webhook Presentation route exists and is guarded — an
   * unauthenticated caller is rejected with 401/403, never 404.
   */
  public function testWebhookEndpointsRequireAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $org = self::RANDOM_UUID;
    $webhook = self::RANDOM_UUID;
    $delivery = self::RANDOM_UUID;

    $guardedRoutes = [
      ['POST', '/api/organizations/' . $org . '/webhooks'],
      ['GET', '/api/organizations/' . $org . '/webhooks'],
      ['GET', '/api/organizations/' . $org . '/webhooks/' . $webhook],
      ['PATCH', '/api/organizations/' . $org . '/webhooks/' . $webhook],
      ['DELETE', '/api/organizations/' . $org . '/webhooks/' . $webhook],
      ['POST', '/api/organizations/' . $org . '/webhooks/' . $webhook . '/rotate-secret'],
      ['POST', '/api/organizations/' . $org . '/webhooks/' . $webhook . '/ping'],
      ['GET', '/api/organizations/' . $org . '/webhooks/' . $webhook . '/deliveries'],
      ['POST', '/api/organizations/' . $org . '/webhooks/' . $webhook . '/deliveries/' . $delivery . '/redeliver'],
      ['GET', '/api/webhooks/event-types'],
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
}
