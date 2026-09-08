<?php

declare(strict_types=1);

namespace Tests\Integration\Onboarding;

use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\{EntityManager, EntityManagerInterface};
use Onboarding\Application\Contract\Setup\OrganizationSetupContext;
use Onboarding\Application\Service\OrganizationSetupService;
use Onboarding\Infrastructure\Persistence\Doctrine\Repository\OrganizationSetupRepository;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Shared\Infrastructure\Symfony\Adapter\Outbound\DoctrineTransactionManagerAdapter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function is_string;

/**
 * Durable organization setup recovery.
 *
 * @category IntegrationTest
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationSetupConcurrencyIntegrationTest extends KernelTestCase
{
  private const string SESSION = 'cc11c711-0000-4000-8000-000000000001';

  private const string USER = 'cc11c711-0000-4000-8000-000000000002';

  private Connection $a;

  private Connection $b;

  private OrganizationSetupService $setupA;

  private OrganizationSetupService $setupB;

  protected function setUp(): void
  {
    self::bootKernel();
    $url = $_ENV['MAIN_DATABASE_URL'] ?? $_SERVER['MAIN_DATABASE_URL'] ?? null;
    if (!is_string($url) || '' === $url) {
      throw new RuntimeException('Main test database URL is required.');
    }
    $this->a = DriverManager::getConnection(['url' => $url]);
    $this->b = DriverManager::getConnection(['url' => $url]);
    $this->a->executeStatement('DELETE FROM organization_onboarding_sessions WHERE id = ?', [self::SESSION]);
    $this->a->executeStatement("INSERT INTO organization_onboarding_sessions (id,user_id,flow,state,next_step,creation_intent,completed_steps,skipped_steps,rollback_stack,step_history,created_at,updated_at) VALUES (?,?,'organization','in_progress','create_organization',true,'[]','[]','[]','[]',NOW(),NOW())", [self::SESSION, self::USER]);
    $em = static::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $em);
    $emA = new EntityManager($this->a, $em->getConfiguration());
    $emB = new EntityManager($this->b, $em->getConfiguration());
    $this->setupA = new OrganizationSetupService(new OrganizationSetupRepository($emA), new DoctrineTransactionManagerAdapter($emA));
    $this->setupB = new OrganizationSetupService(new OrganizationSetupRepository($emB), new DoctrineTransactionManagerAdapter($emB));
    $this->setupA->prepare(self::USER, self::SESSION, 'create_organization', [['itemKey' => 'org', 'payload' => ['name' => 'Atomic setup']]]);
  }

  protected function tearDown(): void
  {
    foreach ([$this->a, $this->b] as $connection) {
      if ($connection->isTransactionActive()) {
        $connection->rollBack();
      }
    }
    $this->a->executeStatement('DELETE FROM organization_onboarding_sessions WHERE id = ?', [self::SESSION]);
    $this->a->close();
    $this->b->close();
    parent::tearDown();
  }

  #[Test]
  public function overlappingCreatesWaitThenObserveTheSameCommittedResult(): void
  {
    $context = new OrganizationSetupContext(self::USER, self::SESSION, 'org');
    $this->a->beginTransaction();
    self::assertNull($this->setupA->begin($context, 'create_organization', null, ['name' => 'Atomic setup'])->resourceId);
    $this->b->executeStatement("SET lock_timeout = '150ms'");
    $this->b->beginTransaction();
    $blocked = false;

    try {
      $this->setupB->begin($context, 'create_organization', null, ['name' => 'Atomic setup']);
    } catch (DriverException $exception) {
      self::assertSame('55P03', $exception->getSQLState());
      $blocked = true;
    }
    self::assertTrue($blocked, 'The second creator must not pass the check while the first transaction is uncommitted.');
    $this->b->rollBack();
    $this->setupA->complete($context, 'create_organization', 'created-resource');
    $this->a->commit();
    $this->b->beginTransaction();
    self::assertSame('created-resource', $this->setupB->begin($context, 'create_organization', null, ['name' => 'Atomic setup'])->resourceId);
    $this->b->commit();
  }

  #[Test]
  public function earlierArrayJournalsRemainReadableAndClearingDoesNotRestoreLegacyAdoption(): void
  {
    $manager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);
    $repository = new OrganizationSetupRepository(new EntityManager($this->a, $manager->getConfiguration()));
    $this->a->executeStatement('UPDATE organization_onboarding_sessions SET setup_operations = ? WHERE id = ?', ['[{"stepKey":"create_organization","itemKey":"org","payload":{"name":"Earlier format"},"resourceId":"saved-id"}]', self::SESSION]);
    self::assertTrue($repository->hasJournal(self::SESSION));
    self::assertSame('saved-id', $repository->listOperations(self::SESSION)[0]->resourceId);
    $this->a->beginTransaction();
    $repository->saveOperations(self::SESSION, []);
    $this->a->commit();
    self::assertTrue($repository->hasJournal(self::SESSION));
    self::assertSame([], $repository->listOperations(self::SESSION));
  }

  #[Test]
  public function aFailedOwnerTransactionLeavesTheInputPreparedForRetry(): void
  {
    $context = new OrganizationSetupContext(self::USER, self::SESSION, 'org');
    $this->a->beginTransaction();
    $this->setupA->begin($context, 'create_organization', null, ['name' => 'Atomic setup']);
    $this->setupA->complete($context, 'create_organization', 'never-committed');
    $this->a->rollBack();
    $this->b->beginTransaction();
    $operation = $this->setupB->begin($context, 'create_organization', null, ['name' => 'Atomic setup']);
    self::assertNull($operation->resourceId);
    self::assertSame('Atomic setup', $operation->payload['name']);
    $this->b->commit();
  }
}
