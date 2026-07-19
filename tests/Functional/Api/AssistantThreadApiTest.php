<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AssistantThreadApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  private const string DUMMY_UUID_2 = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testListAssistantThreadsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/assistant/threads');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /organizations/{organizationId}/assistant/threads endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }

  #[Test]
  public function testStartAssistantThreadRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/organizations/' . self::DUMMY_UUID . '/assistant/threads', server: [
      'CONTENT_TYPE' => 'application/json',
    ], content: '{}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'POST /organizations/{organizationId}/assistant/threads endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }

  #[Test]
  public function testGetAssistantThreadRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/assistant/threads/' . self::DUMMY_UUID_2);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /organizations/{organizationId}/assistant/threads/{threadId} endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }

  #[Test]
  public function testAskAssistantQuestionRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/organizations/' . self::DUMMY_UUID . '/assistant/threads/' . self::DUMMY_UUID_2 . '/messages', server: [
      'CONTENT_TYPE' => 'application/json',
    ], content: '{}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'POST .../messages endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }

  #[Test]
  public function testGetAssistantThreadSubscriptionRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/assistant/threads/' . self::DUMMY_UUID_2 . '/subscription');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET .../subscription endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }
}
