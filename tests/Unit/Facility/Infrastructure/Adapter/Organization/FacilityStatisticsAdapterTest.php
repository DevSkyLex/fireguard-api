<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Infrastructure\Adapter\Organization;

use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Domain\ValueObject\FacilityOrganizationId;
use Facility\Infrastructure\Adapter\Organization\FacilityStatisticsAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(FacilityStatisticsAdapter::class)]
final class FacilityStatisticsAdapterTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testCountFacilitiesByTypeNormalizesMissingTypesToZero(): void
  {
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countByTypeForOrganizationId')
      ->with(
        self::callback(static fn (FacilityOrganizationId $organizationId): bool => self::ORG_ID === (string) $organizationId),
        true,
      )
      ->willReturn([
        'site' => 2,
        'building' => 1,
      ]);

    $adapter = new FacilityStatisticsAdapter($repository);

    self::assertSame([
      'site' => 2,
      'building' => 1,
      'floor' => 0,
      'zone' => 0,
      'area' => 0,
    ], $adapter->countFacilitiesByType(self::ORG_ID));
  }
}
