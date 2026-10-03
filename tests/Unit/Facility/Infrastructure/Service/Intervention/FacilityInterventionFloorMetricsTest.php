<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Infrastructure\Service\Intervention;

use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Port\Inbound\FacilityArchivalGuardPort;
use Facility\Application\Port\Outbound\{FacilityAttachmentRepositoryPort, FacilityMetadataFieldRepositoryPort, FacilityRepositoryPort};
use Facility\Application\Service\{FacilityAttachmentAncestryGuard, FacilityMetadataSchemaGuard};
use Facility\Infrastructure\Exception\FacilityPatchConflictException;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Facility\Infrastructure\Service\Intervention\FacilityInterventionPatchApplier;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

use const INF;
use const NAN;

#[CoversClass(FacilityInterventionPatchApplier::class)]
final class FacilityInterventionFloorMetricsTest extends TestCase
{
  // #region Methods
  /**
   * Offline intervention patches preserve dimensions absent from a proposed change.
   */
  #[Test]
  public function explicitNullClearsOnlyOneMetric(): void
  {
    $record = $this->record();
    self::assertInstanceOf(OrganizationRecord::class, $record->organization);
    $this->applier($record)->apply($record->organization->id, '/api/facilities/' . $record->id, ['elevationMeters' => null]);
    self::assertNull($record->elevationMeters);
    self::assertSame(2.5, $record->heightMeters);
  }

  /**
   * A proposed type change clears the former floor dimensions.
   */
  #[Test]
  public function typeChangeClearsPhysicalDimensions(): void
  {
    $record = $this->record();
    self::assertInstanceOf(OrganizationRecord::class, $record->organization);
    $this->applier($record)->apply($record->organization->id, '/api/facilities/' . $record->id, ['type' => 'area']);
    self::assertNull($record->elevationMeters);
    self::assertNull($record->heightMeters);
  }

  /**
   * Method invalidMetricsConflict
   *
   * Non-numeric, non-finite, out-of-range and non-floor dimensions reject intervention apply.
   *
   * @param array<string, mixed> $patch supplied changes
   */
  #[Test]
  #[DataProvider('invalidPatches')]
  public function invalidMetricsConflict(array $patch): void
  {
    $record = $this->record();
    self::assertInstanceOf(OrganizationRecord::class, $record->organization);
    $this->expectException(FacilityPatchConflictException::class);
    $this->applier($record)->apply($record->organization->id, '/api/facilities/' . $record->id, $patch);
  }

  /**
   * @return iterable<string, array{array<string, mixed>}>
   */
  public static function invalidPatches(): iterable
  {
    yield 'string' => [['heightMeters' => '3']];
    yield 'nan' => [['heightMeters' => NAN]];
    yield 'infinity' => [['elevationMeters' => INF]];
    yield 'zero height' => [['heightMeters' => 0]];
    yield 'high elevation' => [['elevationMeters' => 10001]];
    yield 'non-floor' => [['type' => 'building', 'heightMeters' => 3]];
  }

  /**
   * Constructs an in-memory persisted basement.
   */
  private function record(): FacilityRecord
  {
    $record = new FacilityRecord();
    $record->id = '550e8400-e29b-41d4-a716-446655442050';
    $record->organization = new OrganizationRecord();
    $record->organization->id = '550e8400-e29b-41d4-a716-446655442051';
    $record->type = 'floor';
    $record->name = 'Floor';
    $record->elevationMeters = -3.0;
    $record->heightMeters = 2.5;

    return $record;
  }

  /**
   * Constructs the existing adapter with mocked external ports.
   */
  private function applier(FacilityRecord $record): FacilityInterventionPatchApplier
  {
    $manager = $this->createMock(EntityManagerInterface::class);
    $manager->expects(self::once())->method('find')->with(FacilityRecord::class, $record->id)->willReturn($record);
    $repository = $this->createStub(FacilityRepositoryPort::class);

    return new FacilityInterventionPatchApplier(
      $manager,
      $this->createStub(FacilityArchivalGuardPort::class),
      $repository,
      new FacilityMetadataSchemaGuard($this->createStub(FacilityMetadataFieldRepositoryPort::class)),
      $this->createStub(FacilityAttachmentRepositoryPort::class),
      new FacilityAttachmentAncestryGuard($repository),
      16,
    );
  }
  // #endregion
}
