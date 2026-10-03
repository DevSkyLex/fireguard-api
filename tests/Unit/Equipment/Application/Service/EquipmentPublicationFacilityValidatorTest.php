<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\Service;

use Equipment\Application\Port\Outbound\FacilityValidationPort;
use Equipment\Application\Service\EquipmentPublicationFacilityValidator;
use Facility\Application\Port\Inbound\FacilityLifecycleReferencePort;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(EquipmentPublicationFacilityValidator::class)]
final class EquipmentPublicationFacilityValidatorTest extends TestCase
{
  #[Test]
  public function testActiveEquipmentRequiresAnAssignableFacilityInThePublishingScope(): void
  {
    $facilities = $this->createMock(FacilityValidationPort::class);
    $facilities->expects(self::once())->method('assertFacilityIsAssignable')->with('facility', 'organization', null, 'intervention');
    $retained = $this->createMock(FacilityLifecycleReferencePort::class);
    $retained->expects(self::never())->method('assertRetainedReference');

    new EquipmentPublicationFacilityValidator($facilities, $retained)->assertReference('organization', 'facility', 'operational', 'intervention');
  }

  #[Test]
  public function testTerminalReferencesUseTheRetainedPolicyInThePublishingScope(): void
  {
    $facilities = $this->createMock(FacilityValidationPort::class);
    $facilities->expects(self::never())->method('assertFacilityIsAssignable');
    $retained = $this->createMock(FacilityLifecycleReferencePort::class);
    $retained->expects(self::once())->method('assertRetainedReference')->with('organization', 'facility', null, 'intervention');

    new EquipmentPublicationFacilityValidator($facilities, $retained)->assertReference('organization', 'facility', 'decommissioned', 'intervention');
  }

  #[Test]
  public function testTerminalReferencesPreserveScopeDenialsFromTheRetainedReferencePolicy(): void
  {
    $facilities = $this->createMock(FacilityValidationPort::class);
    $facilities->expects(self::never())->method('assertFacilityIsAssignable');
    $retained = $this->createMock(FacilityLifecycleReferencePort::class);
    $denial = new InvalidArgumentException('Facility reference is outside organization scope.');
    $retained->expects(self::once())->method('assertRetainedReference')->with('organization', 'facility', null, 'intervention')->willThrowException($denial);

    $this->expectExceptionObject($denial);
    new EquipmentPublicationFacilityValidator($facilities, $retained)->assertReference('organization', 'facility', 'decommissioned', 'intervention');
  }

  #[Test]
  public function testMissingRetainedPolicyKeepsTheStrictAssignmentFallback(): void
  {
    $facilities = $this->createMock(FacilityValidationPort::class);
    $facilities->expects(self::once())->method('assertFacilityIsAssignable')->with('facility', 'organization', null, null);

    new EquipmentPublicationFacilityValidator($facilities)->assertReference('organization', 'facility', 'decommissioned');
  }

  #[Test]
  public function testUnassignedEquipmentDoesNotResolveAnyFacility(): void
  {
    $facilities = $this->createMock(FacilityValidationPort::class);
    $facilities->expects(self::never())->method('assertFacilityIsAssignable');
    $retained = $this->createMock(FacilityLifecycleReferencePort::class);
    $retained->expects(self::never())->method('assertRetainedReference');

    new EquipmentPublicationFacilityValidator($facilities, $retained)->assertReference('organization', null, 'in_stock');
  }
}
