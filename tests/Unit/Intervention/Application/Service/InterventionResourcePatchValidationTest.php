<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\Service;

use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\Port\Inbound\EquipmentMaintenanceLogSynchronizerPort;
use Equipment\Application\Port\Outbound\FacilityValidationPort;
use Equipment\Infrastructure\Adapter\Intervention\EquipmentInterventionResourceAdapter;
use Facility\Application\Port\Inbound\FacilityArchivalGuardPort;
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Infrastructure\Adapter\Intervention\FacilityInterventionResourceAdapter;
use Inspection\Infrastructure\Adapter\Intervention\InspectionInterventionResourceAdapter;
use Intervention\Domain\Exception\InterventionConflictException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InterventionResourcePatchValidationTest extends TestCase
{
  #[Test]
  public function itRejectsUnknownEquipmentPatchFields(): void
  {
    $this->assertRejectsUnknownField(
      new EquipmentInterventionResourceAdapter(
        $this->createStub(EntityManagerInterface::class),
        $this->createStub(FacilityValidationPort::class),
        $this->createStub(EquipmentMaintenanceLogSynchronizerPort::class),
      ),
      '/api/equipment/018f0b68-6758-7a12-8a1d-3f0d97f63c11',
      'Unsupported equipment patch fields: unknown.',
    );
  }

  #[Test]
  public function itRejectsUnknownFacilityPatchFields(): void
  {
    $this->assertRejectsUnknownField(
      new FacilityInterventionResourceAdapter(
        $this->createStub(EntityManagerInterface::class),
        $this->createStub(FacilityArchivalGuardPort::class),
        $this->createStub(FacilityRepositoryPort::class),
      ),
      '/api/facilities/018f0b68-6758-7a12-8a1d-3f0d97f63c12',
      'Unsupported facility patch fields: unknown.',
    );
  }

  #[Test]
  public function itRejectsUnknownInspectionPatchFields(): void
  {
    $this->assertRejectsUnknownField(
      new InspectionInterventionResourceAdapter($this->createStub(EntityManagerInterface::class)),
      '/api/inspections/018f0b68-6758-7a12-8a1d-3f0d97f63c13',
      'Unsupported inspection patch fields: unknown.',
    );
  }

  private function assertRejectsUnknownField(object $adapter, string $resource, string $message): void
  {
    $this->expectException(InterventionConflictException::class);
    $this->expectExceptionMessage($message);

    /** @var EquipmentInterventionResourceAdapter|FacilityInterventionResourceAdapter|InspectionInterventionResourceAdapter $adapter */
    $adapter->apply('018f0b68-6758-7a12-8a1d-3f0d97f63c14', $resource, ['unknown' => true]);
  }
}
