<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\Facility\MoveFacility;

use DateTimeImmutable;
use Facility\Application\Port\Inbound\FacilityHierarchyPort;
use Facility\Application\Port\Outbound\{CanonicalFacilityRepositoryPort, FacilityRepositoryPort};
use Facility\Application\UseCase\Command\Facility\MoveFacility\{MoveFacilityCommand, MoveFacilityHandler};
use Facility\Domain\Event\Facility\FacilityMovedEvent;
use Facility\Domain\Exception\FacilityRevisionMismatchException;
use Facility\Domain\Model\Facility\{CanonicalFacility, CanonicalFacilityContent, CanonicalFacilityReference, CanonicalFacilityVersion, Facility};
use Facility\Domain\ValueObject\{FacilityId, FacilityName, FacilityOrganizationId, FacilityRecordStatus, FacilityStatus, FacilityType};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};

/**
 * Test MoveFacilityConsistencyTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MoveFacilityHandler::class)]
final class MoveFacilityConsistencyTest extends TestCase
{
  // #region Constants
  private const string ORG = 'f6000000-0000-4000-8000-000000000001';

  private const string ID = 'f6000000-0000-4000-8000-000000000002';

  private const string PARENT = 'f6000000-0000-4000-8000-000000000003';
  // #endregion

  // #region Tests
  /**
   * Method testTheMoveChecksAndPersistsWithinTheLockAndAuditsAfterCommit.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testTheMoveChecksAndPersistsWithinTheLockAndAuditsAfterCommit(): void
  {
    $order = [];
    $canonical = $this->createMock(CanonicalFacilityRepositoryPort::class);
    $canonical->method('findById')->willReturn($this->facility());
    $canonical->expects(self::once())->method('save')->willReturnCallback(static function (CanonicalFacility $facility) use (&$order): void {
      self::assertSame(self::PARENT, $facility->parentFacilityId());
      self::assertSame(4, $facility->revision());
      $order[] = 'save';
    });
    $hierarchy = $this->createMock(FacilityHierarchyPort::class);
    $hierarchy->expects(self::once())->method('lock')->with(self::ORG)->willReturnCallback(static function () use (&$order): void { $order[] = 'lock'; });
    $hierarchy->expects(self::once())->method('assertGraph')->willReturnCallback(static function () use (&$order): void { $order[] = 'validate'; });
    $transaction = $this->createMock(TransactionManagerPort::class);
    $transaction->expects(self::once())->method('transactional')->willReturnCallback(static function (callable $operation) use (&$order): mixed {
      $order[] = 'begin';
      $result = $operation();
      $order[] = 'commit';

      return $result;
    });
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::once())->method('dispatch')->with(self::isInstanceOf(FacilityMovedEvent::class))->willReturnCallback(static function () use (&$order): void { $order[] = 'event'; });
    $result = $this->handler($canonical, $hierarchy, $transaction, $dispatcher)(new MoveFacilityCommand(self::ORG, self::ID, self::PARENT, 3));
    self::assertSame(self::PARENT, $result->parentFacilityId);
    self::assertSame(['begin', 'lock', 'validate', 'save', 'commit', 'event'], $order);
  }

  /**
   * Method testStaleRevisionsNeverSaveOrAudit.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testStaleRevisionsNeverSaveOrAudit(): void
  {
    $canonical = $this->createMock(CanonicalFacilityRepositoryPort::class);
    $canonical->method('findById')->willReturn($this->facility());
    $canonical->expects(self::never())->method('save');
    $hierarchy = $this->createMock(FacilityHierarchyPort::class);
    $hierarchy->expects(self::once())->method('lock');
    $hierarchy->expects(self::never())->method('assertGraph');
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::never())->method('dispatch');
    $this->expectException(FacilityRevisionMismatchException::class);
    $this->handler($canonical, $hierarchy, $this->transaction(), $dispatcher)(new MoveFacilityCommand(self::ORG, self::ID, self::PARENT, 2));
  }

  /**
   * Method testAnUnchangedParentDoesNotBumpTheRevisionOrEmitAnEvent.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testAnUnchangedParentDoesNotBumpTheRevisionOrEmitAnEvent(): void
  {
    $facility = $this->facility(self::PARENT);
    $canonical = $this->createMock(CanonicalFacilityRepositoryPort::class);
    $canonical->method('findById')->willReturn($facility);
    $canonical->expects(self::never())->method('save');
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::never())->method('dispatch');
    $this->handler($canonical, $this->createStub(FacilityHierarchyPort::class), $this->transaction(), $dispatcher)(new MoveFacilityCommand(self::ORG, self::ID, self::PARENT, 3));
    self::assertSame(3, $facility->revision());
  }
  // #endregion

  // #region Helpers
  /**
   * Method handler.
   *
   * @since 1.0.0
   */
  private function handler(CanonicalFacilityRepositoryPort $canonical, FacilityHierarchyPort $hierarchy, TransactionManagerPort $transaction, EventDispatcherPort $dispatcher): MoveFacilityHandler
  {
    $repository = $this->createStub(FacilityRepositoryPort::class);
    $repository->method('findPublishedById')->willReturn(Facility::create(new FacilityId(self::ID), new FacilityOrganizationId(self::ORG), FacilityType::ZONE, new FacilityName('Zone')));

    return new MoveFacilityHandler($repository, $dispatcher, hierarchy: $hierarchy, transactionManager: $transaction, canonicalFacilities: $canonical);
  }

  /**
   * Method transaction.
   *
   * @since 1.0.0
   */
  private function transaction(): TransactionManagerPort
  {
    $transaction = $this->createStub(TransactionManagerPort::class);
    $transaction->method('transactional')->willReturnCallback(static fn (callable $operation): mixed => $operation());

    return $transaction;
  }

  /**
   * Method facility.
   *
   * @since 1.0.0
   */
  private function facility(?string $parent = null): CanonicalFacility
  {
    return CanonicalFacility::reconstitute(
      new CanonicalFacilityReference(new FacilityId(self::ID), new FacilityOrganizationId(self::ORG), FacilityRecordStatus::PUBLISHED, null, $parent),
      new CanonicalFacilityContent(FacilityType::ZONE, 'Zone', null, null, null, null, []),
      new CanonicalFacilityVersion(FacilityStatus::ACTIVE, 3, new DateTimeImmutable()),
    );
  }
  // #endregion
}
