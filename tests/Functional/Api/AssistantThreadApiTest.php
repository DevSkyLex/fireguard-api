<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

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

  #[Test]
  public function testAttemptControlContractValidationAndStaleRetry(): void
  {
    $client = static::createClient();
    $client->disableReboot();
    $container = static::getContainer();
    $users = $this->createStub(\User\Application\Port\Outbound\UserRepositoryPort::class);
    $users->method('findById')->willReturnCallback(static fn (\User\Domain\ValueObject\UserId $id) => \Tests\Support\Factory\UserTestFactory::createActive((string) $id, (string) $id . '@corp.example'));
    $container->set(\User\Application\Port\Outbound\UserRepositoryPort::class, $users);
    $container->set(\Organization\Application\Port\Inbound\OrganizationAuthorizationPort::class, $this->createStubForIntersectionOfInterfaces([\Organization\Application\Port\Inbound\OrganizationAuthorizationPort::class, \Symfony\Contracts\Service\ResetInterface::class]));
    $settings = $this->createStub(\Assistant\Application\Port\Outbound\Organization\AssistantOrganizationSettingsPort::class);
    $settings->method('isEnabledFor')->willReturn(true);
    $container->set(\Assistant\Application\Port\Outbound\Organization\AssistantOrganizationSettingsPort::class, $settings);
    $container->set(\Assistant\Application\Port\Outbound\AssistantRealtimePublisherPort::class, $this->createStub(\Assistant\Application\Port\Outbound\AssistantRealtimePublisherPort::class));
    $actor = 'bd000000-0000-4000-8000-000000000295';
    $messageId = 'bd000000-0000-4000-8000-000000000296';
    $token = \Tests\Support\Auth\InteractiveTokenFactory::issue($container, $actor, $actor . '@corp.example');
    $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);
    $client->setServerParameter('CONTENT_TYPE', 'application/ld+json');
    $client->setServerParameter('HTTP_ACCEPT', 'application/ld+json');
    $now = new DateTimeImmutable();
    $thread = \Assistant\Domain\Model\Thread\AssistantThread::start(\Assistant\Domain\ValueObject\AssistantThreadId::fromString(self::DUMMY_UUID_2), self::DUMMY_UUID, $actor, null, $now);
    $threads = $container->get(\Assistant\Application\Port\Outbound\AssistantThreadRepositoryPort::class);
    self::assertInstanceOf(\Assistant\Application\Port\Outbound\AssistantThreadRepositoryPort::class, $threads);
    $threads->save($thread);
    $message = \Assistant\Domain\Model\Message\AssistantMessage::pendingReply(\Assistant\Domain\ValueObject\AssistantMessageId::fromString($messageId), self::DUMMY_UUID_2, self::DUMMY_UUID, $now);
    $message->initializeAttempt('bd000000-0000-4000-8000-000000000297', null, $now);
    $messages = $container->get(\Assistant\Application\Port\Outbound\AssistantMessageRepositoryPort::class);
    self::assertInstanceOf(\Assistant\Application\Port\Outbound\AssistantMessageRepositoryPort::class, $messages);
    $messages->save($message);
    $path = '/api/organizations/' . self::DUMMY_UUID . '/assistant/threads/' . self::DUMMY_UUID_2 . '/messages/' . $messageId;
    $body = json_encode(['attemptId' => $messageId], JSON_THROW_ON_ERROR);
    $client->request('POST', $path . '/cancel', content: '{}');
    self::assertResponseStatusCodeSame(422);
    $client->request('POST', $path . '/cancel', content: $body);
    self::assertResponseIsSuccessful();
    $content = $client->getResponse()->getContent();
    self::assertIsString($content);
    $cancelled = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    self::assertIsArray($cancelled);
    self::assertSame('cancelled', $cancelled['status']);
    self::assertTrue($cancelled['canRetry']);
    self::assertFalse($cancelled['canCancel']);
    $client->request('POST', $path . '/retry', content: $body);
    self::assertResponseIsSuccessful();
    $content = $client->getResponse()->getContent();
    self::assertIsString($content);
    $retried = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    self::assertIsArray($retried);
    self::assertSame('pending', $retried['status']);
    self::assertSame(2, $retried['attemptNumber']);
    self::assertNotSame($messageId, $retried['attemptId']);
    self::assertNotEmpty($retried['attemptExpiresAt']);
    $client->request('POST', $path . '/retry', content: $body);
    self::assertResponseStatusCodeSame(409);
    $content = $client->getResponse()->getContent();
    self::assertIsString($content);
    $conflict = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    self::assertIsArray($conflict);
    self::assertSame('assistant_attempt_conflict', $conflict['code']);
  }
}
