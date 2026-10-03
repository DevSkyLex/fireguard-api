<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\Facility;

use Facility\Application\Port\Inbound\{FacilityArchivalGuardPort, FacilityHierarchyPort};
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Application\UseCase\Command\Facility\ArchiveFacility\{ArchiveFacilityCommand, ArchiveFacilityHandler};
use Facility\Application\UseCase\Command\Facility\RestoreFacility\{RestoreFacilityCommand, RestoreFacilityHandler};
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort, TransactionManagerPort};

/**
 * Test FacilityLifecycleLockTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ArchiveFacilityHandler::class)]
#[CoversClass(RestoreFacilityHandler::class)]
final class FacilityLifecycleLockTest extends TestCase
{
  // #region Constants
  private const string ORG = 'f6300000-0000-4000-8000-000000000001';

  private const string ID = 'f6300000-0000-4000-8000-000000000002';
  // #endregion

  // #region Tests
  /**
   * Method testArchivalChecksDependentsInsideTheOrganizationLockAndAuditsAfterCommit.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testArchivalChecksDependentsInsideTheOrganizationLockAndAuditsAfterCommit(): void
  {
    $order = [];
    $facility = $this->facility();
    $repository = $this->repository($facility, $order);
    $archival = $this->createMock(FacilityArchivalGuardPort::class);
    $archival->expects(self::once())->method('assertNoActiveDependents')->with(self::ORG, self::ID)->willReturnCallback(static function () use (&$order): void { $order[] = 'dependents'; });
    $handler = new ArchiveFacilityHandler(
      $repository,
      $this->createStub(OrganizationRepositoryPort::class),
      $this->createStub(NotificationPort::class),
      $this->createStub(LoggerPort::class),
      $archival,
      $this->dispatcher($order),
      $this->hierarchy($order),
      $this->transaction($order),
    );
    $result = $handler(new ArchiveFacilityCommand(self::ORG, self::ID));
    self::assertSame('archived', $result->status);
    self::assertSame(['begin', 'lock', 'read', 'dependents', 'save', 'commit', 'event'], $order);
  }

  /**
   * Method testRestorationReadsAndSavesInsideTheLockAndAuditsAfterCommit.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testRestorationReadsAndSavesInsideTheLockAndAuditsAfterCommit(): void
  {
    $order = [];
    $facility = $this->facility();
    $facility->archive();
    $handler = new RestoreFacilityHandler($this->repository($facility, $order), $this->dispatcher($order), $this->hierarchy($order), $this->transaction($order));
    $result = $handler(new RestoreFacilityCommand(self::ORG, self::ID));
    self::assertSame('active', $result->status);
    self::assertSame(['begin', 'lock', 'read', 'save', 'commit', 'event'], $order);
  }
  // #endregion

  // #region Helpers
  /**
   * Method facility.
   *
   * @since 1.0.0
   */
  private function facility(): Facility
  {
    return Facility::create(new FacilityId(self::ID), new FacilityOrganizationId(self::ORG), FacilityType::SITE, new FacilityName('Site'));
  }

  /**
   * Method repository.
   *
   * @since 1.0.0
   *
   * @param list<string> $order observed operations
   */
  private function repository(Facility $facility, array &$order): FacilityRepositoryPort
  {
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())->method('findPublishedById')->willReturnCallback(static function () use ($facility, &$order): Facility {
      $order[] = 'read';

      return $facility;
    });
    $repository->expects(self::once())->method('save')->willReturnCallback(static function () use (&$order): void { $order[] = 'save'; });

    return $repository;
  }

  /**
   * Method dispatcher.
   *
   * @since 1.0.0
   *
   * @param list<string> $order observed operations
   */
  private function dispatcher(array &$order): EventDispatcherPort
  {
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::once())->method('dispatch')->willReturnCallback(static function () use (&$order): void { $order[] = 'event'; });

    return $dispatcher;
  }

  /**
   * Method hierarchy.
   *
   * @since 1.0.0
   *
   * @param list<string> $order observed operations
   */
  private function hierarchy(array &$order): FacilityHierarchyPort
  {
    $hierarchy = $this->createMock(FacilityHierarchyPort::class);
    $hierarchy->expects(self::once())->method('lock')->with(self::ORG)->willReturnCallback(static function () use (&$order): void { $order[] = 'lock'; });

    return $hierarchy;
  }

  /**
   * Method transaction.
   *
   * @since 1.0.0
   *
   * @param list<string> $order observed operations
   */
  private function transaction(array &$order): TransactionManagerPort
  {
    $transaction = $this->createMock(TransactionManagerPort::class);
    $transaction->expects(self::once())->method('transactional')->willReturnCallback(static function (callable $operation) use (&$order): mixed {
      $order[] = 'begin';
      $result = $operation();
      $order[] = 'commit';

      return $result;
    });

    return $transaction;
  }
  // #endregion
}
