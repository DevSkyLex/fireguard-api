<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Authorization\Application\Port\Inbound\AuthorizationSeedRoleAssignmentsPort;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Infrastructure\Persistence\Doctrine\Record\PlanRecord;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\{Connection as QueueConnection, DoctrineTransport};
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Tests\Support\Auth\InteractiveTokenFactory;
use Tests\Support\Database\RootCommitDatabaseIsolationTrait;
use Tests\Support\Factory\UserTestFactory;
use User\Application\Port\Outbound\Internal\UserRepositoryPort;

use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Class PlanQuotaValidationApiTest
 *
 * Rejects numeric quota keys at the real HTTP boundary without committing a plan or changing an existing one.
 *
 * @category Functional Test
 */
#[SkipDatabaseRollback]
final class PlanQuotaValidationApiTest extends WebTestCase
{
  use RootCommitDatabaseIsolationTrait;

  private const string ADMIN = 'da500000-0000-4000-8000-000000000001';

  private const string PLAN = 'da500000-0000-4000-8000-000000000002';

  /**
   * @return iterable<string, array{string}>
   */
  public static function malformedQuotaJson(): iterable
  {
    yield 'list' => ['[5]'];
    yield 'numeric object key' => ['{"0":5}'];
  }

  #[Test]
  #[DataProvider('malformedQuotaJson')]
  public function createRejectsMalformedKeysWithAClientError(string $limits): void
  {
    [$client, $main] = $this->seed();
    $planCount = $main->fetchOne('SELECT COUNT(*) FROM plans');
    $outboxCount = $main->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'main_outbox'");
    $client->request('POST', '/api/plans', server: [
      'CONTENT_TYPE' => 'application/ld+json',
      'HTTP_ACCEPT' => 'application/ld+json',
      'HTTP_IDEMPOTENCY_KEY' => 'invalid-quota-create',
    ], content: '{"key":"numeric-limits","name":"Numeric limits","limits":' . $limits . '}');

    self::assertResponseStatusCodeSame(400);
    self::assertStringContainsString('Quota resource keys must be registered string identifiers.', $client->getResponse()->getContent() ?: '');
    self::assertSame($planCount, $main->fetchOne('SELECT COUNT(*) FROM plans'));
    self::assertSame($outboxCount, $main->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'main_outbox'"));
    self::assertSame(0, $main->fetchOne("SELECT COUNT(*) FROM http_idempotency_receipts WHERE state = 'completed'"));
  }

  #[Test]
  #[DataProvider('malformedQuotaJson')]
  public function patchRejectsMalformedKeysWithoutSavingEarlierChanges(string $limits): void
  {
    [$client, $main] = $this->seed();
    $outboxCount = $main->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'main_outbox'");
    $client->request('PATCH', '/api/plans/' . self::PLAN, server: [
      'CONTENT_TYPE' => 'application/merge-patch+json',
      'HTTP_ACCEPT' => 'application/ld+json',
      'HTTP_IDEMPOTENCY_KEY' => 'invalid-quota-patch',
    ], content: '{"name":"Must not be saved","limits":' . $limits . '}');

    self::assertResponseStatusCodeSame(400);
    self::assertStringContainsString('Quota resource keys must be registered string identifiers.', $client->getResponse()->getContent() ?: '');
    self::assertSame('Original quota plan', $main->fetchOne('SELECT name FROM plans WHERE id = ?', [self::PLAN]));
    $storedLimits = $main->fetchOne('SELECT limits FROM plans WHERE id = ?', [self::PLAN]);
    self::assertIsString($storedLimits);
    self::assertSame(['members' => 3], json_decode($storedLimits, true, flags: JSON_THROW_ON_ERROR));
    self::assertSame($outboxCount, $main->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'main_outbox'"));
    self::assertSame(0, $main->fetchOne("SELECT COUNT(*) FROM http_idempotency_receipts WHERE state = 'completed'"));
  }

  /**
   * @return array{KernelBrowser, Connection}
   */
  private function seed(): array
  {
    $client = self::createClient();
    $client->disableReboot();
    $container = self::getContainer();
    $users = $container->get(UserRepositoryPort::class);
    $assignments = $container->get(AuthorizationSeedRoleAssignmentsPort::class);
    $auth = $container->get('doctrine.orm.auth_entity_manager');
    $main = $container->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(UserRepositoryPort::class, $users);
    self::assertInstanceOf(AuthorizationSeedRoleAssignmentsPort::class, $assignments);
    self::assertInstanceOf(EntityManagerInterface::class, $auth);
    self::assertInstanceOf(EntityManagerInterface::class, $main);
    $sender = new DoctrineTransport(new QueueConnection(['queue_name' => 'main_outbox', 'auto_setup' => false, 'use_notify' => false], $main->getConnection()), new PhpSerializer());
    $container->set('messenger.transport.main_outbox', $sender);
    $sender->setup();
    $users->save(UserTestFactory::createActive(self::ADMIN, 'quota-admin@example.test', 'quota_admin'));
    $assignments->assign(self::ADMIN, 'admin', self::ADMIN, new DateTimeImmutable());
    $auth->flush();
    $now = new DateTimeImmutable();
    $plan = new PlanRecord();
    $plan->id = self::PLAN;
    $plan->key = 'quota-validation';
    $plan->name = 'Original quota plan';
    $plan->limits = ['members' => 3];
    $plan->createdAt = $now;
    $plan->updatedAt = $now;
    $main->persist($plan);
    $main->flush();
    $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . InteractiveTokenFactory::issue($container, self::ADMIN, 'quota-admin@example.test'));

    return [$client, $main->getConnection()];
  }
}
