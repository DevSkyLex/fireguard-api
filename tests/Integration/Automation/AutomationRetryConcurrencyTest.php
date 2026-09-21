<?php

declare(strict_types=1);

namespace Tests\Integration\Automation;

use Automation\Application\Contract\Policy\AutomationPolicy;
use Automation\Application\Port\Outbound\{AutomationPolicyPort, AutomationRuleQueuePort};
use Automation\Application\UseCase\Command\RetryAutomationAttempt\{RetryAutomationAttemptCommand, RetryAutomationAttemptHandler};
use Automation\Application\UseCase\Command\Rule\ExecuteAutomationRule\{ExecuteAutomationRuleCommand, ExecuteAutomationRuleHandler};
use Automation\Domain\Exception\AutomationRetryNotAllowedException;
use Automation\Infrastructure\Persistence\Doctrine\Repository\AutomationRunRepository;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\{EntityManager, EntityManagerInterface};
use Intervention\Application\Contract\Draft\CreatedInterventionDraft;
use Intervention\Application\Port\Inbound\InterventionDraftFactoryPort;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Psr\Log\NullLogger;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort};
use Shared\Infrastructure\Messaging\Outbox\DbalTransactionManagerAdapter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_column;
use function array_unique;
use function array_values;

#[SkipDatabaseRollback]
final class AutomationRetryConcurrencyTest extends KernelTestCase
{
  private const string ORG = 'automation-retry-test';

  private const string RULE = 'auto_create_intervention_on_critical_nc';

  private EntityManagerInterface $em;

  private Connection $observer;

  private UuidFactory $ids;

  private string $run;

  private bool $enabled = true;

  protected function setUp(): void
  {
    self::bootKernel();
    $em = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $em);
    $this->em = $em;
    $ids = self::getContainer()->get(UuidFactory::class);
    self::assertInstanceOf(UuidFactory::class, $ids);
    $this->ids = $ids;
    $url = $_ENV['MAIN_DATABASE_URL'] ?? $_SERVER['MAIN_DATABASE_URL'] ?? null;
    self::assertIsString($url);
    $this->observer = DriverManager::getConnection(['url' => $url]);
    $this->observer->executeStatement('DELETE FROM automation_runs WHERE organization_id = ?', [self::ORG]);
    $repo = $this->repository($em);
    $run = $em->getConnection()->transactional(function () use ($repo): string {
      $id = $repo->reserveRun(self::RULE, self::ORG, 'nc', ['inspectionId' => 'inspection', 'nonConformityId' => 'nc', 'severity' => 'critical']);
      self::assertIsString($id);
      $repo->markFailed($id, 'Internal failure containing sensitive details');

      return $id;
    });
    $this->run = $run;
  }

  protected function tearDown(): void
  {
    while ($this->observer->isTransactionActive()) {
      $this->observer->rollBack();
    }
    $this->observer->executeStatement('DELETE FROM automation_runs WHERE organization_id = ?', [self::ORG]);
    $this->observer->close();
    parent::tearDown();
  }

  public function testConcurrentRetryKeepsOneActionAndOneNewAttempt(): void
  {
    $queue = $this->createMock(AutomationRuleQueuePort::class);
    $queue->expects(self::once())->method('enqueue')->willReturnCallback(function (string $rule, string $org, string $subject, array $payload, ?string $attempt): void {
      self::assertSame('inspection', $payload['inspectionId']);
      self::assertNotSame($this->run, $attempt);
      self::assertSame(1, $this->observer->fetchOne('SELECT COUNT(*) FROM automation_attempts WHERE run_id = ?', [$this->run]));
      $this->observer->executeStatement("SET lock_timeout = '150ms'");

      try {
        $this->handler(new EntityManager($this->observer, $this->em->getConfiguration()))($this->command());
        self::fail('A competing retry must wait for the original action lock.');
      } catch (DriverException $error) {
        self::assertSame('55P03', $error->getSQLState());
      }
    });
    $result = $this->handler($this->em, $queue)($this->command());
    self::assertSame(2, $result->attempt->attemptNumber);
    $history = $this->repository($this->em)->listAttempts(self::ORG, 20, 0, true);
    self::assertCount(2, $history);
    self::assertEqualsCanonicalizing(['failed', 'pending'], array_values(array_unique(array_column($history, 'status'))));
    self::assertFalse($history[0]->canRetry);
    self::assertCount(0, $this->repository($this->em)->listAttempts('other-org', 20, 0, true));
    self::assertSame(2, $this->repository($this->em)->countAttempts(self::ORG));
    $this->expectException(AutomationRetryNotAllowedException::class);
    $this->handler($this->em)($this->command());
  }

  public function testDispatchFailureRollsBackRetryAndRetainsOriginalFailure(): void
  {
    $queue = $this->createStub(AutomationRuleQueuePort::class);
    $queue->method('enqueue')->willThrowException(new RuntimeException('queue failed'));

    try {
      $this->handler($this->em, $queue)($this->command());
      self::fail('Queue failure must abort retry.');
    } catch (RuntimeException $error) {
      self::assertSame('queue failed', $error->getMessage());
    }
    self::assertSame(1, $this->repository($this->em)->countAttempts(self::ORG));
    $history = $this->repository($this->em)->listAttempts(self::ORG, 20, 0, true);
    self::assertTrue($history[0]->canRetry);
    self::assertSame('automation_action_failed', $history[0]->errorCode);
  }

  public function testDisabledPolicyRefusesRetryBeforeQueueing(): void
  {
    $this->enabled = false;
    $queue = $this->createMock(AutomationRuleQueuePort::class);
    $queue->expects(self::never())->method('enqueue');
    $this->expectException(AutomationRetryNotAllowedException::class);
    $this->handler($this->em, $queue)($this->command());
  }

  public function testWorkerRechecksPolicyAfterAcceptedRetry(): void
  {
    $result = $this->handler($this->em)($this->command());
    $this->enabled = false;
    $factory = $this->createMock(InterventionDraftFactoryPort::class);
    $factory->expects(self::never())->method('create');
    $this->worker($factory)($this->execution($result->attempt->id));
    self::assertSame('skipped', $this->observer->fetchOne('SELECT status FROM automation_attempts WHERE id = ?', [$result->attempt->id]));
  }

  public function testRepeatedDeliveryAfterRetryCreatesOneActionAndKeepsFailedHistory(): void
  {
    $result = $this->handler($this->em)($this->command());
    $factory = $this->createMock(InterventionDraftFactoryPort::class);
    $factory->expects(self::once())->method('create')->willReturn(new CreatedInterventionDraft('draft', 1, 1));
    $worker = $this->worker($factory);
    $worker($this->execution($result->attempt->id));
    $worker($this->execution($result->attempt->id));
    $worker($this->execution(null));
    self::assertSame('succeeded', $this->observer->fetchOne('SELECT status FROM automation_attempts WHERE id = ?', [$result->attempt->id]));
    self::assertSame('failed', $this->observer->fetchOne('SELECT status FROM automation_attempts WHERE id = ?', [$this->run]));
    self::assertSame(1, $this->observer->fetchOne('SELECT COUNT(*) FROM automation_runs WHERE organization_id = ?', [self::ORG]));
  }

  public function testFailedRetryRecordsItsOwnOutcomeAndAllowsANewAttempt(): void
  {
    $first = $this->handler($this->em)($this->command());
    $factory = $this->createStub(InterventionDraftFactoryPort::class);
    $factory->method('create')->willThrowException(new RuntimeException('action failed'));
    $this->worker($factory)($this->execution($first->attempt->id));
    $next = $this->handler($this->em)(new RetryAutomationAttemptCommand('operator', self::ORG, $this->run, $first->attempt->id));
    self::assertSame(3, $next->attempt->attemptNumber);
    self::assertSame(3, $this->repository($this->em)->countAttempts(self::ORG));
  }

  public function testNativeQueueAndAttemptCommitAndRollBackTogether(): void
  {
    $registry = $this->createStub(\Doctrine\Persistence\ConnectionRegistry::class);
    $registry->method('getConnection')->willReturn($this->em->getConnection());
    $transport = new \Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransportFactory($registry)->createTransport('doctrine://main?queue_name=automation_retry_test&auto_setup=false', ['use_notify' => false], new \Symfony\Component\Messenger\Transport\Serialization\PhpSerializer());
    self::assertInstanceOf(\Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport::class, $transport);
    $transport->setup();
    $this->observer->executeStatement("DELETE FROM messenger_messages WHERE queue_name = 'automation_retry_test'");
    $bus = $this->createStub(\Symfony\Component\Messenger\MessageBusInterface::class);
    $bus->method('dispatch')->willReturnCallback(fn (object $message) => $transport->send(\Symfony\Component\Messenger\Envelope::wrap($message)));
    $sender = new \Automation\Infrastructure\Adapter\Messenger\MessengerAutomationRuleQueueAdapter($bus);
    $queue = $this->createStub(AutomationRuleQueuePort::class);
    $queue->method('enqueue')->willReturnCallback(function (string $rule, string $org, string $subject, array $payload, ?string $attempt) use ($sender): void {
      /** @var array<string, mixed> $payload */
      $sender->enqueue($rule, $org, $subject, $payload, $attempt);
      self::assertSame(0, $this->observer->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'automation_retry_test'"));

      throw new RuntimeException('rollback after send');
    });

    try {
      try {
        $this->handler($this->em, $queue)($this->command());
        self::fail('Simulated interruption must roll back dispatch.');
      } catch (RuntimeException $error) {
        self::assertSame('rollback after send', $error->getMessage());
      }
      self::assertSame(0, $this->observer->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'automation_retry_test'"));
      self::assertSame(1, $this->repository($this->em)->countAttempts(self::ORG));
      $this->handler($this->em, $sender)($this->command());
      self::assertSame(1, $this->observer->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'automation_retry_test'"));
      self::assertSame(2, $this->repository($this->em)->countAttempts(self::ORG));
    } finally {
      $this->observer->executeStatement("DELETE FROM messenger_messages WHERE queue_name = 'automation_retry_test'");
    }
  }

  private function repository(EntityManagerInterface $em): AutomationRunRepository
  {
    return new AutomationRunRepository($em, $this->ids);
  }

  private function command(): RetryAutomationAttemptCommand
  {
    return new RetryAutomationAttemptCommand('operator', self::ORG, $this->run, $this->run);
  }

  private function execution(?string $attempt): ExecuteAutomationRuleCommand
  {
    return new ExecuteAutomationRuleCommand(self::RULE, self::ORG, 'nc', ['inspectionId' => 'inspection', 'nonConformityId' => 'nc', 'severity' => 'critical'], $attempt);
  }

  private function policy(): AutomationPolicyPort
  {
    $policy = $this->createStub(AutomationPolicyPort::class);
    $policy->method('policyFor')->willReturnCallback(fn () => new AutomationPolicy($this->enabled, ['critical' => 1]));

    return $policy;
  }

  private function handler(EntityManagerInterface $em, ?AutomationRuleQueuePort $queue = null): RetryAutomationAttemptHandler
  {
    return new RetryAutomationAttemptHandler($this->repository($em), $this->policy(), $this->createStub(OrganizationAuthorizationPort::class), $queue ?? $this->createStub(AutomationRuleQueuePort::class), new DbalTransactionManagerAdapter($em->getConnection()));
  }

  private function worker(InterventionDraftFactoryPort $factory): ExecuteAutomationRuleHandler
  {
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable());

    return new ExecuteAutomationRuleHandler($this->repository($this->em), $this->policy(), $factory, $this->createStub(EventDispatcherPort::class), $clock, new NullLogger(), new DbalTransactionManagerAdapter($this->em->getConnection()));
  }
}
