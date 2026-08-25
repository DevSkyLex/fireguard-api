<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function basename;
use function is_string;
use function json_encode;
use function str_contains;
use function uniqid;

/**
 * Test AssistantPresentationFlow.
 *
 * End-to-end coverage for the Assistant module Presentation layer:
 * the org-scoped, member-private AI-assistant thread endpoints
 * (`/api/organizations/{organizationId}/assistant/...`). Each endpoint gets
 * an authenticated happy-path assertion (status + response shape) and an
 * unauthenticated guard assertion (route exists, is protected).
 *
 * @category E2E Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AssistantPresentationFlowTest extends OAuth2WebTestCase
{
  private const string ASSISTANT_QUESTION = 'What is the inspection interval for a fire extinguisher?';

  #[Test]
  public function testAuthenticatedMemberCanDriveTheFullAssistantThreadFlow(): void
  {
    $client = static::createClientWithFixtures();

    $ownerEmail = 'assistant-owner-' . uniqid() . '@example.com';
    $ownerPassword = 'OwnerPassword123!';

    $this->createAndActivateUser($client, $ownerEmail, $ownerPassword);
    $ownerToken = $this->loginAndGetUserAccessToken($client, $ownerEmail, $ownerPassword);

    // The organization owner holds the `organization.*` wildcard, which
    // covers the `organization.assistant.use` permission each handler gates on.
    $organizationId = $this->createOrganization($client, $ownerToken, 'Assistant Org ' . uniqid());
    self::assertNotNull($organizationId, 'Organization should be created successfully.');

    $this->enableAssistantFor($client, $ownerToken, $organizationId);

    // Endpoint 1: GetCollection — list my assistant threads (initially empty).
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/assistant/threads',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
    );

    self::assertSame(
      Response::HTTP_OK,
      $client->getResponse()->getStatusCode(),
      'Listing assistant threads should succeed. Response: ' . $client->getResponse()->getContent(),
    );

    $listData = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertArrayHasKey('member', $listData, 'Hydra collection should expose a member array.');
    self::assertIsArray($listData['member']);

    // Endpoint 2: Post — start a new assistant thread.
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/assistant/threads',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
      content: json_encode(['title' => 'Compliance questions']) ?: '',
    );

    self::assertSame(
      Response::HTTP_CREATED,
      $client->getResponse()->getStatusCode(),
      'Starting an assistant thread should succeed. Response: ' . $client->getResponse()->getContent(),
    );

    $threadData = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    $threadId = $this->extractResourceId($threadData);
    self::assertNotNull($threadId, 'Started thread should expose an id.');
    self::assertSame($organizationId, $threadData['organizationId'] ?? null, 'Thread should belong to the organization.');
    self::assertArrayHasKey('memberId', $threadData, 'Thread should expose its owning memberId.');
    self::assertSame('Compliance questions', $threadData['title'] ?? null, 'Thread title should round-trip.');

    // Endpoint 3: Get — read the thread with its (paged) messages.
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/assistant/threads/' . $threadId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
    );

    self::assertSame(
      Response::HTTP_OK,
      $client->getResponse()->getStatusCode(),
      'Reading the assistant thread should succeed. Response: ' . $client->getResponse()->getContent(),
    );

    $detailData = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertSame($threadId, $this->extractResourceId($detailData), 'Detail id should match the created thread.');
    self::assertArrayHasKey('messages', $detailData, 'Thread detail should expose a messages array.');
    self::assertIsArray($detailData['messages']);
    self::assertArrayHasKey('messagesTotal', $detailData, 'Thread detail should expose messagesTotal.');

    // Endpoint 4: Post — ask a question. The reply generation is routed to the
    // `assistant` transport, which is `in-memory://` under test, so this only
    // persists the question + a pending reply and enqueues (no Ollama call).
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/assistant/threads/' . $threadId . '/messages',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
      content: json_encode(['body' => self::ASSISTANT_QUESTION]) ?: '',
    );

    self::assertSame(
      Response::HTTP_CREATED,
      $client->getResponse()->getStatusCode(),
      'Asking an assistant question should succeed. Response: ' . $client->getResponse()->getContent(),
    );

    $askData = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertSame($threadId, $askData['threadId'] ?? null, 'Answer payload should reference the thread.');
    self::assertArrayHasKey('userMessage', $askData, 'Answer payload should expose the persisted user message.');
    self::assertArrayHasKey('assistantMessage', $askData, 'Answer payload should expose the pending assistant reply.');
    self::assertIsArray($askData['userMessage']);
    self::assertSame(self::ASSISTANT_QUESTION, $askData['userMessage']['body'] ?? null, 'User message body should round-trip.');

    // Endpoint 5: Get — mint a Mercure subscription token scoped to this thread.
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/assistant/threads/' . $threadId . '/subscription',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
    );

    self::assertSame(
      Response::HTTP_OK,
      $client->getResponse()->getStatusCode(),
      'Minting an assistant subscription should succeed. Response: ' . $client->getResponse()->getContent(),
    );

    $subscriptionData = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertArrayHasKey('token', $subscriptionData, 'Subscription should expose a Mercure JWT.');
    self::assertArrayHasKey('topic', $subscriptionData, 'Subscription should expose the exact topic.');
    $topic = $subscriptionData['topic'] ?? null;
    self::assertIsString($topic);
    self::assertStringContainsString($threadId, $topic, 'Topic must be scoped to this thread.');
  }

  #[Test]
  public function testListAssistantThreadsRequiresAuthentication(): void
  {
    $this->assertRouteIsGuarded(
      'GET',
      '/api/organizations/' . uniqid('org-') . '/assistant/threads',
    );
  }

  #[Test]
  public function testStartAssistantThreadRequiresAuthentication(): void
  {
    $this->assertRouteIsGuarded(
      'POST',
      '/api/organizations/' . uniqid('org-') . '/assistant/threads',
      json_encode(['title' => 'Nope']) ?: '',
    );
  }

  #[Test]
  public function testGetAssistantThreadRequiresAuthentication(): void
  {
    $this->assertRouteIsGuarded(
      'GET',
      '/api/organizations/' . uniqid('org-') . '/assistant/threads/' . uniqid('thread-'),
    );
  }

  #[Test]
  public function testAskAssistantQuestionRequiresAuthentication(): void
  {
    $this->assertRouteIsGuarded(
      'POST',
      '/api/organizations/' . uniqid('org-') . '/assistant/threads/' . uniqid('thread-') . '/messages',
      json_encode(['body' => 'Nope']) ?: '',
    );
  }

  #[Test]
  public function testGetAssistantThreadSubscriptionRequiresAuthentication(): void
  {
    $this->assertRouteIsGuarded(
      'GET',
      '/api/organizations/' . uniqid('org-') . '/assistant/threads/' . uniqid('thread-') . '/subscription',
    );
  }

  // #region Helpers

  /**
   * Assert an assistant route exists (not 404) and rejects anonymous access.
   */
  private function assertRouteIsGuarded(string $method, string $uri, ?string $content = null): void
  {
    $client = static::createClientWithFixtures();

    $server = ['HTTP_ACCEPT' => 'application/ld+json'];
    if (null !== $content) {
      $server['CONTENT_TYPE'] = 'application/ld+json';
    }

    $client->request(method: $method, uri: $uri, server: $server, content: $content ?? '');

    $status = $client->getResponse()->getStatusCode();

    self::assertNotSame(
      Response::HTTP_NOT_FOUND,
      $status,
      'Route should exist: ' . $method . ' ' . $uri . '. Response: ' . $client->getResponse()->getContent(),
    );
    self::assertContains(
      $status,
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
      'Unauthenticated access should be rejected (401/403). Got ' . $status . ' for ' . $method . ' ' . $uri,
    );
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
    self::assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'User login should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $token = $data['access_token'] ?? null;

    self::assertTrue(is_string($token) && '' !== $token, 'Login response should contain access_token.');

    return $token;
  }

  /**
   * Turns the assistant on for the organization.
   *
   * The assistant is opt-in: `OrganizationAssistantDefaults::ENABLED` is
   * `false`, so a freshly created organization has it off and every endpoint
   * answers 403 through `AssistantAccessPolicy`. This flow exercises the
   * assistant, so it does what an administrator would do first.
   */
  private function enableAssistantFor(KernelBrowser $client, string $token, string $organizationId): void
  {
    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . $organizationId,
      server: [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode(['assistant' => ['enabled' => true]]) ?: '',
    );

    self::assertSame(
      Response::HTTP_OK,
      $client->getResponse()->getStatusCode(),
      'Enabling the assistant should succeed. Response: ' . $client->getResponse()->getContent(),
    );
  }

  private function createOrganization(KernelBrowser $client, string $token, string $name): ?string
  {
    $client->request(
      method: 'POST',
      uri: '/api/organizations',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode(['name' => $name]) ?: '',
    );

    $data = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');

    return $this->extractResourceId($data);
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

  // #endregion
}
