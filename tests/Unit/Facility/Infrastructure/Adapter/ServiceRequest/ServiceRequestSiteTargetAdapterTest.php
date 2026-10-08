<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Infrastructure\Adapter\ServiceRequest;

use ArrayObject;
use Customer\Application\Contract\CustomerSnapshot;
use Customer\Application\Port\Inbound\CustomerLookupPort;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Port\Inbound\FacilityHierarchyPort;
use Facility\Infrastructure\Adapter\ServiceRequest\ServiceRequestSiteTargetAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class ServiceRequestSiteTargetAdapterTest
 *
 * Verifies locked archival rechecks and the order of hierarchy, row and customer access.
 *
 * @category Unit Tests
 */
final class ServiceRequestSiteTargetAdapterTest extends TestCase
{
  // #region Methods
  /**
   * Method testLocksHierarchyAndRowsBeforeReadingCustomerIdentity
   *
   * @access public
   *
   * @return void
   */
  public function testLocksHierarchyAndRowsBeforeReadingCustomerIdentity(): void
  {
    /** @var ArrayObject<int,string> $calls */
    $calls = new ArrayObject();
    $connection = $this->createMock(Connection::class);
    $connection->method('isTransactionActive')->willReturn(true);
    $connection->expects(self::exactly(2))->method('fetchAllAssociative')->willReturnCallback(
      static function (string $sql, array $parameters) use ($calls): array {
        $events = $calls->getArrayCopy();
        if ([] === $events || 'hierarchy' !== $events[0]) {
          self::fail('The caller must acquire the hierarchy lock before resolving its target.');
        }
        if (['hierarchy'] === $events) {
          $calls[] = 'ancestry';
          self::assertSame(['target' => 'floor', 'organization' => 'organization'], $parameters);

          return self::ancestry();
        }
        $calls[] = 'rows';
        self::assertStringContainsString('ORDER BY id FOR SHARE', $sql);
        self::assertSame(['organization' => 'organization', 'id0' => 'floor', 'id1' => 'site'], $parameters);

        return [['id' => 'floor', 'status' => 'active'], ['id' => 'site', 'status' => 'active']];
      },
    );
    $hierarchy = $this->createMock(FacilityHierarchyPort::class);
    $hierarchy->expects(self::once())->method('lock')->with('organization')->willReturnCallback(static function () use ($calls): void {
      $calls[] = 'hierarchy';
    });
    $customers = $this->createMock(CustomerLookupPort::class);
    $customers->expects(self::once())->method('find')->with('customer', 'organization')->willReturnCallback(static function () use ($calls): CustomerSnapshot {
      self::assertSame(['hierarchy', 'ancestry', 'rows'], $calls->getArrayCopy());
      $calls[] = 'customer';

      return new CustomerSnapshot('customer', 'Retained customer', [], null);
    });
    $adapter = $this->adapter($connection, $customers, $hierarchy);
    $adapter->lock('organization');
    $target = $adapter->find('organization', 'site', 'floor');
    self::assertNotNull($target);
    self::assertSame(['id' => 'customer', 'name' => 'Retained customer'], $target->customer);
    self::assertSame(['hierarchy', 'ancestry', 'rows', 'customer'], $calls->getArrayCopy());
  }

  /**
   * Method testRetainsAnyArchiveObservedBeforeOrAfterRowLocking
   *
   * @access public
   *
   * @param string $initialStatus status in the ancestry read
   * @param string $lockedStatus status after acquiring shared row locks
   *
   * @return void
   */
  #[DataProvider('archivalChanges')]
  public function testRetainsAnyArchiveObservedBeforeOrAfterRowLocking(string $initialStatus, string $lockedStatus): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->method('isTransactionActive')->willReturn(true);
    $connection->expects(self::exactly(2))->method('fetchAllAssociative')->willReturnOnConsecutiveCalls(
      self::ancestry($initialStatus),
      [['id' => 'floor', 'status' => $lockedStatus], ['id' => 'site', 'status' => 'active']],
    );
    $customers = $this->createStub(CustomerLookupPort::class);
    $customers->method('find')->willReturn(new CustomerSnapshot('customer', 'Customer', [], null));
    $target = $this->adapter($connection, $customers)->find('organization', null, 'floor');
    self::assertNotNull($target);
    self::assertTrue($target->archived);
  }

  /**
   * Method archivalChanges
   *
   * @access public
   *
   * @return iterable<string,array{string,string}> archive transitions
   */
  public static function archivalChanges(): iterable
  {
    yield 'archived while locking' => ['active', 'archived'];
    yield 'initial archive retained' => ['archived', 'active'];
  }

  /**
   * Method testUnavailableLockedAncestryStopsBeforeCustomerLookup
   *
   * @access public
   *
   * @return void
   */
  public function testUnavailableLockedAncestryStopsBeforeCustomerLookup(): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->method('isTransactionActive')->willReturn(true);
    $connection->expects(self::exactly(2))->method('fetchAllAssociative')->willReturnOnConsecutiveCalls(
      self::ancestry(),
      [['id' => 'floor', 'status' => 'active']],
    );
    $customers = $this->createMock(CustomerLookupPort::class);
    $customers->expects(self::never())->method('find');
    self::assertNull($this->adapter($connection, $customers)->find('organization', null, 'floor'));
  }

  /**
   * Method testNonTransactionalReadsRetainResolvedAncestryWithoutRowLocks
   *
   * @access public
   *
   * @return void
   */
  public function testNonTransactionalReadsRetainResolvedAncestryWithoutRowLocks(): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->method('isTransactionActive')->willReturn(false);
    $connection->expects(self::once())->method('fetchAllAssociative')->willReturn(self::ancestry());
    $customers = $this->createStub(CustomerLookupPort::class);
    $customers->method('find')->willReturn(new CustomerSnapshot('customer', 'Customer', [], null));
    $target = $this->adapter($connection, $customers)->find('organization', null, 'floor');
    self::assertNotNull($target);
    self::assertFalse($target->archived);
  }

  /**
   * Method ancestry
   *
   * @access private
   *
   * @param string $status observed descendant status
   *
   * @return list<array{id:string,name:string,type:string,parent_facility_id:?string,customer_id:?string,status:string}> available rows
   */
  private static function ancestry(string $status = 'active'): array
  {
    return [
      ['id' => 'floor', 'name' => 'Floor', 'type' => 'floor', 'parent_facility_id' => 'site', 'customer_id' => null, 'status' => $status],
      ['id' => 'site', 'name' => 'Site', 'type' => 'site', 'parent_facility_id' => null, 'customer_id' => 'customer', 'status' => 'active'],
    ];
  }

  /**
   * Method adapter
   *
   * @access private
   *
   * @param Connection $connection main connection double
   * @param CustomerLookupPort $customers scoped lookup double
   * @param FacilityHierarchyPort|null $hierarchy shared lock double
   *
   * @return ServiceRequestSiteTargetAdapter the adapter under test
   */
  private function adapter(Connection $connection, CustomerLookupPort $customers, ?FacilityHierarchyPort $hierarchy = null): ServiceRequestSiteTargetAdapter
  {
    $manager = $this->createStub(EntityManagerInterface::class);
    $manager->method('getConnection')->willReturn($connection);

    return new ServiceRequestSiteTargetAdapter($manager, $customers, $hierarchy ?? $this->createStub(FacilityHierarchyPort::class));
  }
  // #endregion
}
