<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Infrastructure\Adapter\Equipment;

use Inspection\Application\Port\Outbound\NonConformityRepositoryPort;
use Inspection\Domain\ValueObject\InspectionOrganizationId;
use Inspection\Infrastructure\Adapter\Equipment\EquipmentNonConformityStatisticsAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test EquipmentNonConformityStatisticsAdapterTest.
 *
 * The adapter composes two ALREADY-tested `NonConformityRepositoryPort`
 * count calls (no new DQL is introduced here), so a mocked-port unit test
 * is sufficient — see `NonConformityRepository`'s own integration coverage
 * for the underlying query.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentNonConformityStatisticsAdapter::class)]
final class EquipmentNonConformityStatisticsAdapterTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655443001';

  #[Test]
  public function testCountOpenNonConformitiesSumsOpenAndInProgress(): void
  {
    /** @var NonConformityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NonConformityRepositoryPort::class);
    $repository->expects(self::exactly(2))
      ->method('countByOrganizationId')
      ->with(
        self::callback(static fn (InspectionOrganizationId $organizationId): bool => self::ORG_ID === (string) $organizationId),
        null,
        self::logicalOr('open', 'in_progress'),
        null,
      )
      ->willReturnCallback(static fn (InspectionOrganizationId $organizationId, ?string $severity, ?string $status, ?string $search): int => match ($status) {
        'open' => 4,
        'in_progress' => 2,
        default => 0,
      });

    $adapter = new EquipmentNonConformityStatisticsAdapter($repository);

    self::assertSame(6, $adapter->countOpenNonConformities(self::ORG_ID));
  }

  #[Test]
  public function testCountOpenNonConformitiesReturnsZeroWhenNone(): void
  {
    /** @var NonConformityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NonConformityRepositoryPort::class);
    $repository->expects(self::exactly(2))
      ->method('countByOrganizationId')
      ->willReturn(0);

    $adapter = new EquipmentNonConformityStatisticsAdapter($repository);

    self::assertSame(0, $adapter->countOpenNonConformities(self::ORG_ID));
  }
}
