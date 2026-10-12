<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\Support\Auth\InteractiveTokenFactory;
use Tests\Support\Database\RootCommitDatabaseIsolationTrait;
use Tests\Support\Factory\UserTestFactory;
use User\Application\Port\Outbound\Internal\UserRepositoryPort;

use function is_array;
use function is_string;
use function json_decode;
use function str_starts_with;

use const JSON_THROW_ON_ERROR;

/**
 * Exercises the real stateful GET route, owner gate, bus command and durable main receipt.
 *
 * @category Functional Test
 */
#[SkipDatabaseRollback]
final class OnboardingHttpIdempotencyApiTest extends WebTestCase
{
  use RootCommitDatabaseIsolationTrait;

  private const string USER = 'aa0e8400-e29b-41d4-a716-4466554c0901';

  #[Test]
  public function keyedGetAndPreferenceRetriesReplayTheirCommittedStateWithoutOverwritingLaterProgress(): void
  {
    $client = static::createClient();
    $client->disableReboot();
    $repository = static::getContainer()->get(UserRepositoryPort::class);
    self::assertInstanceOf(UserRepositoryPort::class, $repository);
    $repository->save(UserTestFactory::createActive(self::USER, 'onboarding-http@example.test', 'onboarding_http_user'));
    $token = InteractiveTokenFactory::issue(static::getContainer(), self::USER, 'onboarding-http@example.test');

    $this->call($client, $token, 'GET', '/api/onboarding/organization', 'read-initial');
    self::assertResponseStatusCodeSame(200);
    $initial = $this->body($client);
    self::assertIsString($initial['sessionId']);
    self::assertNull($initial['targetOrganizationId']);
    self::assertNull($initial['accessibleOrganizationId']);
    self::assertFalse($initial['dismissed']);
    self::assertIsArray($initial['steps']);
    self::assertCount(5, $initial['steps']);

    $this->call($client, $token, 'POST', '/api/onboarding/organization/dismiss', 'dismiss-once');
    self::assertResponseStatusCodeSame(200);
    $dismissed = $this->body($client);
    self::assertTrue($dismissed['dismissed']);

    $this->call($client, $token, 'GET', '/api/onboarding/organization', 'read-initial');
    self::assertResponseStatusCodeSame(200);
    self::assertResponseHeaderSame('Idempotency-Replayed', 'true');
    self::assertSame($this->withoutGeneratedIris($initial), $this->withoutGeneratedIris($this->body($client)));

    $this->call($client, $token, 'GET', '/api/onboarding/organization', 'read-new');
    self::assertResponseStatusCodeSame(200);
    self::assertTrue($this->body($client)['dismissed']);

    $this->call($client, $token, 'POST', '/api/onboarding/organization/resume', 'resume-once');
    self::assertResponseStatusCodeSame(200);
    self::assertFalse($this->body($client)['dismissed']);

    $this->call($client, $token, 'POST', '/api/onboarding/organization/dismiss', 'dismiss-once');
    self::assertResponseStatusCodeSame(200);
    self::assertResponseHeaderSame('Idempotency-Replayed', 'true');
    self::assertSame($this->withoutGeneratedIris($dismissed), $this->withoutGeneratedIris($this->body($client)));
    $manager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);
    self::assertNull($manager->getConnection()->fetchOne('SELECT dismissed_at FROM organization_onboarding_sessions WHERE user_id = ?', [self::USER]));
    self::assertSame($initial['sessionId'], $manager->getConnection()->fetchOne('SELECT id FROM organization_onboarding_sessions WHERE user_id = ?', [self::USER]));
  }

  /**
   * @param array<array-key, mixed> $body serialized state
   *
   * @return array<array-key, mixed> exact state excluding only API Platform's ephemeral blank-node identifiers
   */
  private function withoutGeneratedIris(array $body): array
  {
    foreach ($body as $key => $value) {
      if ('@id' === $key && is_string($value) && str_starts_with($value, '/api/.well-known/genid/')) {
        unset($body[$key]);
      } elseif (is_array($value)) {
        $body[$key] = $this->withoutGeneratedIris($value);
      }
    }

    return $body;
  }

  private function call(KernelBrowser $client, string $token, string $method, string $path, string $key): void
  {
    $client->request($method, $path, server: [
      'CONTENT_TYPE' => 'application/ld+json',
      'HTTP_ACCEPT' => 'application/ld+json',
      'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      'HTTP_IDEMPOTENCY_KEY' => $key,
    ], content: 'POST' === $method ? '{}' : null);
  }

  /**
   * @return array<string, mixed>
   */
  private function body(KernelBrowser $client): array
  {
    $body = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    self::assertIsArray($body);

    /** @var array<string, mixed> $body */
    return $body;
  }
}
