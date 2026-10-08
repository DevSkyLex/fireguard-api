<?php

declare(strict_types=1);

namespace Tests\Integration\Facility\Infrastructure\Adapter\ServiceRequest;

use Customer\Application\Contract\CustomerSnapshot;
use Customer\Application\Port\Inbound\CustomerLookupPort;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Port\Inbound\FacilityHierarchyPort;
use Facility\Infrastructure\Adapter\ServiceRequest\ServiceRequestSiteTargetAdapter;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function str_pad;

use const STR_PAD_LEFT;

/**
 * Class ServiceRequestSiteTargetAdapterTest
 *
 * Exercises organization, publication, cycle and depth fencing with real PostgreSQL ancestry queries.
 *
 * @category Integration Tests
 */
final class ServiceRequestSiteTargetAdapterTest extends KernelTestCase
{
  // #region Constants
  /**
   * Constant ORGANIZATION
   */
  private const string ORGANIZATION = 'c7200000-0000-4000-8000-000000000001';

  /**
   * Constant OTHER_ORGANIZATION
   */
  private const string OTHER_ORGANIZATION = 'c7200000-0000-4000-8000-000000000002';

  /**
   * Constant SITE
   */
  private const string SITE = 'c7200000-0000-4000-8000-000000000010';

  /**
   * Constant BUILDING
   */
  private const string BUILDING = 'c7200000-0000-4000-8000-000000000011';

  /**
   * Constant CUSTOMER
   */
  private const string CUSTOMER = 'c7200000-0000-4000-8000-000000000012';
  // #endregion

  // #region Properties
  /**
   * Property manager
   */
  private EntityManagerInterface $manager;

  /**
   * Property organization
   */
  private OrganizationRecord $organization;

  /**
   * Property root
   */
  private FacilityRecord $root;

  /**
   * Property building
   */
  private FacilityRecord $building;
  // #endregion

  // #region Methods
  /**
   * Method setUp
   *
   * @access protected
   *
   * @return void
   */
  protected function setUp(): void
  {
    self::bootKernel();
    $manager = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);
    $this->manager = $manager;
    $this->organization = $this->organization(self::ORGANIZATION);
    $this->root = $this->facility(self::SITE, 'site');
    $this->building = $this->facility(self::BUILDING, 'building', $this->root);
    $this->manager->flush();
  }

  /**
   * Method testDerivesMinimalRootIdentityAndRetainsArchivedAncestry
   *
   * @access public
   *
   * @return void
   */
  public function testDerivesMinimalRootIdentityAndRetainsArchivedAncestry(): void
  {
    $this->root->customerId = self::CUSTOMER;
    $this->building->status = 'archived';
    $this->manager->flush();
    $customers = $this->createMock(CustomerLookupPort::class);
    $customers->expects(self::once())->method('find')->with(self::CUSTOMER, self::ORGANIZATION)->willReturn(
      new CustomerSnapshot(self::CUSTOMER, 'Retained customer', [['name' => 'Hidden contact', 'email' => 'private@example.com', 'phone' => null, 'role' => null]], new DateTimeImmutable('2026-10-01T10:00:00+00:00')),
    );
    $target = $this->adapter($customers)->find(self::ORGANIZATION, self::SITE, self::BUILDING);
    self::assertNotNull($target);
    self::assertSame(self::SITE, $target->id);
    self::assertSame('site target', $target->name);
    self::assertTrue($target->archived);
    self::assertSame(['id' => self::CUSTOMER, 'name' => 'Retained customer'], $target->customer);
  }

  /**
   * Method testUnassignedRootAndAbsentTargetRemainDistinct
   *
   * @access public
   *
   * @return void
   */
  public function testUnassignedRootAndAbsentTargetRemainDistinct(): void
  {
    $customers = $this->createMock(CustomerLookupPort::class);
    $customers->expects(self::never())->method('find');
    $adapter = $this->adapter($customers);
    $target = $adapter->find(self::ORGANIZATION, self::SITE, null);
    self::assertNotNull($target);
    self::assertNull($target->customer);
    self::assertFalse($target->archived);
    self::assertNull($adapter->find(self::ORGANIZATION, null, null));
  }

  /**
   * Method testUnavailableAncestryCannotExposeRootOrCustomer
   *
   * @access public
   *
   * @param string $scenario unavailable target or ancestry
   *
   * @return void
   */
  #[DataProvider('unavailableAncestries')]
  public function testUnavailableAncestryCannotExposeRootOrCustomer(string $scenario): void
  {
    $siteId = self::SITE;
    $organizationId = self::ORGANIZATION;
    $this->root->customerId = self::CUSTOMER;
    if ('foreign target' === $scenario) {
      $organizationId = self::OTHER_ORGANIZATION;
    } elseif ('foreign ancestor' === $scenario) {
      $this->root->organization = $this->organization(self::OTHER_ORGANIZATION);
    } elseif ('draft target' === $scenario) {
      $this->building->recordStatus = 'draft';
    } elseif ('draft ancestor' === $scenario) {
      $this->root->recordStatus = 'draft';
    } elseif ('cycle' === $scenario) {
      $this->root->parentFacility = $this->building;
      $this->root->customerId = null;
    } elseif ('non-site root' === $scenario) {
      $this->root->type = 'building';
      $this->root->customerId = null;
    } else {
      $siteId = self::CUSTOMER;
    }
    $this->manager->flush();
    $customers = $this->createMock(CustomerLookupPort::class);
    $customers->expects(self::never())->method('find');
    self::assertNull($this->adapter($customers)->find($organizationId, $siteId, self::BUILDING));
  }

  /**
   * Method unavailableAncestries
   *
   * @access public
   *
   * @return iterable<string,array{string}> inaccessible ancestry cases
   */
  public static function unavailableAncestries(): iterable
  {
    foreach (['foreign target', 'foreign ancestor', 'draft target', 'draft ancestor', 'cycle', 'non-site root', 'explicit other site'] as $scenario) {
      yield $scenario => [$scenario];
    }
  }

  /**
   * Method testRootMustRemainInsideTheSixtyFourRowAncestryBound
   *
   * @access public
   *
   * @param int $descendants chain length below the root
   * @param bool $available whether the actual root lies inside the bound
   *
   * @return void
   */
  #[DataProvider('ancestryDepths')]
  public function testRootMustRemainInsideTheSixtyFourRowAncestryBound(int $descendants, bool $available): void
  {
    $target = $this->root;
    for ($index = 0; $index < $descendants; ++$index) {
      $id = 'c7200000-0000-4000-8000-' . str_pad((string) (100 + $index), 12, '0', STR_PAD_LEFT);
      $target = $this->facility($id, 'zone', $target);
    }
    $this->manager->flush();
    $customers = $this->createMock(CustomerLookupPort::class);
    $customers->expects(self::never())->method('find');
    $resolved = $this->adapter($customers)->find(self::ORGANIZATION, null, $target->id);
    self::assertSame($available, null !== $resolved);
    if (null !== $resolved) {
      self::assertSame(self::SITE, $resolved->id);
    }
  }

  /**
   * Method ancestryDepths
   *
   * @access public
   *
   * @return iterable<string,array{int,bool}> boundary cases
   */
  public static function ancestryDepths(): iterable
  {
    yield 'root is the sixty-fourth row' => [63, true];
    yield 'root is beyond the bound' => [64, false];
  }

  /**
   * Method testUnavailableRetainedCustomerCannotProducePartialTarget
   *
   * @access public
   *
   * @return void
   */
  public function testUnavailableRetainedCustomerCannotProducePartialTarget(): void
  {
    $this->root->customerId = self::CUSTOMER;
    $this->manager->flush();
    $customers = $this->createMock(CustomerLookupPort::class);
    $customers->expects(self::once())->method('find')->with(self::CUSTOMER, self::ORGANIZATION)->willReturn(null);
    self::assertNull($this->adapter($customers)->find(self::ORGANIZATION, null, self::BUILDING));
  }

  /**
   * Method organization
   *
   * @access private
   *
   * @param string $id organization identifier
   *
   * @return OrganizationRecord the test organization
   */
  private function organization(string $id): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Site target tests';
    $organization->slug = 'site-target-' . $id;
    $organization->ownerUserId = self::CUSTOMER;
    $organization->createdByUserId = self::CUSTOMER;
    $organization->createdAt = $organization->updatedAt = new DateTimeImmutable('2026-10-08T10:00:00+00:00');
    $this->manager->persist($organization);

    return $organization;
  }

  /**
   * Method facility
   *
   * @access private
   *
   * @param string $id facility identifier
   * @param string $type stored facility type
   * @param FacilityRecord|null $parent retained parent relation
   *
   * @return FacilityRecord the test facility
   */
  private function facility(string $id, string $type, ?FacilityRecord $parent = null): FacilityRecord
  {
    $facility = new FacilityRecord();
    $facility->id = $id;
    $facility->organization = $this->organization;
    $facility->type = $type;
    $facility->name = $type . ' target';
    $facility->parentFacility = $parent;
    $facility->createdAt = $facility->updatedAt = new DateTimeImmutable('2026-10-08T10:00:00+00:00');
    $this->manager->persist($facility);

    return $facility;
  }

  /**
   * Method adapter
   *
   * @access private
   *
   * @param CustomerLookupPort $customers scoped customer lookup double
   *
   * @return ServiceRequestSiteTargetAdapter the adapter using the real main manager
   */
  private function adapter(CustomerLookupPort $customers): ServiceRequestSiteTargetAdapter
  {
    return new ServiceRequestSiteTargetAdapter($this->manager, $customers, $this->createStub(FacilityHierarchyPort::class));
  }
  // #endregion
}
