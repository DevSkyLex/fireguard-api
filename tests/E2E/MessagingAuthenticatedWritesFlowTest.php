<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Application\Port\Outbound\MessagingRealtimePublisherPort;
use Messaging\Domain\Event\Channel\MessagingChannelCreatedEvent;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function array_column;
use function array_filter;
use function array_values;
use function file_exists;
use function file_put_contents;
use function is_array;
use function is_numeric;
use function json_encode;
use function strlen;
use function sys_get_temp_dir;
use function tempnam;
use function uniqid;
use function unlink;

use const JSON_THROW_ON_ERROR;
use const UPLOAD_ERR_OK;

/**
 * Class MessagingAuthenticatedWritesFlowTest.
 *
 * Exercises messaging processors with real login tokens and isolated PostgreSQL writes.
 * The realtime port records emissions; actual Mercure delivery belongs to the live browser smoke.
 *
 * @category E2E Tests
 */
final class MessagingAuthenticatedWritesFlowTest extends OAuth2WebTestCase
{
  // #region Methods
  /**
   * Method authenticatedParticipantsCanCompleteTheMessageLifecycle.
   *
   * Proves persistence, participant identity, reply separation, multipart storage and receipts.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function authenticatedParticipantsCanCompleteTheMessageLifecycle(): void
  {
    $client = static::createClientWithFixtures();
    $client->disableReboot();
    $emissions = [];
    $this->recordRealtime($client, $emissions);
    $createdEvents = [];
    /** @var EventDispatcherInterface $dispatcher */
    $dispatcher = static::getContainer()->get('event_dispatcher');
    $dispatcher->addListener('messaging.messaging_channel_created_event', static function (MessagingChannelCreatedEvent $event) use (&$createdEvents): void {
      $createdEvents[] = $event;
    });

    $owner = $this->authenticateFreshUser($client);
    $reader = $this->authenticateFreshUser($client);
    $organization = $this->requestJson($client, $owner['token'], 'POST', '/api/organizations', 201, ['name' => 'Messaging HTTP ' . uniqid()]);
    $organizationId = $this->id($organization);
    $writerRoleId = $this->id($this->requestJson($client, $owner['token'], 'POST', '/api/organizations/' . $organizationId . '/roles', 201, ['name' => 'messaging_writer', 'permissions' => ['organization.messaging.read', 'organization.messaging.write']]));
    $readerMember = $this->requestJson($client, $owner['token'], 'POST', '/api/organizations/' . $organizationId . '/members', 201, ['userId' => $reader['userId'], 'roleIds' => [$writerRoleId]]);
    $readerMemberId = $this->id($readerMember);
    $channel = $this->requestJson($client, $owner['token'], 'POST', '/api/channels', 201, ['organization' => '/api/organizations/' . $organizationId, 'name' => 'HTTP lifecycle']);
    $channelId = $this->id($channel);
    self::assertSame('/api/organizations/' . $organizationId, $channel['organization'] ?? null);
    self::assertSame(1, $channel['participantCount'] ?? null);
    self::assertSame(0, $channel['messagesCount'] ?? null);
    self::assertCount(1, $createdEvents);
    self::assertSame($channelId, $createdEvents[0]->conversationId);
    self::assertSame($organizationId, $createdEvents[0]->organizationId);
    self::assertSame($owner['userId'], $createdEvents[0]->actorUserId);
    $ownerMemberId = $createdEvents[0]->createdByMemberId;
    self::assertSame('/api/organizations/' . $organizationId . '/members/' . $ownerMemberId, $channel['createdByMember'] ?? null);

    $participant = $this->requestJson($client, $owner['token'], 'POST', '/api/channels/' . $channelId . '/participants', 201, ['memberId' => $readerMemberId, 'role' => 'reviewer']);
    self::assertSame($readerMemberId, $participant['memberId'] ?? null);
    self::assertSame('manual', $participant['source'] ?? null);
    self::assertSame('reviewer', $participant['role'] ?? null);
    $participants = $this->requestJson($client, $owner['token'], 'GET', '/api/channels/' . $channelId . '/participants', 200);
    self::assertEqualsCanonicalizing([$ownerMemberId, $readerMemberId], array_column($this->members($participants), 'memberId'));
    self::assertSame(2, $this->number($this->connection($client)->fetchOne('SELECT COUNT(*) FROM messaging_participants WHERE conversation_id = ?', [$channelId])));

    $message = $this->requestJson($client, $owner['token'], 'POST', '/api/conversations/' . $channelId . '/messages', 201, ['body' => '<p>Original message</p>']);
    $messageId = $this->id($message);
    self::assertSame('/api/conversations/' . $channelId, $message['conversation'] ?? null);
    self::assertSame('/api/organizations/' . $organizationId . '/members/' . $ownerMemberId, $message['authorMember'] ?? null);
    self::assertSame('<p>Original message</p>', $message['body'] ?? null);
    self::assertSame(false, $message['isDeleted'] ?? null);
    self::assertSame('<p>Original message</p>', $this->connection($client)->fetchOne('SELECT body FROM messaging_messages WHERE id = ?', [$messageId]));

    $edited = $this->requestJson($client, $owner['token'], 'PATCH', '/api/messages/' . $messageId, 200, ['body' => '<p>Edited message</p>']);
    self::assertSame('<p>Edited message</p>', $edited['body'] ?? null);
    self::assertIsString($edited['editedAt'] ?? null);
    $reply = $this->requestJson($client, $reader['token'], 'POST', '/api/messages/' . $messageId . '/replies', 201, ['body' => '<p>Participant reply</p>']);
    $replyId = $this->id($reply);
    self::assertSame('/api/organizations/' . $organizationId . '/members/' . $readerMemberId, $reply['authorMember'] ?? null);
    self::assertSame($messageId, $this->connection($client)->fetchOne('SELECT parent_message_id FROM messaging_messages WHERE id = ?', [$replyId]));
    self::assertSame(1, $this->number($this->connection($client)->fetchOne('SELECT reply_count FROM messaging_messages WHERE id = ?', [$messageId])));
    $roots = $this->requestJson($client, $reader['token'], 'GET', '/api/conversations/' . $channelId . '/messages', 200);
    self::assertSame([$messageId], array_column($this->members($roots), 'id'));
    self::assertSame(1, $this->members($roots)[0]['replyCount'] ?? null);
    $replies = $this->requestJson($client, $owner['token'], 'GET', '/api/messages/' . $messageId . '/replies', 200);
    self::assertSame([$replyId], array_column($this->members($replies), 'id'));
    self::assertSame(2, $this->number($this->connection($client)->fetchOne('SELECT messages_count FROM messaging_conversations WHERE id = ?', [$channelId])));

    $contents = "%PDF-1.4\nMessaging HTTP attachment\n%%EOF\n";
    $attachment = $this->upload($client, $owner['token'], $messageId, $contents, 201);
    $attachmentId = $this->id($attachment);
    self::assertSame('messaging-flow.pdf', $attachment['fileName'] ?? null);
    self::assertSame('application/pdf', $attachment['mimeType'] ?? null);
    self::assertSame(strlen($contents), $attachment['size'] ?? null);
    self::assertSame('/api/messages/' . $messageId, $attachment['message'] ?? null);
    self::assertSame('/api/messaging-attachments/' . $attachmentId . '/content', $attachment['contentUrl'] ?? null);
    self::assertArrayNotHasKey('storagePath', $attachment);
    $client->request('GET', $attachment['contentUrl'], server: $this->headers($reader['token']));
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertSame($contents, $client->getResponse()->getContent());
    self::assertStringContainsString('attachment', $client->getResponse()->headers->get('Content-Disposition') ?? '');
    $files = $this->requestJson($client, $reader['token'], 'GET', '/api/conversations/' . $channelId . '/attachments', 200);
    self::assertSame([$attachmentId], array_column($this->members($files), 'id'));

    $this->requestJson($client, $reader['token'], 'POST', '/api/conversations/' . $channelId . '/delivery', 200, ['messageId' => $messageId]);
    $this->requestJson($client, $reader['token'], 'PATCH', '/api/conversations/' . $channelId . '/read', 200, ['lastReadMessageId' => $messageId]);
    $marker = $this->connection($client)->fetchAssociative('SELECT organization_id, last_read_message_id, last_delivered_message_id FROM messaging_read_markers WHERE conversation_id = ? AND member_id = ?', [$channelId, $readerMemberId]);
    self::assertSame(['organization_id' => $organizationId, 'last_read_message_id' => $messageId, 'last_delivered_message_id' => $messageId], $marker);
    $receipts = $this->requestJson($client, $owner['token'], 'GET', '/api/conversations/' . $channelId . '/receipts', 200);
    self::assertIsArray($receipts['receipts'] ?? null);
    $readerReceipt = array_values(array_filter($receipts['receipts'], static fn (mixed $receipt): bool => is_array($receipt) && $readerMemberId === ($receipt['memberId'] ?? null)));
    self::assertCount(1, $readerReceipt);
    self::assertSame($messageId, $readerReceipt[0]['readMessageId'] ?? null);
    self::assertSame($messageId, $readerReceipt[0]['deliveredMessageId'] ?? null);

    $client->request('DELETE', '/api/messaging-attachments/' . $attachmentId, server: $this->headers($owner['token']) + ['HTTP_IF_MATCH' => '"revision-1"']);
    self::assertSame(204, $client->getResponse()->getStatusCode());
    self::assertSame(0, $this->number($this->connection($client)->fetchOne('SELECT COUNT(*) FROM messaging_attachments WHERE id = ?', [$attachmentId])));
    $this->requestJson($client, $owner['token'], 'DELETE', '/api/messages/' . $messageId, 204);
    $deletedRoots = $this->requestJson($client, $reader['token'], 'GET', '/api/conversations/' . $channelId . '/messages', 200);
    $tombstone = $this->members($deletedRoots)[0];
    self::assertSame($messageId, $tombstone['id'] ?? null);
    self::assertSame(true, $tombstone['isDeleted'] ?? null);
    self::assertNull($tombstone['body'] ?? null);
    self::assertSame([], $tombstone['mentions'] ?? null);
    self::assertSame([], $tombstone['attachments'] ?? null);
    self::assertSame(1, $tombstone['replyCount'] ?? null);
    $retained = $this->connection($client)->fetchAssociative('SELECT body, deleted_by_member_id, deleted_at FROM messaging_messages WHERE id = ?', [$messageId]);
    self::assertIsArray($retained);
    self::assertSame('<p>Edited message</p>', $retained['body']);
    self::assertSame($ownerMemberId, $retained['deleted_by_member_id']);
    self::assertIsString($retained['deleted_at']);
    self::assertSame(['message.created', 'message.updated', 'message.created', 'receipt.changed', 'receipt.changed', 'message.deleted'], array_column(array_column($emissions, 'payload'), 'type'));
    foreach ($emissions as $emission) {
      self::assertSame($organizationId, $emission['organizationId']);
      self::assertSame($channelId, $emission['conversationId']);
      self::assertTrue($emission['persisted']);
    }
    self::assertSame($messageId, $emissions[0]['payload']['messageId'] ?? null);
    self::assertSame($messageId, $emissions[2]['payload']['parentMessageId'] ?? null);
    self::assertSame($replyId, $emissions[2]['payload']['messageId'] ?? null);
  }

  /**
   * Method outsidersAndNonparticipantsCannotReadOrMutateTheChannel.
   *
   * Proves denial paths leave content, counters, markers and publications unchanged.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function outsidersAndNonparticipantsCannotReadOrMutateTheChannel(): void
  {
    $client = static::createClientWithFixtures();
    $client->disableReboot();
    $emissions = [];
    $this->recordRealtime($client, $emissions);
    $ownerToken = $this->authenticateAsSeededAdmin($client);
    $nonparticipant = $this->authenticateFreshUser($client);
    $outsider = $this->authenticateFreshUser($client);
    $organizationId = $this->id($this->requestJson($client, $ownerToken, 'POST', '/api/organizations', 201, ['name' => 'Messaging denial ' . uniqid()]));
    $roleId = $this->id($this->requestJson($client, $ownerToken, 'POST', '/api/organizations/' . $organizationId . '/roles', 201, ['name' => 'messaging_writer', 'permissions' => ['organization.messaging.read', 'organization.messaging.write']]));
    $this->requestJson($client, $ownerToken, 'POST', '/api/organizations/' . $organizationId . '/members', 201, ['userId' => $nonparticipant['userId'], 'roleIds' => [$roleId]]);
    $foreignOrganizationId = $this->id($this->requestJson($client, $outsider['token'], 'POST', '/api/organizations', 201, ['name' => 'Foreign messaging ' . uniqid()]));
    $channelId = $this->id($this->requestJson($client, $ownerToken, 'POST', '/api/channels', 201, ['organization' => '/api/organizations/' . $organizationId, 'name' => 'Private channel']));
    $messageId = $this->id($this->requestJson($client, $ownerToken, 'POST', '/api/conversations/' . $channelId . '/messages', 201, ['body' => '<p>Private content</p>']));
    $attachment = $this->upload($client, $ownerToken, $messageId, "%PDF-1.4\nPrivate evidence\n%%EOF\n", 201);
    $attachmentId = $this->id($attachment);
    self::assertCount(1, $emissions);

    foreach ([[$nonparticipant['token'], 403], [$outsider['token'], 404]] as [$token, $status]) {
      foreach (['/api/channels/' . $channelId, '/api/conversations/' . $channelId . '/messages', '/api/conversations/' . $channelId . '/receipts', '/api/messages/' . $messageId . '/replies', '/api/conversations/' . $channelId . '/attachments', '/api/messaging-attachments/' . $attachmentId . '/content'] as $path) {
        $this->requestJson($client, $token, 'GET', $path, $status);
      }
      $this->requestJson($client, $token, 'POST', '/api/conversations/' . $channelId . '/messages', $status, ['body' => '<p>Forbidden post</p>']);
      $this->requestJson($client, $token, 'PATCH', '/api/messages/' . $messageId, $status, ['body' => '<p>Forbidden edit</p>']);
      $this->requestJson($client, $token, 'DELETE', '/api/messages/' . $messageId, $status);
      $this->requestJson($client, $token, 'POST', '/api/messages/' . $messageId . '/replies', $status, ['body' => '<p>Forbidden reply</p>']);
      $this->requestJson($client, $token, 'PATCH', '/api/conversations/' . $channelId . '/read', $status, ['lastReadMessageId' => $messageId]);
      $this->requestJson($client, $token, 'POST', '/api/conversations/' . $channelId . '/delivery', $status, ['messageId' => $messageId]);
      $this->upload($client, $token, $messageId, "%PDF-1.4\nForbidden\n%%EOF\n", $status);
    }

    $foreignMemberId = $this->connection($client)->fetchOne('SELECT id FROM organization_members WHERE organization_id = ? AND user_id = ?', [$foreignOrganizationId, $outsider['userId']]);
    self::assertIsString($foreignMemberId);
    $this->requestJson($client, $ownerToken, 'POST', '/api/channels/' . $channelId . '/participants', 422, ['memberId' => $foreignMemberId]);
    self::assertCount(1, $emissions);
    self::assertSame('<p>Private content</p>', $this->connection($client)->fetchOne('SELECT body FROM messaging_messages WHERE id = ?', [$messageId]));
    self::assertSame(1, $this->number($this->connection($client)->fetchOne('SELECT COUNT(*) FROM messaging_messages WHERE conversation_id = ?', [$channelId])));
    self::assertSame(1, $this->number($this->connection($client)->fetchOne('SELECT messages_count FROM messaging_conversations WHERE id = ?', [$channelId])));
    self::assertSame(1, $this->number($this->connection($client)->fetchOne('SELECT COUNT(*) FROM messaging_participants WHERE conversation_id = ?', [$channelId])));
    self::assertSame(1, $this->number($this->connection($client)->fetchOne('SELECT COUNT(*) FROM messaging_attachments WHERE conversation_id = ?', [$channelId])));
    self::assertSame(0, $this->number($this->connection($client)->fetchOne('SELECT COUNT(*) FROM messaging_read_markers WHERE conversation_id = ? AND last_read_message_id IS NOT NULL', [$channelId])));
    self::assertNull($this->connection($client)->fetchOne('SELECT deleted_at FROM messaging_messages WHERE id = ?', [$messageId]));
    $client->request('DELETE', '/api/messaging-attachments/' . $attachmentId, server: $this->headers($ownerToken) + ['HTTP_IF_MATCH' => '"revision-1"']);
    self::assertSame(204, $client->getResponse()->getStatusCode());
  }

  /**
   * Method recordRealtime.
   *
   * Records outbound payloads and the presence of their durable rows when emitted.
   *
   * @access private
   *
   * @param KernelBrowser $client the authenticated HTTP client
   * @param list<array{organizationId:string, conversationId:string, payload:array<string, mixed>, persisted:bool}> $emissions the recording sink
   *
   * @return void
   */
  private function recordRealtime(KernelBrowser $client, array &$emissions): void
  {
    $publisher = $this->createStub(MessagingRealtimePublisherPort::class);
    $publisher->method('publishMessage')->willReturnCallback(function (string $organizationId, string $conversationId, array $payload) use ($client, &$emissions): void {
      $persisted = isset($payload['messageId'])
        ? 1 === $this->number($this->connection($client)->fetchOne('SELECT COUNT(*) FROM messaging_messages WHERE id = ? AND conversation_id = ? AND organization_id = ?', [$payload['messageId'], $conversationId, $organizationId]))
        : 1 === $this->number($this->connection($client)->fetchOne('SELECT COUNT(*) FROM messaging_read_markers WHERE conversation_id = ? AND member_id = ? AND organization_id = ?', [$conversationId, $payload['memberId'] ?? '', $organizationId]));
      $emissions[] = ['organizationId' => $organizationId, 'conversationId' => $conversationId, 'payload' => $payload, 'persisted' => $persisted];
    });
    static::getContainer()->set(MessagingRealtimePublisherPort::class, $publisher);
  }

  /**
   * Method requestJson.
   *
   * Sends an HTTP operation with a real bearer token and asserts its exact status.
   *
   * @access private
   *
   * @param KernelBrowser $client the HTTP client
   * @param string $token the access token
   * @param string $method the HTTP method
   * @param string $path the API route
   * @param int $status the expected HTTP status
   * @param array<string, mixed>|null $payload the request body
   *
   * @return array<string, mixed> the decoded response
   */
  private function requestJson(KernelBrowser $client, string $token, string $method, string $path, int $status, ?array $payload = null): array
  {
    $client->request($method, $path, server: $this->headers($token, 'PATCH' === $method), content: null === $payload ? null : json_encode($payload, JSON_THROW_ON_ERROR));
    self::assertSame($status, $client->getResponse()->getStatusCode(), $method . ' ' . $path . ': ' . $client->getResponse()->getContent());

    return $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
  }

  /**
   * Method upload.
   *
   * Sends real multipart bytes through the attachment processor and removes its input file.
   *
   * @access private
   *
   * @param KernelBrowser $client the HTTP client
   * @param string $token the access token
   * @param string $messageId the owning message
   * @param string $contents the file bytes
   * @param int $status the expected HTTP status
   *
   * @return array<string, mixed> the decoded attachment response
   */
  private function upload(KernelBrowser $client, string $token, string $messageId, string $contents, int $status): array
  {
    $path = tempnam(sys_get_temp_dir(), 'messaging-http-');
    self::assertIsString($path);
    file_put_contents($path, $contents);

    try {
      $client->request('POST', '/api/messages/' . $messageId . '/attachments', parameters: ['label' => 'HTTP evidence'], files: ['file' => new UploadedFile($path, 'messaging-flow.pdf', 'application/pdf', UPLOAD_ERR_OK, true)], server: ['HTTP_ACCEPT' => 'application/ld+json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
      self::assertSame($status, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent() ?: '');

      return $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    } finally {
      if (file_exists($path)) {
        unlink($path);
      }
    }
  }

  /**
   * Method headers.
   *
   * Builds the negotiated JSON-LD and merge-patch headers.
   *
   * @access private
   *
   * @param string $token the access token
   * @param bool $patch whether the request is a merge patch
   *
   * @return array<string, string> the request headers
   */
  private function headers(string $token, bool $patch = false): array
  {
    return ['HTTP_ACCEPT' => 'application/ld+json', 'CONTENT_TYPE' => $patch ? 'application/merge-patch+json' : 'application/ld+json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token];
  }

  /**
   * Method connection.
   *
   * Reads only the explicit business entity manager in the run's cloned database.
   *
   * @access private
   *
   * @param KernelBrowser $client the current HTTP client
   *
   * @return Connection the main PostgreSQL connection
   */
  private function connection(KernelBrowser $client): Connection
  {
    /** @var EntityManagerInterface $manager */
    $manager = $client->getContainer()->get('doctrine.orm.main_entity_manager');

    return $manager->getConnection();
  }

  /**
   * Method id.
   *
   * Requires the resource identifier rather than guessing it from a route.
   *
   * @access private
   *
   * @param array<string, mixed> $resource the API output
   *
   * @return string the bare identifier
   */
  private function id(array $resource): string
  {
    self::assertIsString($resource['id'] ?? null);

    return $resource['id'];
  }

  /**
   * Method members.
   *
   * Requires the JSON-LD collection shape before checking exact item identities.
   *
   * @access private
   *
   * @param array<string, mixed> $collection the API output
   *
   * @return list<array<string, mixed>> the collection members
   */
  private function members(array $collection): array
  {
    $members = $collection['member'] ?? $collection['hydra:member'] ?? null;
    self::assertIsArray($members);
    $result = [];
    foreach ($members as $member) {
      self::assertIsArray($member);
      $row = [];
      foreach ($member as $key => $value) {
        self::assertIsString($key);
        $row[$key] = $value;
      }
      $result[] = $row;
    }

    return $result;
  }

  /**
   * Method number.
   *
   * Requires a numeric PostgreSQL result before converting it to an exact count.
   *
   * @access private
   *
   * @param mixed $value the DBAL result
   *
   * @return int the confirmed count
   */
  private function number(mixed $value): int
  {
    self::assertTrue(is_numeric($value));

    return (int) $value;
  }
  // #endregion
}
