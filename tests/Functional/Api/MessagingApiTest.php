<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Domain\Service\DirectConversationKey;
use Messaging\Infrastructure\Persistence\Doctrine\Record\MessagingConversationRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function array_column;
use function json_decode;
use function rawurlencode;

final class MessagingApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  #[Test]
  public function testListConversationsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/conversations?organization=' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /conversations endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated GET /conversations, got ' . $statusCode);
  }

  #[Test]
  public function testGetOrCreateConversationRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/conversations', server: [
      'CONTENT_TYPE' => 'application/json',
    ], content: '{"organization":"/api/organizations/' . self::DUMMY_UUID . '","subjectType":"facility","subject":"/api/facilities/' . self::DUMMY_UUID . '"}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'POST /conversations endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated POST /conversations, got ' . $statusCode);
  }

  #[Test]
  public function testGetConversationRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/conversations/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /conversations/{id} endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated GET /conversations/{id}, got ' . $statusCode);
  }

  #[Test]
  public function testArchiveConversationRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('PATCH', '/api/conversations/' . self::DUMMY_UUID, server: [
      'CONTENT_TYPE' => 'application/merge-patch+json',
    ], content: '{"isArchived":true}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'PATCH /conversations/{id} endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated PATCH /conversations/{id}, got ' . $statusCode);
  }

  #[Test]
  public function testMarkConversationReadRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('PATCH', '/api/conversations/' . self::DUMMY_UUID . '/read', server: [
      'CONTENT_TYPE' => 'application/merge-patch+json',
    ], content: '{}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'PATCH /conversations/{id}/read endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated PATCH /conversations/{id}/read, got ' . $statusCode);
  }

  #[Test]
  public function testGetConversationSubscriptionRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/conversations/' . self::DUMMY_UUID . '/subscription');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /conversations/{id}/subscription endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated GET /conversations/{id}/subscription, got ' . $statusCode);
  }

  #[Test]
  public function testListMessagesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/conversations/' . self::DUMMY_UUID . '/messages');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /conversations/{conversationId}/messages endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated GET /conversations/{conversationId}/messages, got ' . $statusCode);
  }

  #[Test]
  public function testPostMessageRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/conversations/' . self::DUMMY_UUID . '/messages', server: [
      'CONTENT_TYPE' => 'application/json',
    ], content: '{"body":"Hello team"}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'POST /conversations/{conversationId}/messages endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated POST /conversations/{conversationId}/messages, got ' . $statusCode);
  }

  #[Test]
  public function testEditMessageRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('PATCH', '/api/messages/' . self::DUMMY_UUID, server: [
      'CONTENT_TYPE' => 'application/merge-patch+json',
    ], content: '{"body":"Updated"}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'PATCH /messages/{id} endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated PATCH /messages/{id}, got ' . $statusCode);
  }

  #[Test]
  public function testDeleteMessageRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('DELETE', '/api/messages/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'DELETE /messages/{id} endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated DELETE /messages/{id}, got ' . $statusCode);
  }

  #[Test]
  public function testAddMessageAttachmentRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/messages/' . self::DUMMY_UUID . '/attachments');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'POST /messages/{messageId}/attachments endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated POST /messages/{messageId}/attachments, got ' . $statusCode);
  }

  #[Test]
  public function testListConversationAttachmentsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/conversations/' . self::DUMMY_UUID . '/attachments');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /conversations/{conversationId}/attachments endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated GET /conversations/{conversationId}/attachments, got ' . $statusCode);
  }

  #[Test]
  public function testDeleteMessageAttachmentRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('DELETE', '/api/messaging-attachments/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'DELETE /messaging-attachments/{id} endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated DELETE /messaging-attachments/{id}, got ' . $statusCode);
  }

  #[Test]
  public function testDownloadMessageAttachmentRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/messaging-attachments/' . self::DUMMY_UUID . '/content');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /messaging-attachments/{id}/content endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated GET /messaging-attachments/{id}/content, got ' . $statusCode);
  }

  #[Test]
  public function testPinMessageRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/messages/' . self::DUMMY_UUID . '/pin');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'POST /messages/{id}/pin endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated POST /messages/{id}/pin, got ' . $statusCode);
  }

  #[Test]
  public function testUnpinMessageRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('DELETE', '/api/messages/' . self::DUMMY_UUID . '/pin');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'DELETE /messages/{id}/pin endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated DELETE /messages/{id}/pin, got ' . $statusCode);
  }

  #[Test]
  public function testListPinnedMessagesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/conversations/' . self::DUMMY_UUID . '/pinned-messages');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /conversations/{conversationId}/pinned-messages endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated GET /conversations/{conversationId}/pinned-messages, got ' . $statusCode);
  }

  #[Test]
  public function testAddReactionRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/messages/' . self::DUMMY_UUID . '/reactions', server: [
      'CONTENT_TYPE' => 'application/json',
    ], content: '{"emoji":"👍"}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'POST /messages/{id}/reactions endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated POST /messages/{id}/reactions, got ' . $statusCode);
  }

  #[Test]
  public function testRemoveReactionRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('DELETE', '/api/messages/' . self::DUMMY_UUID . '/reactions/' . rawurlencode("\u{1F44D}"));

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'DELETE /messages/{id}/reactions/{emoji} endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated DELETE /messages/{id}/reactions/{emoji}, got ' . $statusCode);
  }

  #[Test]
  public function testSaveMessageRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/messages/' . self::DUMMY_UUID . '/save');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'POST /messages/{id}/save endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated POST /messages/{id}/save, got ' . $statusCode);
  }

  #[Test]
  public function testUnsaveMessageRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('DELETE', '/api/messages/' . self::DUMMY_UUID . '/save');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'DELETE /messages/{id}/save endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated DELETE /messages/{id}/save, got ' . $statusCode);
  }

  #[Test]
  public function testListSavedMessagesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/saved-messages?organization=' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /saved-messages endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated GET /saved-messages, got ' . $statusCode);
  }

  #[Test]
  public function testFavoriteConversationRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/conversations/' . self::DUMMY_UUID . '/favorite');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'POST /conversations/{id}/favorite endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated POST /conversations/{id}/favorite, got ' . $statusCode);
  }

  #[Test]
  public function testUnfavoriteConversationRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('DELETE', '/api/conversations/' . self::DUMMY_UUID . '/favorite');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'DELETE /conversations/{id}/favorite endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated DELETE /conversations/{id}/favorite, got ' . $statusCode);
  }

  // #region L2.4 — direct messages

  #[Test]
  public function testGetOrCreateDirectConversationRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/direct-conversations', server: [
      'CONTENT_TYPE' => 'application/json',
    ], content: '{"organization":"/api/organizations/' . self::DUMMY_UUID . '","memberId":"' . self::DUMMY_UUID . '"}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'POST /direct-conversations endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated POST /direct-conversations, got ' . $statusCode);
  }

  /**
   * The regression fix #2 exists for: `MessagingConversationRepository::list()`
   * used to filter only `subjectType != CHANNEL`, so the moment
   * `MessagingSubjectType::DIRECT` existed every direct (1-to-1) conversation
   * would leak into `GET /api/conversations` — tenant-correct (still scoped
   * to the organization) but product-wrong (a private conversation showing
   * up in the organization-wide conversation list). This test seeds a real
   * subject-thread conversation AND a real direct conversation directly via
   * the ORM (bypassing the creation endpoints, which are exercised by their
   * own unit tests), authenticates as an actual organization member through
   * `loginUser()` (works for the `api` firewall even though it is
   * `stateless: true` — the token is stored in the container, not the
   * session), and asserts the direct conversation never appears in the list
   * while the subject-thread conversation does.
   */
  #[Test]
  public function testDirectConversationDoesNotAppearInListConversations(): void
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655449500';
    $userId = '550e8400-e29b-41d4-a716-446655449501';
    $memberId = '550e8400-e29b-41d4-a716-446655449502';
    $roleId = '550e8400-e29b-41d4-a716-446655449503';
    $subjectConversationId = '550e8400-e29b-41d4-a716-446655449504';
    $directConversationId = '550e8400-e29b-41d4-a716-446655449505';

    $existingOrganization = $entityManager->find(OrganizationRecord::class, $organizationId);
    if ($existingOrganization instanceof OrganizationRecord) {
      $entityManager->remove($existingOrganization);
      $entityManager->flush();
    }

    $organization = new OrganizationRecord();
    $organization->id = $organizationId;
    $organization->name = 'Direct Conversation List Test';
    $organization->slug = 'direct-conversation-list-test-' . $organizationId;
    $organization->ownerUserId = $userId;
    $organization->createdByUserId = $userId;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $role = new OrganizationRoleRecord();
    $role->id = $roleId;
    $role->organization = $organization;
    $role->name = 'full-access-tester';
    $role->permissions = ['*'];
    $role->description = 'Functional-test-only role granting every permission.';
    $role->isSystem = false;
    $role->createdAt = $now;
    $entityManager->persist($role);

    $member = new OrganizationMemberRecord();
    $member->id = $memberId;
    $member->organization = $organization;
    $member->userId = $userId;
    $member->isActive = true;
    $member->joinedAt = $now;
    $entityManager->persist($member);

    $roleAssignment = new OrganizationMemberRoleRecord();
    $roleAssignment->member = $member;
    $roleAssignment->role = $role;
    $roleAssignment->assignedAt = $now;
    $entityManager->persist($roleAssignment);

    $subjectConversation = new MessagingConversationRecord();
    $subjectConversation->id = $subjectConversationId;
    $subjectConversation->organization = $organization;
    $subjectConversation->subjectType = 'facility';
    $subjectConversation->subjectId = '550e8400-e29b-41d4-a716-446655449600';
    $subjectConversation->visibility = 'subject';
    $subjectConversation->messagesCount = 0;
    $subjectConversation->isArchived = false;
    $subjectConversation->createdAt = $now;
    $subjectConversation->updatedAt = $now;
    $entityManager->persist($subjectConversation);

    $directConversation = new MessagingConversationRecord();
    $directConversation->id = $directConversationId;
    $directConversation->organization = $organization;
    $directConversation->subjectType = 'direct';
    $directConversation->subjectId = DirectConversationKey::for($memberId, '550e8400-e29b-41d4-a716-446655449700');
    $directConversation->visibility = 'participants';
    $directConversation->messagesCount = 0;
    $directConversation->isArchived = false;
    $directConversation->createdAt = $now;
    $directConversation->updatedAt = $now;
    $entityManager->persist($directConversation);

    $entityManager->flush();

    $user = new SecurityUser(
      id: $userId,
      email: 'direct-conversation-list-test@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
    );
    $client->loginUser($user, 'api');

    $client->request('GET', '/api/conversations?organization=' . $organizationId . '&itemsPerPage=50', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), 'List should succeed. Response: ' . $response->getContent());

    $decoded = json_decode($response->getContent() ?: '{}', true);
    self::assertIsArray($decoded);
    self::assertArrayHasKey('member', $decoded);
    self::assertIsArray($decoded['member']);

    $ids = array_column($decoded['member'], 'id');

    self::assertContains($subjectConversationId, $ids, 'The subject-thread conversation must still appear in the list.');
    self::assertNotContains($directConversationId, $ids, 'A direct (1-to-1) conversation must NEVER appear in GET /api/conversations.');
  }

  // #endregion

  // #region L2.5 — threaded replies

  #[Test]
  public function testPostReplyRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/messages/' . self::DUMMY_UUID . '/replies', server: [
      'CONTENT_TYPE' => 'application/json',
    ], content: '{"body":"A reply"}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'POST /messages/{id}/replies endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated POST /messages/{id}/replies, got ' . $statusCode);
  }

  #[Test]
  public function testListRepliesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/messages/' . self::DUMMY_UUID . '/replies');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /messages/{id}/replies endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated GET /messages/{id}/replies, got ' . $statusCode);
  }

  // #endregion

  // #region L2.6 — channel parent/child hierarchy

  #[Test]
  public function testSetChannelParentRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('PATCH', '/api/channels/' . self::DUMMY_UUID . '/parent', server: [
      'CONTENT_TYPE' => 'application/merge-patch+json',
    ], content: '{"parentChannelId":null}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'PATCH /channels/{id}/parent endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated PATCH /channels/{id}/parent, got ' . $statusCode);
  }

  // #endregion

  // #region L2.7 — online presence (no DB table — cache-backed)

  #[Test]
  public function testPingPresenceRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/presence/ping', server: [
      'CONTENT_TYPE' => 'application/json',
    ], content: '{"organization":"/api/organizations/' . self::DUMMY_UUID . '"}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'POST /presence/ping endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated POST /presence/ping, got ' . $statusCode);
  }

  #[Test]
  public function testGetPresenceRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/presence?organization=' . self::DUMMY_UUID . '&memberIds=' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /presence endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated GET /presence, got ' . $statusCode);
  }

  // #endregion
}
