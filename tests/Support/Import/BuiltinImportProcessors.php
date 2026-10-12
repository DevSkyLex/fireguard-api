<?php

declare(strict_types=1);

namespace Tests\Support\Import;

use Equipment\Application\Contract\Provisioning\{ProvisionEquipmentRequest, ProvisionEquipmentResult};
use Equipment\Application\Port\Inbound\EquipmentProvisioningPort;
use Equipment\Infrastructure\Adapter\Import\EquipmentImportKindProcessorAdapter;
use Facility\Application\Contract\Provisioning\{ProvisionFacilityRequest, ProvisionFacilityResult};
use Facility\Application\Port\Inbound\FacilityProvisioningPort;
use Facility\Infrastructure\Adapter\Import\FacilityImportKindProcessorAdapter;
use Import\Application\Service\ImportKindProcessorRegistry;
use LogicException;
use Organization\Application\Contract\Provisioning\{ProvisionMemberInvitationRequest, ProvisionMemberInvitationResult};
use Organization\Application\Port\Inbound\MemberInvitationProvisioningPort;
use Organization\Infrastructure\Adapter\Import\MemberImportKindProcessorAdapter;

/**
 * Fixture BuiltinImportProcessors.
 *
 * Composes the real owner contributions while preventing unexpected provisioning
 * in tests that only read their permissions or CSV template metadata.
 *
 * @category Test Support
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class BuiltinImportProcessors
{
  public static function create(
    ?EquipmentProvisioningPort $equipment = null,
    ?FacilityProvisioningPort $facility = null,
    ?MemberInvitationProvisioningPort $member = null,
  ): ImportKindProcessorRegistry {
    $equipment ??= new class () implements EquipmentProvisioningPort {
      public function provision(ProvisionEquipmentRequest $request): ProvisionEquipmentResult
      {
        throw new LogicException('Unexpected equipment provisioning.');
      }
    };
    $facility ??= new class () implements FacilityProvisioningPort {
      public function provision(ProvisionFacilityRequest $request): ProvisionFacilityResult
      {
        throw new LogicException('Unexpected facility provisioning.');
      }

      public function restoreSimulation(ProvisionFacilityRequest $request): ProvisionFacilityResult
      {
        throw new LogicException('Unexpected facility simulation restoration.');
      }
    };
    $member ??= new class () implements MemberInvitationProvisioningPort {
      public function provision(ProvisionMemberInvitationRequest $request): ProvisionMemberInvitationResult
      {
        throw new LogicException('Unexpected member provisioning.');
      }
    };

    return new ImportKindProcessorRegistry([
      new EquipmentImportKindProcessorAdapter($equipment),
      new FacilityImportKindProcessorAdapter($facility),
      new MemberImportKindProcessorAdapter($member),
    ]);
  }
}
