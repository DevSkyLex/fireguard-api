<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function in_array;
use function sprintf;

/**
 * Test MessagingPresentationFlowTest.
 *
 * Authenticated E2E flow tests for the Messaging module's Presentation layer
 * (API Platform resources: Conversation, Channel, DirectConversation, Message,
 * Presence, MessagingLink, MessagingAttachment, MessagingAttachmentContent).
 *
 * Every Messaging operation is gated by `is_granted('ROLE_USER')` at the
 * resource level; the seeded fixtures load NO messaging data and the
 * client-credentials principal is not an organization messaging member, so a
 * 2xx happy path is not reachable here. The coverage therefore rests on two
 * reliable pillars:
 *   1. an UNAUTHENTICATED request per endpoint, asserting the route exists
 *      (status != 404) and is guarded (401/403) — the firewall short-circuits
 *      before any provider/processor runs, so this is deterministic; and
 *   2. an AUTHENTICATED request against each provider whose required query
 *      filter is validated BEFORE any data/permission access, asserting the
 *      deterministic 400 (Bad Request) it raises — this genuinely exercises the
 *      Presentation provider under a valid token.
 *
 * @category E2E Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MessagingPresentationFlowTest extends OAuth2WebTestCase
{
  /**
   * Arbitrary well-formed UUID used wherever an operation needs a resource id
   * in the URI. No such messaging row is seeded, so it is only ever used by the
   * UNAUTHENTICATED guard assertions, which are decided at the firewall long
   * before the id is looked up.
   */
  private const string ANY_ID = '0191c3d4-e5f6-7890-8abc-def012345678';

  /**
   * Seeded organization id (see `Organization\Infrastructure\DataFixtures\OrganizationFixtures`),
   * used as the `organization` IRI filter on authenticated list requests.
   */
  private const string ORGANIZATION_ID = OrganizationFixtures::ORGANIZATION_ID;

  // #region Setup

  protected function setUp(): void
  {
    parent::setUp();
    // Reset token cache between tests since fixtures are reloaded per test.
    $this->accessToken = null;
  }

  // #endregion

  // #region Conversation resource

  public function testConversationEndpointsExistAndAreGuardedWhenUnauthenticated(): void
  {
    $client = static::createClientWithFixtures();

    $this->assertRouteGuarded($client, 'GET', '/api/conversations?organization=' . self::ORGANIZATION_ID);
    $this->assertRouteGuarded($client, 'POST', '/api/conversations', '{}');
    $this->assertRouteGuarded($client, 'GET', '/api/conversations/' . self::ANY_ID);
    $this->assertRouteGuarded($client, 'PATCH', '/api/conversations/' . self::ANY_ID, '{}');
    $this->assertRouteGuarded($client, 'PATCH', '/api/conversations/' . self::ANY_ID . '/read', '{}');
    $this->assertRouteGuarded($client, 'GET', '/api/conversations/' . self::ANY_ID . '/subscription');
    $this->assertRouteGuarded($client, 'POST', '/api/conversations/' . self::ANY_ID . '/favorite', '{}');
    $this->assertRouteGuarded($client, 'DELETE', '/api/conversations/' . self::ANY_ID . '/favorite');
    $this->assertRouteGuarded($client, 'GET', '/api/conversations/' . self::ANY_ID . '/activity');
  }

  public function testListConversationsAuthenticatedRejectsMissingOrganizationFilter(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);
    self::assertNotNull($token, 'Should be able to obtain an access token.');

    // The `organization` filter is validated inside ListConversationsProvider
    // before any permission/data access, so a missing filter is a deterministic
    // 400 for an authenticated caller — proof the provider ran under the token.
    $status = $this->authenticatedRequest($client, 'GET', '/api/conversations', $token);

    self::assertSame(Response::HTTP_BAD_REQUEST, $status, 'Missing organization filter must yield 400.');
  }

  // #endregion

  // #region Channel resource

  public function testChannelEndpointsExistAndAreGuardedWhenUnauthenticated(): void
  {
    $client = static::createClientWithFixtures();

    $this->assertRouteGuarded($client, 'POST', '/api/channels', '{}');
    $this->assertRouteGuarded($client, 'GET', '/api/channels?organization=' . self::ORGANIZATION_ID);
    $this->assertRouteGuarded($client, 'GET', '/api/channels/' . self::ANY_ID);
    $this->assertRouteGuarded($client, 'PATCH', '/api/channels/' . self::ANY_ID, '{}');
    $this->assertRouteGuarded($client, 'DELETE', '/api/channels/' . self::ANY_ID);
    $this->assertRouteGuarded($client, 'POST', '/api/channels/' . self::ANY_ID . '/participants', '{}');
    $this->assertRouteGuarded($client, 'GET', '/api/channels/' . self::ANY_ID . '/participants');
    $this->assertRouteGuarded($client, 'DELETE', '/api/channels/' . self::ANY_ID . '/participants/' . self::ANY_ID);
    $this->assertRouteGuarded($client, 'PATCH', '/api/channels/' . self::ANY_ID . '/team', '{}');
    $this->assertRouteGuarded($client, 'PATCH', '/api/channels/' . self::ANY_ID . '/parent', '{}');
  }

  // #endregion

  // #region DirectConversation resource

  public function testDirectConversationEndpointsExistAndAreGuardedWhenUnauthenticated(): void
  {
    $client = static::createClientWithFixtures();

    $this->assertRouteGuarded($client, 'POST', '/api/direct-conversations', '{}');
    $this->assertRouteGuarded($client, 'GET', '/api/direct-conversations?organization=' . self::ORGANIZATION_ID);
  }

  // #endregion

  // #region Message resource

  public function testMessageEndpointsExistAndAreGuardedWhenUnauthenticated(): void
  {
    $client = static::createClientWithFixtures();

    $this->assertRouteGuarded($client, 'GET', '/api/conversations/' . self::ANY_ID . '/messages');
    $this->assertRouteGuarded($client, 'POST', '/api/conversations/' . self::ANY_ID . '/messages', '{}');
    $this->assertRouteGuarded($client, 'PUT', '/api/conversations/' . self::ANY_ID . '/messages/' . self::ANY_ID, '{}');
    $this->assertRouteGuarded($client, 'PATCH', '/api/messages/' . self::ANY_ID, '{}');
    $this->assertRouteGuarded($client, 'DELETE', '/api/messages/' . self::ANY_ID);
    $this->assertRouteGuarded($client, 'GET', '/api/conversations/' . self::ANY_ID . '/pinned-messages');
    $this->assertRouteGuarded($client, 'POST', '/api/messages/' . self::ANY_ID . '/pin', '{}');
    $this->assertRouteGuarded($client, 'DELETE', '/api/messages/' . self::ANY_ID . '/pin');
    $this->assertRouteGuarded($client, 'POST', '/api/messages/' . self::ANY_ID . '/reactions', '{}');
    $this->assertRouteGuarded($client, 'DELETE', '/api/messages/' . self::ANY_ID . '/reactions/thumbsup');
    $this->assertRouteGuarded($client, 'POST', '/api/messages/' . self::ANY_ID . '/replies', '{}');
    $this->assertRouteGuarded($client, 'GET', '/api/messages/' . self::ANY_ID . '/replies');
    $this->assertRouteGuarded($client, 'GET', '/api/saved-messages?organization=' . self::ORGANIZATION_ID);
    $this->assertRouteGuarded($client, 'POST', '/api/messages/' . self::ANY_ID . '/save', '{}');
    $this->assertRouteGuarded($client, 'DELETE', '/api/messages/' . self::ANY_ID . '/save');
  }

  public function testListSavedMessagesAuthenticatedRejectsMissingOrganizationFilter(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);
    self::assertNotNull($token, 'Should be able to obtain an access token.');

    // ListSavedMessagesProvider validates the required `organization` filter
    // before any data access, so an authenticated call without it is a
    // deterministic 400.
    $status = $this->authenticatedRequest($client, 'GET', '/api/saved-messages', $token);

    self::assertSame(Response::HTTP_BAD_REQUEST, $status, 'Missing organization filter must yield 400.');
  }

  // #endregion

  // #region Presence resource

  public function testPresenceEndpointsExistAndAreGuardedWhenUnauthenticated(): void
  {
    $client = static::createClientWithFixtures();

    $this->assertRouteGuarded($client, 'POST', '/api/presence/ping', '{}');
    $this->assertRouteGuarded($client, 'GET', '/api/presence?organization=' . self::ORGANIZATION_ID . '&memberIds=' . self::ANY_ID);
  }

  public function testGetPresenceAuthenticatedRejectsMissingFilters(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);
    self::assertNotNull($token, 'Should be able to obtain an access token.');

    // GetPresenceProvider validates both the `organization` and the `memberIds`
    // filters before any data access; a bare authenticated request is a
    // deterministic 400.
    $missingAll = $this->authenticatedRequest($client, 'GET', '/api/presence', $token);
    self::assertSame(Response::HTTP_BAD_REQUEST, $missingAll, 'Missing organization filter must yield 400.');

    // With an organization but no memberIds, the second guard still yields 400.
    $missingMembers = $this->authenticatedRequest($client, 'GET', '/api/presence?organization=' . self::ORGANIZATION_ID, $token);
    self::assertSame(Response::HTTP_BAD_REQUEST, $missingMembers, 'Missing memberIds filter must yield 400.');
  }

  // #endregion

  // #region MessagingLink / MessagingAttachment / MessagingAttachmentContent resources

  public function testAttachmentAndLinkEndpointsExistAndAreGuardedWhenUnauthenticated(): void
  {
    $client = static::createClientWithFixtures();

    // MessagingLink.
    $this->assertRouteGuarded($client, 'GET', '/api/conversations/' . self::ANY_ID . '/links');

    // MessagingAttachment.
    $this->assertRouteGuarded($client, 'POST', '/api/messages/' . self::ANY_ID . '/attachments', '{}');
    $this->assertRouteGuarded($client, 'GET', '/api/conversations/' . self::ANY_ID . '/attachments');
    $this->assertRouteGuarded($client, 'DELETE', '/api/messaging-attachments/' . self::ANY_ID);

    // MessagingAttachmentContent (binary download controller).
    $this->assertRouteGuarded($client, 'GET', '/api/messaging-attachments/' . self::ANY_ID . '/content');
  }

  // #endregion

  // #region Helpers

  /**
   * Issues an UNAUTHENTICATED request and asserts the route both exists
   * (status != 404) and is guarded (401 or 403). The security firewall decides
   * this before any provider/processor runs, so it is deterministic regardless
   * of whether the referenced resource is seeded.
   */
  private function assertRouteGuarded(KernelBrowser $client, string $method, string $uri, ?string $content = null): void
  {
    $server = [
      'HTTP_ACCEPT' => 'application/ld+json',
    ];
    if (null !== $content) {
      $server['CONTENT_TYPE'] = 'application/ld+json';
    }

    $client->request(method: $method, uri: $uri, server: $server, content: $content ?? '');

    $status = $client->getResponse()->getStatusCode();

    self::assertNotSame(
      Response::HTTP_NOT_FOUND,
      $status,
      sprintf('Route %s %s should exist (got 404).', $method, $uri),
    );
    self::assertTrue(
      in_array($status, [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN], true),
      sprintf('Route %s %s should be guarded (401/403), got %d.', $method, $uri, $status),
    );
  }

  /**
   * Issues an AUTHENTICATED request with the given bearer token and returns the
   * resulting HTTP status code.
   */
  private function authenticatedRequest(KernelBrowser $client, string $method, string $uri, string $token, ?string $content = null): int
  {
    $server = [
      'HTTP_ACCEPT' => 'application/ld+json',
      'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
    ];
    if (null !== $content) {
      $server['CONTENT_TYPE'] = 'application/ld+json';
    }

    $client->request(method: $method, uri: $uri, server: $server, content: $content ?? '');

    return $client->getResponse()->getStatusCode();
  }

  // #endregion
}
