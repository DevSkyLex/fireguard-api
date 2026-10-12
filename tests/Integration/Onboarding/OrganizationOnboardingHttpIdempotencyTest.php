<?php

declare(strict_types=1);

namespace Tests\Integration\Onboarding;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\ORM\EntityManagerInterface;
use Onboarding\Application\Contract\Setup\OrganizationSetupOperation;
use Onboarding\Application\Contract\Step\{OrganizationOnboardingStepContext, OrganizationOnboardingStepDefinition};
use Onboarding\Application\Port\Inbound\OrganizationOnboardingStepContributionPort;
use Onboarding\Application\Service\{OrganizationOnboardingFlowService, OrganizationOnboardingSessionState, OrganizationOnboardingStepRegistry};
use Onboarding\Application\UseCase\Command\Flow\DismissOrganizationOnboarding\{DismissOrganizationOnboardingCommand, DismissOrganizationOnboardingHandler, DismissOrganizationOnboardingResult};
use Onboarding\Application\UseCase\Command\Flow\ResumeOrganizationOnboarding\{ResumeOrganizationOnboardingCommand, ResumeOrganizationOnboardingHandler, ResumeOrganizationOnboardingResult};
use Onboarding\Application\UseCase\Command\Flow\StartOrganizationOnboarding\{StartOrganizationOnboardingCommand, StartOrganizationOnboardingHandler, StartOrganizationOnboardingResult};
use Onboarding\Application\UseCase\Command\Flow\SynchronizeOrganizationOnboarding\{SynchronizeOrganizationOnboardingCommand, SynchronizeOrganizationOnboardingHandler, SynchronizeOrganizationOnboardingResult};
use Onboarding\Domain\Model\OrganizationOnboardingSession\OrganizationOnboardingSession;
use Onboarding\Infrastructure\Persistence\Doctrine\Repository\OrganizationOnboardingSessionRepository;
use Organization\Application\Port\Inbound\OrganizationOnboardingDirectoryPort;
use PHPUnit\Framework\Attributes\Test;
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyScope};
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, UuidGeneratorPort};
use Shared\Application\Service\Idempotency\DurableHttpIdempotencyService;
use Shared\Infrastructure\Crypto\VersionedAesGcmAdapter;
use Shared\Infrastructure\Idempotency\{HmacIdempotencyFingerprintAdapter, HttpIdempotencyExecutionContext, HttpIdempotencyResultCodec};
use Shared\Infrastructure\Persistence\Doctrine\Repository\DoctrineIdempotencyReceiptRepository;
use Shared\Infrastructure\Symfony\Adapter\Outbound\DoctrineTransactionManagerAdapter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Tests\Support\Database\RootCommitDatabaseIsolationTrait;

use function base64_encode;
use function get_object_vars;
use function is_int;
use function is_string;
use function str_repeat;

/**
 * Proves that onboarding synchronization/reset and their typed results share a real main commit.
 *
 * @category Integration Test
 */
#[SkipDatabaseRollback]
final class OrganizationOnboardingHttpIdempotencyTest extends KernelTestCase
{
  use RootCommitDatabaseIsolationTrait;

  private const string USER = 'aa0e8400-e29b-41d4-a716-4466554c0801';

  private const string SESSION = 'aa0e8400-e29b-41d4-a716-4466554c0802';

  /**
   * @var list<Connection> independent test connections
   */
  private array $connections = [];

  protected function tearDown(): void
  {
    foreach ($this->connections as $connection) {
      $connection->close();
    }
    $this->connections = [];
    parent::tearDown();
  }

  #[Test]
  public function statefulGetCommitsItsProjectionAndReplayNeverSynchronizesAgain(): void
  {
    [$flow, $context, $engine, $repository, $observer, $operation] = $this->fixture(
      SynchronizeOrganizationOnboardingCommand::class,
      SynchronizeOrganizationOnboardingResult::class,
    );
    $scope = new IdempotencyScope(self::USER, null, null, $operation->name, '/api/onboarding/organization');
    $context->activate($operation, $engine, $engine->begin($scope, 'get-key', []), []);
    $result = $context->dispatchPrimary(
      new SynchronizeOrganizationOnboardingCommand(self::USER),
      fn (): ResultMessage => new SynchronizeOrganizationOnboardingHandler($flow)(new SynchronizeOrganizationOnboardingCommand(self::USER)),
    );
    self::assertInstanceOf(SynchronizeOrganizationOnboardingResult::class, $result);
    self::assertSame(self::SESSION, $result->state->sessionId);
    self::assertSame('external_setup', $result->state->nextStep);
    self::assertSame(self::SESSION, $observer->fetchOne('SELECT id FROM organization_onboarding_sessions WHERE user_id = ?', [self::USER]));
    self::assertSame('completed', $observer->fetchOne('SELECT state FROM http_idempotency_receipts'));
    $context->deactivate();

    // Another logical action changes the live state after this GET committed.
    $session = $repository->findByUserId(self::USER);
    self::assertInstanceOf(OrganizationOnboardingSession::class, $session);
    $session->dismiss();
    $repository->save($session);
    $liveUpdatedAt = $observer->fetchOne('SELECT updated_at FROM organization_onboarding_sessions WHERE user_id = ?', [self::USER]);

    $context->activate($operation, $engine, $engine->begin($scope, 'get-key', []), []);
    $replayed = $context->dispatchPrimary(new SynchronizeOrganizationOnboardingCommand(self::USER), static function (): ResultMessage {
      self::fail('A committed GET retry must not synchronize the flow.');
    });
    self::assertInstanceOf(SynchronizeOrganizationOnboardingResult::class, $replayed);
    self::assertEquals($result, $replayed);
    self::assertFalse($replayed->state->dismissed);
    self::assertSame($liveUpdatedAt, $observer->fetchOne('SELECT updated_at FROM organization_onboarding_sessions WHERE user_id = ?', [self::USER]));
    self::assertNotNull($observer->fetchOne('SELECT dismissed_at FROM organization_onboarding_sessions WHERE user_id = ?', [self::USER]));
  }

  #[Test]
  public function resetRetryReturnsTheCommittedSessionWithoutResettingNewProgress(): void
  {
    [$flow, $context, $engine, $repository, $observer, $operation] = $this->fixture(
      StartOrganizationOnboardingCommand::class,
      StartOrganizationOnboardingResult::class,
    );
    $repository->save(OrganizationOnboardingSession::start('aa0e8400-e29b-41d4-a716-4466554c0803', self::USER, firstStep: 'external_setup'));
    $scope = new IdempotencyScope(self::USER, null, null, $operation->name, '/api/onboarding/organization/start');
    $context->activate($operation, $engine, $engine->begin($scope, 'reset-key', ['reset' => true]), []);
    $command = new StartOrganizationOnboardingCommand(self::USER, reset: true, intent: 'create');
    $result = $context->dispatchPrimary($command, fn (): ResultMessage => new StartOrganizationOnboardingHandler($flow)($command));
    self::assertInstanceOf(StartOrganizationOnboardingResult::class, $result);
    self::assertSame(self::SESSION, $result->state->sessionId);
    $context->deactivate();
    $session = $repository->findByUserId(self::USER);
    self::assertInstanceOf(OrganizationOnboardingSession::class, $session);
    $session->dismiss();
    $repository->save($session);
    $context->activate($operation, $engine, $engine->begin($scope, 'reset-key', ['reset' => true]), []);
    $replayed = $context->dispatchPrimary($command, static function (): ResultMessage {
      self::fail('A committed reset retry must not delete a session again.');
    });
    self::assertEquals($result, $replayed);
    self::assertSame(self::SESSION, $observer->fetchOne('SELECT id FROM organization_onboarding_sessions WHERE user_id = ?', [self::USER]));
    self::assertNotNull($observer->fetchOne('SELECT dismissed_at FROM organization_onboarding_sessions WHERE user_id = ?', [self::USER]));
    self::assertSame(1, $this->countSessions($observer));
  }

  #[Test]
  public function dismissAndResumeSnapshotsMatchTheCommittedPreferenceAfterReload(): void
  {
    [$flow, $context, $engine, $repository, $observer, $schema] = $this->fixture(
      DismissOrganizationOnboardingCommand::class,
      DismissOrganizationOnboardingResult::class,
    );
    $flow->getFlow(self::USER);
    foreach ([
      [new DismissOrganizationOnboardingCommand(self::USER), new DismissOrganizationOnboardingHandler($flow), DismissOrganizationOnboardingResult::class, true, 'dismiss'],
      [new ResumeOrganizationOnboardingCommand(self::USER), new ResumeOrganizationOnboardingHandler($flow), ResumeOrganizationOnboardingResult::class, false, 'resume'],
    ] as [$command, $handler, $resultClass, $dismissed, $action]) {
      $operation = new HttpIdempotencyOperation($action, 'main', 'bus_owner', $command::class, $resultClass, $schema->contractClasses);
      $scope = new IdempotencyScope(self::USER, null, null, $action, '/api/onboarding/organization/' . $action);
      $context->activate($operation, $engine, $engine->begin($scope, $action . '-key', []), []);
      $result = $context->dispatchPrimary($command, static fn (): ResultMessage => $handler($command));
      $state = get_object_vars($result)['state'];
      self::assertInstanceOf(OrganizationOnboardingSessionState::class, $state);
      self::assertSame($dismissed, $state->dismissed);
      self::assertSame($dismissed, null !== $observer->fetchOne('SELECT dismissed_at FROM organization_onboarding_sessions WHERE user_id = ?', [self::USER]));
      $context->deactivate();
      $session = $repository->findByUserId(self::USER);
      self::assertInstanceOf(OrganizationOnboardingSession::class, $session);
      self::assertSame($dismissed, $session->isDismissed());
      $replay = $engine->begin($scope, $action . '-key', [])->replay;
      self::assertNotNull($replay);
      self::assertEquals($result, new HttpIdempotencyResultCodec()->decode($replay->body, $operation));
    }
  }

  #[Test]
  public function unavailableResultSchemaRollsBackTheSessionInsteadOfCommittingWithoutAReceipt(): void
  {
    [$flow, $context, $engine, , $observer, $operation] = $this->fixture(
      SynchronizeOrganizationOnboardingCommand::class,
      SynchronizeOrganizationOnboardingResult::class,
      withSchema: false,
    );
    $scope = new IdempotencyScope(self::USER, null, null, $operation->name, '/api/onboarding/organization');
    $context->activate($operation, $engine, $engine->begin($scope, 'get-key', []), []);

    try {
      $context->dispatchPrimary(
        new SynchronizeOrganizationOnboardingCommand(self::USER),
        fn (): ResultMessage => new SynchronizeOrganizationOnboardingHandler($flow)(new SynchronizeOrganizationOnboardingCommand(self::USER)),
      );
      self::fail('An undeclared state schema must refuse the final commit.');
    } catch (IdempotencyDeniedException $exception) {
      self::assertSame('result_unavailable', $exception->reason);
    }
    self::assertSame(0, $this->countSessions($observer));
    self::assertSame('pending', $observer->fetchOne('SELECT state FROM http_idempotency_receipts'));
    self::assertNull($observer->fetchOne('SELECT result_payload FROM http_idempotency_receipts'));
  }

  private function countSessions(Connection $observer): int
  {
    $count = $observer->fetchOne('SELECT COUNT(*) FROM organization_onboarding_sessions WHERE user_id = ?', [self::USER]);
    self::assertTrue(is_int($count) || is_string($count));

    return (int) $count;
  }

  /**
   * @param class-string<CommandMessage> $commandClass owner primary command
   * @param class-string<ResultMessage> $resultClass owner result schema
   *
   * @return array{OrganizationOnboardingFlowService, HttpIdempotencyExecutionContext, DurableHttpIdempotencyService, OrganizationOnboardingSessionRepository, Connection, HttpIdempotencyOperation}
   */
  private function fixture(string $commandClass, string $resultClass, bool $withSchema = true): array
  {
    self::bootKernel();
    $manager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);
    $main = $manager->getConnection();
    $url = $_ENV['MAIN_DATABASE_URL'] ?? null;
    self::assertIsString($url);
    $reservation = DriverManager::getConnection(['url' => $url]);
    $observer = DriverManager::getConnection(['url' => $url]);
    $this->connections = [$reservation, $observer];
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-11T12:00:00Z'));
    $fingerprints = new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('h', 32)));
    $engine = new DurableHttpIdempotencyService(
      new DoctrineIdempotencyReceiptRepository($main, $reservation),
      $fingerprints,
      new VersionedAesGcmAdapter(['test' => base64_encode(str_repeat('k', 32))], 'test'),
      $clock,
    );
    $stack = new RequestStack();
    $stack->push(new Request());
    $context = new HttpIdempotencyExecutionContext($stack, new HttpIdempotencyResultCodec(), $fingerprints, $main, $this->createStub(Connection::class), $manager);
    $repository = new OrganizationOnboardingSessionRepository($manager);
    $directory = $this->createStub(OrganizationOnboardingDirectoryPort::class);
    $directory->method('organizationsForUser')->willReturn([]);
    $ids = $this->createMock(UuidGeneratorPort::class);
    $ids->expects(self::once())->method('generate')->willReturn(self::SESSION);
    $step = new class () implements OrganizationOnboardingStepContributionPort {
      public static function definition(): OrganizationOnboardingStepDefinition
      {
        return new OrganizationOnboardingStepDefinition('external_setup', 'External setup', 1, true);
      }

      public function evaluate(OrganizationOnboardingStepContext $context): bool
      {
        return false;
      }

      public function execute(OrganizationOnboardingStepContext $context): void
      {
      }
    };
    $flow = new OrganizationOnboardingFlowService(
      $repository,
      $directory,
      new OrganizationOnboardingStepRegistry([$step]),
      $ids,
      new DoctrineTransactionManagerAdapter($manager, $context, 'main'),
      $this->createStub(EventDispatcherPort::class),
    );
    $operation = new HttpIdempotencyOperation(
      name: 'onboarding_test',
      database: 'main',
      transactionMode: 'bus_owner',
      primaryCommand: $commandClass,
      resultClass: $resultClass,
      contractClasses: $withSchema ? [OrganizationOnboardingSessionState::class, OrganizationSetupOperation::class, OrganizationOnboardingStepDefinition::class] : [],
      resultIdentifierProperty: 'organizationId',
      resourceKind: 'organization',
      resultIdentifierNullable: true,
    );

    return [$flow, $context, $engine, $repository, $observer, $operation];
  }
}
