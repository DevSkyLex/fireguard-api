<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Infrastructure\Persistence\Doctrine\Filter;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\FilterCollection;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Tenant\Infrastructure\Persistence\Doctrine\Filter\TenantFilter;

/**
 * Test TenantFilterTest.
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

    self::assertSame('', $filter->addFilterConstraint($metadata, 't'));
  }

  private function createFilter(): TenantFilter
  {
    $connection = $this->createMock(Connection::class);
    $connection->method('quote')
      ->willReturnCallback(static fn (string $value): string => "'" . $value . "'");

    $filters = $this->createMock(FilterCollection::class);
    $filters->method('setFiltersStateDirty');

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('getConnection')
      ->willReturn($connection);
    $entityManager->method('getFilters')
      ->willReturn($filters);

    return new TenantFilter($entityManager);
  }
  // #endregion
}
