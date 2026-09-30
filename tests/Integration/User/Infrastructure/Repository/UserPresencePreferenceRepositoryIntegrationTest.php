<?php

declare(strict_types=1);

namespace Tests\Integration\User\Infrastructure\Repository;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use User\Application\Contract\Presence\PresencePreferenceChangedEvent;
use User\Application\UseCase\Command\Presence\UpdatePresencePreference\{UpdatePresencePreferenceCommand, UpdatePresencePreferenceHandler};
use User\Infrastructure\Persistence\Doctrine\Repository\UserPresencePreferenceRepository;

/** PostgreSQL proves atomic revisions and independent-connection visibility before delivery.
 * @category Integration Test
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[SkipDatabaseRollback]
final class UserPresencePreferenceRepositoryIntegrationTest extends KernelTestCase
{
  private const string USER = 'fa000000-0000-4000-8000-000000000091';

  private EntityManagerInterface $em;

  private Connection $observer;

  private UserPresencePreferenceRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    $em = self::getContainer()->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $em);
    $this->em = $em;
    $url = $_ENV['AUTH_DATABASE_URL'] ?? $_SERVER['AUTH_DATABASE_URL'] ?? null;
    self::assertIsString($url);
    $this->observer = DriverManager::getConnection(['url' => $url]);
    $this->observer->executeStatement('DELETE FROM user_presence_preferences WHERE user_id = ?', [self::USER]);
    $this->repository = new UserPresencePreferenceRepository($em);
  }

  protected function tearDown(): void
  {
    while ($this->observer->isTransactionActive()) {
      $this->observer->rollBack();
    }
    $this->observer->executeStatement('DELETE FROM user_presence_preferences WHERE user_id = ?', [self::USER]);
    $this->observer->close();
    parent::tearDown();
  }

  #[Test]
  public function defaultsNoOpsAndLastCommittedChangeHaveMonotonicRevisions(): void
  {
    self::assertFalse($this->repository->get(self::USER)->doNotDisturb);
    self::assertSame(0, $this->repository->get(self::USER)->revision);
    self::assertFalse($this->repository->save(self::USER, false)->changed);
    self::assertSame(0, $this->repository->get(self::USER)->revision);
    self::assertSame(1, $this->repository->save(self::USER, true)->preference->revision);
    self::assertFalse($this->repository->save(self::USER, true)->changed);
    self::assertSame(2, $this->repository->save(self::USER, false)->preference->revision);
    $batch = $this->repository->readMany([self::USER, 'fa000000-0000-4000-8000-000000000092']);
    self::assertCount(1, $batch);
    self::assertFalse($batch[self::USER]->doNotDisturb);
    self::assertSame([], $this->repository->readMany([]));
  }

  #[Test]
  public function partialVisibilityWritesPreserveNpdAndIncrementOnlyForChanges(): void
  {
    self::assertFalse($this->repository->get(self::USER)->invisible);
    $this->repository->save(self::USER, true);
    $hidden = $this->repository->save(self::USER, null, true)->preference;
    self::assertTrue($hidden->invisible);
    self::assertTrue($hidden->doNotDisturb);
    self::assertSame(2, $hidden->revision);
    self::assertFalse($this->repository->save(self::USER, null, true)->changed);
    $npdChanged = $this->repository->save(self::USER, false)->preference;
    self::assertTrue($npdChanged->invisible);
    self::assertSame(3, $npdChanged->revision);
    $visible = $this->repository->save(self::USER, null, false)->preference;
    self::assertFalse($visible->invisible);
    self::assertFalse($visible->doNotDisturb);
    self::assertSame(4, $visible->revision);
  }

  #[Test]
  public function independentConnectionSeesCommitBeforeEventDispatch(): void
  {
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::once())->method('dispatch')->willReturnCallback(function (PresencePreferenceChangedEvent $event): void {
      self::assertSame(0, $this->em->getConnection()->getTransactionNestingLevel());
      self::assertSame(1, $this->observer->fetchOne('SELECT revision FROM user_presence_preferences WHERE user_id = ?', [self::USER]));
      self::assertTrue($event->doNotDisturb);
      self::assertSame(1, $event->revision);
    });
    new UpdatePresencePreferenceHandler($this->repository, $events, $this->createStub(LoggerPort::class))(new UpdatePresencePreferenceCommand(self::USER, true));
  }

  #[Test]
  public function competingWriterWaitsForAccountRowAndDoesNotLoseRevisions(): void
  {
    $this->repository->save(self::USER, false);
    $this->observer->beginTransaction();
    $this->observer->executeQuery('SELECT user_id FROM user_presence_preferences WHERE user_id = ? FOR UPDATE', [self::USER])->fetchOne();
    $this->em->getConnection()->executeStatement("SET lock_timeout = '150ms'");

    try {
      $this->repository->save(self::USER, true);
      self::fail('A competing write must wait for the account row lock.');
    } catch (DriverException $exception) {
      self::assertSame('55P03', $exception->getSQLState());
    } finally {
      $this->observer->commit();
      $this->em->getConnection()->executeStatement("SET lock_timeout = '0'");
    }
    self::assertSame(0, $this->repository->get(self::USER)->revision);
    self::assertSame(1, $this->repository->save(self::USER, true)->preference->revision);
    self::assertSame(2, $this->repository->save(self::USER, false)->preference->revision);
  }
}
