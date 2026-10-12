<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Tests\Support\Database\RootCommitDatabaseIsolationTrait;

use function hash;
use function implode;
use function is_numeric;
use function is_string;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Class OrdinaryOwnerIdempotencyFlowTest
 *
 * Exercises real keyed owner routes, encrypted secret replay and the complete message response projection through the HTTP boundary.
 *
 * @category E2E Test
 */
#[SkipDatabaseRollback]
final class OrdinaryOwnerIdempotencyFlowTest extends OAuth2WebTestCase
{
  use RootCommitDatabaseIsolationTrait;

  /**
   * Method testPersonalCalendarTokenReplaysExactlyUntilAnotherRotation
   */
  public function testPersonalCalendarTokenReplaysExactlyUntilAnotherRotation(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->authenticateAsSeededAdmin($client);
    $uri = '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/calendar/feed-token';
    $headers = $this->headers($token, 'calendar-owner-first');
    $client->request('POST', $uri, server: $headers);
    self::assertSame(201, $client->getResponse()->getStatusCode(), $this->failureCode($client));
    $first = $client->getResponse()->getContent() ?: '';
    $created = $this->decodeJsonResponse($first);
    self::assertIsString($created['secret'] ?? null);
    $secret = $created['secret'];
    $client->request('POST', $uri, server: $headers);
    self::assertSame(201, $client->getResponse()->getStatusCode(), $this->failureCode($client));
    self::assertSame('true', $client->getResponse()->headers->get('Idempotency-Replayed'));
    $replayedBody = $client->getResponse()->getContent() ?: '';
    self::assertContains($this->differenceKeys($first, $replayedBody), ['', '@id']);
    $count = $this->main($client)->fetchOne('SELECT COUNT(*) FROM member_calendar_feed_tokens WHERE token_hash = ?', [hash('sha256', $secret)]);
    self::assertTrue(is_numeric($count));
    self::assertSame(1, (int) $count);
    $client->request('POST', $uri, server: $this->headers($token, 'calendar-owner-later'));
    self::assertSame(201, $client->getResponse()->getStatusCode(), $this->failureCode($client));
    $client->request('POST', $uri, server: $headers);
    self::assertSame(403, $client->getResponse()->getStatusCode());
    self::assertStringNotContainsString($secret, $client->getResponse()->getContent() ?: '');
    $this->assertEquivalentBody($first, $replayedBody);
  }

  /**
   * Method testNestedChannelIdentityAndMessageProjectionReplayWithoutSecondWrite
   */
  public function testNestedChannelIdentityAndMessageProjectionReplayWithoutSecondWrite(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->authenticateAsSeededAdmin($client);
    $channelHeaders = $this->headers($token, 'messaging-owner-channel');
    $channelInput = json_encode(['organization' => '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID, 'name' => 'Keyed replay channel'], JSON_THROW_ON_ERROR);
    $client->request('POST', '/api/channels', server: $channelHeaders, content: $channelInput);
    self::assertSame(201, $client->getResponse()->getStatusCode(), $this->failureCode($client));
    $channelBody = $client->getResponse()->getContent() ?: '';
    $channel = $this->decodeJsonResponse($channelBody);
    self::assertIsString($channel['id'] ?? null);
    $channelId = $channel['id'];
    $client->request('POST', '/api/channels', server: $channelHeaders, content: $channelInput);
    self::assertSame(201, $client->getResponse()->getStatusCode(), $this->failureCode($client));
    self::assertSame('true', $client->getResponse()->headers->get('Idempotency-Replayed'));
    $replayedChannelBody = $client->getResponse()->getContent() ?: '';
    self::assertContains($this->differenceKeys($channelBody, $replayedChannelBody), ['', '@id']);
    $uri = '/api/conversations/' . $channelId . '/messages';
    $messageHeaders = $this->headers($token, 'messaging-owner-message');
    $messageInput = json_encode(['body' => '<p>First keyed message</p>'], JSON_THROW_ON_ERROR);
    $client->request('POST', $uri, server: $messageHeaders, content: $messageInput);
    self::assertSame(201, $client->getResponse()->getStatusCode(), $this->failureCode($client));
    $messageBody = $client->getResponse()->getContent() ?: '';
    $client->request('POST', $uri, server: $messageHeaders, content: $messageInput);
    self::assertSame(201, $client->getResponse()->getStatusCode(), $this->failureCode($client));
    self::assertSame('true', $client->getResponse()->headers->get('Idempotency-Replayed'));
    $replayedMessageBody = $client->getResponse()->getContent() ?: '';
    self::assertContains($this->differenceKeys($messageBody, $replayedMessageBody), ['', '@id']);
    $count = $this->main($client)->fetchOne('SELECT COUNT(*) FROM messaging_messages WHERE conversation_id = ?', [$channelId]);
    self::assertTrue(is_numeric($count));
    self::assertSame(1, (int) $count);
    $this->main($client)->delete('messaging_participants', ['conversation_id' => $channelId]);
    $client->request('POST', $uri, server: $messageHeaders, content: $messageInput);
    self::assertSame(403, $client->getResponse()->getStatusCode());
    self::assertStringNotContainsString('First keyed message', $client->getResponse()->getContent() ?: '');
    $this->assertEquivalentBody($channelBody, $replayedChannelBody);
    $this->assertEquivalentBody($messageBody, $replayedMessageBody);
  }

  /**
   * Method headers
   *
   * @param string $token current caller bearer
   * @param string $key logical request key
   *
   * @return array<string, string> negotiated mutation headers
   */
  private function headers(string $token, string $key): array
  {
    return ['HTTP_ACCEPT' => 'application/ld+json', 'CONTENT_TYPE' => 'application/ld+json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'HTTP_IDEMPOTENCY_KEY' => $key];
  }

  /**
   * Method failureCode
   *
   * @param KernelBrowser $client current HTTP client
   *
   * @return string safe protocol error code without raw secret output
   */
  private function failureCode(KernelBrowser $client): string
  {
    $response = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    $code = $response['code'] ?? '';

    return is_string($code) ? $code : '';
  }

  /**
   * Method differenceKeys
   *
   * @param string $first original JSON response
   * @param string $second authorized replay response
   *
   * @return string changed field names without secret or content values
   */
  private function differenceKeys(string $first, string $second): string
  {
    $before = $this->decodeJsonResponse($first);
    $after = $this->decodeJsonResponse($second);
    $keys = [];
    foreach ($before as $key => $value) {
      if (($after[$key] ?? null) !== $value) {
        $keys[] = $key;
      }
    }

    return implode(', ', $keys);
  }

  /**
   * Method assertEquivalentBody
   *
   * Allows only the existing serializer's anonymous JSON-LD identity to vary; every other field, including business UUIDs, is part of the saved semantic snapshot.
   *
   * @param string $first original response
   * @param string $second authorized replay
   */
  private function assertEquivalentBody(string $first, string $second): void
  {
    $before = $this->decodeJsonResponse($first);
    $after = $this->decodeJsonResponse($second);
    if (($before['@id'] ?? null) !== ($after['@id'] ?? null)) {
      self::assertIsString($before['@id'] ?? null);
      self::assertIsString($after['@id'] ?? null);
      self::assertStringContainsString('/.well-known/genid/', $before['@id']);
      self::assertStringContainsString('/.well-known/genid/', $after['@id']);
      unset($before['@id'], $after['@id']);
    }
    self::assertSame(hash('sha256', json_encode($before, JSON_THROW_ON_ERROR)), hash('sha256', json_encode($after, JSON_THROW_ON_ERROR)), $this->differenceKeys($first, $second));
  }

  /**
   * Method main
   *
   * @param KernelBrowser $client current HTTP test client
   *
   * @return Connection explicit main owner database
   */
  private function main(KernelBrowser $client): Connection
  {
    $manager = $client->getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);

    return $manager->getConnection();
  }
}
