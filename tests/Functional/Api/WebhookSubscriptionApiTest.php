<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WebhookSubscriptionApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  private const string DUMMY_UUID_2 = '550e8400-e29b-41d4-a716-446655440001';

  private const string DUMMY_UUID_3 = '550e8400-e29b-41d4-a716-446655440002';

  #[Test]
  public function testCreateWebhookSubscriptionRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/organizations/' . self::DUMMY_UUID . '/webhooks', server: [
      'CONTENT_TYPE' => 'application/json',
    ], content: '{"url":"https://example.com/hook","eventTypes":["intervention.published"]}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'POST /organizations/{organizationId}/webhooks endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /organizations/{organizationId}/webhooks, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListWebhookSubscriptionsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/webhooks');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /organizations/{organizationId}/webhooks endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }

  #[Test]
  public function testGetWebhookSubscriptionRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/webhooks/' . self::DUMMY_UUID_2);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /organizations/{organizationId}/webhooks/{webhookId} endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }

  #[Test]
  public function testUpdateWebhookSubscriptionRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('PATCH', '/api/organizations/' . self::DUMMY_UUID . '/webhooks/' . self::DUMMY_UUID_2, server: [
      'CONTENT_TYPE' => 'application/merge-patch+json',
    ], content: '{"isActive":false}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'PATCH /organizations/{organizationId}/webhooks/{webhookId} endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }

  #[Test]
  public function testDeleteWebhookSubscriptionRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('DELETE', '/api/organizations/' . self::DUMMY_UUID . '/webhooks/' . self::DUMMY_UUID_2);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'DELETE /organizations/{organizationId}/webhooks/{webhookId} endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }

  #[Test]
  public function testRotateWebhookSecretRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/organizations/' . self::DUMMY_UUID . '/webhooks/' . self::DUMMY_UUID_2 . '/rotate-secret');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'POST .../rotate-secret endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }

  #[Test]
  public function testPingWebhookSubscriptionRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/organizations/' . self::DUMMY_UUID . '/webhooks/' . self::DUMMY_UUID_2 . '/ping');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'POST .../ping endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }

  #[Test]
  public function testListWebhookDeliveriesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/webhooks/' . self::DUMMY_UUID_2 . '/deliveries');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET .../deliveries endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }

  #[Test]
  public function testRedeliverWebhookRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/organizations/' . self::DUMMY_UUID . '/webhooks/' . self::DUMMY_UUID_2 . '/deliveries/' . self::DUMMY_UUID_3 . '/redeliver');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'POST .../redeliver endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }

  #[Test]
  public function testListWebhookEventTypesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/webhooks/event-types');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /webhooks/event-types endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }
}
