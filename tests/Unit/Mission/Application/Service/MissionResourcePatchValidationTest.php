<?php

declare(strict_types=1);

namespace Tests\Unit\Mission\Application\Service;

use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\Port\Outbound\FacilityValidationPort;
use Equipment\Infrastructure\Adapter\Mission\EquipmentMissionResourceAdapter;
use Facility\Infrastructure\Adapter\Mission\FacilityMissionResourceAdapter;
use Inspection\Infrastructure\Adapter\Mission\InspectionMissionResourceAdapter;
use Mission\Domain\Exception\MissionConflictException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MissionResourcePatchValidationTest extends TestCase
{
  #[Test]
  public function itRejectsUnknownEquipmentPatchFields(): void
  {
    $this->assertRejectsUnknownField(
      new EquipmentMissionResourceAdapter(
        $this->createStub(EntityManagerInterface::class),
        $this->createStub(FacilityValidationPort::class),
      ),
      '/api/equipment/018f0b68-6758-7a12-8a1d-3f0d97f63c11',
      'Unsupported equipment patch fields: unknown.',
    );
  }

  #[Test]
  public function itRejectsUnknownFacilityPatchFields(): void
  {
    $this->assertRejectsUnknownField(
      new FacilityMissionResourceAdapter($this->createStub(EntityManagerInterface::class)),
      '/api/facilities/018f0b68-6758-7a12-8a1d-3f0d97f63c12',
      'Unsupported facility patch fields: unknown.',
    );
  }

  #[Test]
  public function itRejectsUnknownInspectionPatchFields(): void
  {
    $this->assertRejectsUnknownField(
      new InspectionMissionResourceAdapter($this->createStub(EntityManagerInterface::class)),
      '/api/inspections/018f0b68-6758-7a12-8a1d-3f0d97f63c13',
      'Unsupported inspection patch fields: unknown.',
    );
  }

  private function assertRejectsUnknownField(object $adapter, string $resource, string $message): void
  {
    $this->expectException(MissionConflictException::class);
    $this->expectExceptionMessage($message);

    /** @var EquipmentMissionResourceAdapter|FacilityMissionResourceAdapter|InspectionMissionResourceAdapter $adapter */
    $adapter->apply('018f0b68-6758-7a12-8a1d-3f0d97f63c14', $resource, ['unknown' => true]);
  }
}
