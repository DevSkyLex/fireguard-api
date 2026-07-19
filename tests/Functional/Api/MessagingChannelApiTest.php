<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MessagingChannelApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  private const string DUMMY_UUID_2 = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testCreateChannelRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/channels', server: [
      'CONTENT_TYPE' => 'application/json',
    ], content: '{"organization":"/api/organizations/' . self::DUMMY_UUID . '","name":"General"}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'POST /channels endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated POST /channels, got ' . $statusCode);
  }

  #[Test]
  public function testListChannelsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/channels?organization=' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /channels endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated GET /channels, got ' . $statusCode);
  }

  #[Test]
  public function testGetChannelRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/channels/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /channels/{id} endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated GET /channels/{id}, got ' . $statusCode);
  }

  #[Test]
  public function testUpdateChannelRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('PATCH', '/api/channels/' . self::DUMMY_UUID, server: [
      'CONTENT_TYPE' => 'application/merge-patch+json',
    ], content: '{"name":"Announcements"}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'PATCH /channels/{id} endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated PATCH /channels/{id}, got ' . $statusCode);
  }

  #[Test]
  public function testDeleteChannelRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('DELETE', '/api/channels/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'DELETE /channels/{id} endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated DELETE /channels/{id}, got ' . $statusCode);
  }

  #[Test]
  public function testAddChannelParticipantRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/channels/' . self::DUMMY_UUID . '/participants', server: [
      'CONTENT_TYPE' => 'application/json',
    ], content: '{"memberId":"' . self::DUMMY_UUID_2 . '"}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'POST /channels/{id}/participants endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated POST /channels/{id}/participants, got ' . $statusCode);
  }

  #[Test]
  public function testListChannelParticipantsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/channels/' . self::DUMMY_UUID . '/participants');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /channels/{id}/participants endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated GET /channels/{id}/participants, got ' . $statusCode);
  }

  #[Test]
  public function testRemoveChannelParticipantRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('DELETE', '/api/channels/' . self::DUMMY_UUID . '/participants/' . self::DUMMY_UUID_2);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'DELETE /channels/{id}/participants/{memberId} endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated DELETE /channels/{id}/participants/{memberId}, got ' . $statusCode);
  }

  #[Test]
  public function testBindChannelTeamRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('PATCH', '/api/channels/' . self::DUMMY_UUID . '/team', server: [
      'CONTENT_TYPE' => 'application/merge-patch+json',
    ], content: '{"teamId":"' . self::DUMMY_UUID_2 . '"}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'PATCH /channels/{id}/team endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated PATCH /channels/{id}/team, got ' . $statusCode);
  }

  #[Test]
  public function testChannelMessagesReuseTheExistingConversationMessageEndpoints(): void
  {
    $client = static::createClient();

    // A channel id IS a conversation id: posting a channel message reuses
    // POST /api/conversations/{conversationId}/messages verbatim (no new
    // /api/channels/{id}/messages route exists).
    $client->request('POST', '/api/conversations/' . self::DUMMY_UUID . '/messages', server: [
      'CONTENT_TYPE' => 'application/json',
    ], content: '{"body":"Hello channel"}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'POST /conversations/{conversationId}/messages endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated POST /conversations/{conversationId}/messages, got ' . $statusCode);
  }
}
