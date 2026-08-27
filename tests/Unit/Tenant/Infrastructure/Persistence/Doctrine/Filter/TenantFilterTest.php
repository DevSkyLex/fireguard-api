<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Infrastructure\Persistence\Doctrine\Filter;

use Audit\Infrastructure\Persistence\Doctrine\Record\AuditEventRecord;
use Authorization\Infrastructure\Persistence\Doctrine\Record\{RoleAssignmentRecord, RoleRecord};
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\FilterCollection;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Tenant\Infrastructure\Persistence\Doctrine\Filter\TenantFilter;

/**
 * Test TenantFilterTest.
 *
 * Also guards the blast radius of the filter: it scopes the multi-tenant
 * business tables and leaves the authorization tables alone. Filtering the
 * latter strips the caller of its own grants, which turned every
 * permission-gated endpoint into a 403 as soon as a request carried a
 * `tenantId`.
 *
 * @category Doctrine Filter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TenantFilter::class)]
final class TenantFilterTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testReturnsEmptyWhenEntityHasNoTenantId(): void
  {
    $filter = $this->createFilter();
    $metadata = $this->createMock(ClassMetadata::class);
    $metadata->expects(self::once())
      ->method('hasField')
      ->with('tenantId')
      ->willReturn(false);

    self::assertSame('', $filter->addFilterConstraint($metadata, 't'));
  }

  #[Test]
  public function testReturnsEmptyWhenParameterMissing(): void
  {
    $filter = $this->createFilter();
    $metadata = $this->createMock(ClassMetadata::class);
    $metadata->expects(self::once())
      ->method('hasField')
      ->with('tenantId')
      ->willReturn(true);
    $metadata->method('getName')
      ->willReturn(AuditEventRecord::class);

    self::assertSame('', $filter->addFilterConstraint($metadata, 't'));
  }

  #[Test]
  public function testReturnsConstraintWhenTenantIdIsSet(): void
  {
    $filter = $this->createFilter();
    $filter->setParameter('tenant_id', 'tenant-123');

    $metadata = $this->createMock(ClassMetadata::class);
    $metadata->expects(self::once())
      ->method('hasField')
      ->with('tenantId')
      ->willReturn(true);
    $metadata->method('getName')
      ->willReturn(AuditEventRecord::class);
    $metadata->expects(self::once())
      ->method('getColumnName')
      ->with('tenantId')
      ->willReturn('tenant_id');

    self::assertSame("t.tenant_id = 'tenant-123'", $filter->addFilterConstraint($metadata, 't'));
  }

  #[Test]
  public function testReturnsEmptyWhenTenantIdIsEmptyString(): void
  {
    $filter = $this->createFilter();
    $filter->setParameter('tenant_id', '');

    $metadata = $this->createMock(ClassMetadata::class);
    $metadata->expects(self::once())
      ->method('hasField')
      ->with('tenantId')
      ->willReturn(true);
    $metadata->method('getName')
      ->willReturn(AuditEventRecord::class);

    self::assertSame('', $filter->addFilterConstraint($metadata, 't'));
  }

  /**
   * @return iterable<string, array{class-string}>
   */
  public static function exemptRecordProvider(): iterable
  {
    yield 'roles' => [RoleRecord::class];
    yield 'role assignments' => [RoleAssignmentRecord::class];
  }

  /**
   * @param class-string $class the exempt record class
   */
  #[Test]
  #[DataProvider('exemptRecordProvider')]
  public function testReturnsEmptyForTenantFilterExemptEntities(string $class): void
  {
    $filter = $this->createFilter();
    $filter->setParameter('tenant_id', 'tenant-123');

    $metadata = $this->createMock(ClassMetadata::class);
    $metadata->expects(self::once())
      ->method('hasField')
      ->with('tenantId')
      ->willReturn(true);
    $metadata->method('getName')
      ->willReturn($class);
    $metadata->expects(self::never())
      ->method('getColumnName');

    self::assertSame('', $filter->addFilterConstraint($metadata, 't'));
  }

  private function createFilter(): TenantFilter
  {
    $connection = $this->createStub(Connection::class);
    $connection->method('quote')
      ->willReturnCallback(static fn (string $value): string => "'" . $value . "'");

    $filters = $this->createStub(FilterCollection::class);
    $filters->method('setFiltersStateDirty');

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('getConnection')
      ->willReturn($connection);
    $entityManager->method('getFilters')
      ->willReturn($filters);

    return new TenantFilter($entityManager);
  }
  // #endregion
}
