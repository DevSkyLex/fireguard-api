<?php

declare(strict_types=1);

namespace Tests\Integration\Approval\Infrastructure\Persistence\Doctrine;

use Approval\Application\Port\Outbound\ApprovalMemberDirectoryPort;
use Approval\Application\UseCase\Command\Decision\WithdrawApprovalRequest\{WithdrawApprovalRequestCommand, WithdrawApprovalRequestHandler};
use Approval\Domain\Exception\ApprovalRequestNotPendingException;
use Approval\Domain\ValueObject\ApprovalRequestId;
use Approval\Infrastructure\Persistence\Doctrine\Lock\PostgresApprovalDecisionLockAdapter;
use Approval\Infrastructure\Persistence\Doctrine\Repository\ApprovalRequestRepository;
use DateTimeImmutable;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\{EntityManager, EntityManagerInterface};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test ApprovalDecisionConcurrencyTest.
 *
 * Uses independent PostgreSQL connections rather than DAMA's shared transaction.
 *
 * @category Integration Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApprovalDecisionConcurrencyTest extends KernelTestCase
{
  private const string ID = 'a9000000-0000-4000-8000-000000000071';

  private Connection $a;

  private Connection $b;

  private EntityManagerInterface $emA;

  private EntityManagerInterface $emB;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $configured */
    $configured = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $url = $_ENV['MAIN_DATABASE_URL'] ?? $_SERVER['MAIN_DATABASE_URL'] ?? null;
    self::assertIsString($url);
    $this->a = DriverManager::getConnection(['url' => $url]);
    $this->b = DriverManager::getConnection(['url' => $url]);
    $this->emA = new EntityManager($this->a, $configured->getConfiguration());
    $this->emB = new EntityManager($this->b, $configured->getConfiguration());
    $this->a->delete('approval_requests', ['id' => self::ID]);
    new ApprovalRequestRepository($this->emA)->reservePending(
      self::ID,
      'a9000000-0000-4000-8000-000000000072',
      'equipment_decommission',
      'a9000000-0000-4000-8000-000000000073',
      'a9000000-0000-4000-8000-000000000074',
      'a9000000-0000-4000-8000-000000000075',
      [],
      new DateTimeImmutable('+1 day'),
      new DateTimeImmutable(),
    );
  }

  protected function tearDown(): void
  {
    foreach ([$this->a, $this->b] as $connection) {
      while ($connection->isTransactionActive()) {
        $connection->rollBack();
      }
    }
    $this->a->delete('approval_requests', ['id' => self::ID]);
    $this->emA->close();
    $this->emB->close();
    $this->a->close();
    $this->b->close();
    parent::tearDown();
  }

  #[Test]
  public function onlyOneDecisionCanReadThePendingRequestAtATime(): void
  {
    $this->b->executeStatement("SET lock_timeout = '150ms'");
    $first = new PostgresApprovalDecisionLockAdapter($this->emA);
    $second = new PostgresApprovalDecisionLockAdapter($this->emB);
    $entered = false;
    $blocked = false;

    $first->synchronized(self::ID, function () use ($second, &$entered, &$blocked): void {
      $this->a->update('approval_requests', ['status' => 'approved'], ['id' => self::ID]);

      try {
        $second->synchronized(self::ID, static function () use (&$entered): void {
          $entered = true;
        });
      } catch (DriverException $exception) {
        self::assertSame('55P03', $exception->getSQLState());
        $blocked = true;
      }
    });

    self::assertTrue($blocked);
    self::assertFalse($entered);
    self::assertSame('approved', $this->b->fetchOne('SELECT status FROM approval_requests WHERE id = ?', [self::ID]));
  }

  #[Test]
  public function rereadsTheWinnerEvenWhenDoctrineAlreadyCachedTheRequest(): void
  {
    $repository = new ApprovalRequestRepository($this->emB);
    $id = ApprovalRequestId::fromString(self::ID);
    self::assertTrue($repository->findById($id)?->isPending());
    $this->a->update('approval_requests', ['status' => 'rejected'], ['id' => self::ID]);

    new PostgresApprovalDecisionLockAdapter($this->emB)->synchronized(self::ID, static function () use ($repository, $id): void {
      self::assertSame('rejected', $repository->findById($id)->status()->value);
    });
  }

  #[Test]
  public function rollsBackAllWritesWhenTheDecisionFailsTechnically(): void
  {
    try {
      new PostgresApprovalDecisionLockAdapter($this->emA)->synchronized(self::ID, function (): never {
        $this->a->update('approval_requests', ['status' => 'approved', 'decision_note' => 'must roll back'], ['id' => self::ID]);

        throw new RuntimeException('persistence unavailable');
      });
    } catch (RuntimeException $exception) {
      self::assertSame('persistence unavailable', $exception->getMessage());
    }

    self::assertSame('pending', $this->b->fetchOne('SELECT status FROM approval_requests WHERE id = ?', [self::ID]));
    self::assertNull($this->b->fetchOne('SELECT decision_note FROM approval_requests WHERE id = ?', [self::ID]));
  }

  #[Test]
  public function withdrawalCompetesWithDecisionsAndRereadsTheCommittedWinner(): void
  {
    $members = $this->createStub(ApprovalMemberDirectoryPort::class);
    $members->method('resolveMemberId')->willReturn('a9000000-0000-4000-8000-000000000074');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable());
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');
    $repository = new ApprovalRequestRepository($this->emB);
    self::assertTrue($repository->findById(ApprovalRequestId::fromString(self::ID))?->isPending());
    $handler = new WithdrawApprovalRequestHandler($repository, $members, $authorization, $events, $clock, new PostgresApprovalDecisionLockAdapter($this->emB));
    $command = new WithdrawApprovalRequestCommand('a9000000-0000-4000-8000-000000000072', self::ID, 'a9000000-0000-4000-8000-000000000075');
    $this->b->executeStatement("SET lock_timeout = '150ms'");
    new PostgresApprovalDecisionLockAdapter($this->emA)->synchronized(self::ID, function () use ($handler, $command): void {
      $this->a->update('approval_requests', ['status' => 'approved'], ['id' => self::ID]);

      try {
        $handler($command);
        self::fail('Withdrawal must wait for the competing decision.');
      } catch (DriverException $exception) {
        self::assertSame('55P03', $exception->getSQLState());
      }
    });

    // A lock timeout aborts its ORM transaction: the next request gets a fresh manager.
    $this->emB = new EntityManager($this->b, $this->emA->getConfiguration());
    $handler = new WithdrawApprovalRequestHandler(new ApprovalRequestRepository($this->emB), $members, $authorization, $events, $clock, new PostgresApprovalDecisionLockAdapter($this->emB));

    try {
      $handler($command);
      self::fail('Withdrawal cannot overwrite the winning decision.');
    } catch (ApprovalRequestNotPendingException) {
      self::assertSame('approved', $this->b->fetchOne('SELECT status FROM approval_requests WHERE id = ?', [self::ID]));
    }
  }

  #[Test]
  public function commitsExpirationBeforeReturningABusinessRefusal(): void
  {
    $result = new PostgresApprovalDecisionLockAdapter($this->emA)->synchronized(self::ID, function (): ApprovalRequestNotPendingException {
      $this->a->update('approval_requests', ['status' => 'expired'], ['id' => self::ID]);

      return ApprovalRequestNotPendingException::withId(self::ID);
    });

    self::assertInstanceOf(ApprovalRequestNotPendingException::class, $result);
    self::assertSame('expired', $this->b->fetchOne('SELECT status FROM approval_requests WHERE id = ?', [self::ID]));
  }
}
