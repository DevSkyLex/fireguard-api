<?php

declare(strict_types=1);

namespace Equipment\Application\Service;

use Equipment\Application\Contract\Procurement\{EquipmentReserveReceiptRequest,EquipmentReserveReceiptResult};
use Equipment\Application\Port\Inbound\EquipmentReserveReceiptPort;
use Equipment\Application\Port\Outbound\EquipmentTypeCatalogPort;
use Equipment\Application\UseCase\Command\Equipment\CreateEquipment\{CreateEquipmentCommand,CreateEquipmentResult};
use Equipment\Domain\ValueObject\EquipmentIdentity;
use Organization\Application\Contract\Quota\{OrganizationQuotaExceededException, OrganizationQuotaResource};
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationQuotaPort};
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\Uuid;

/** One batch quota lock before any reserve creation. Unexpected failures roll back the entire main batch. @category Service */
final readonly class EquipmentReserveReceiptService implements EquipmentReserveReceiptPort
{
  public function __construct(private CommandBusPort $commands, private TransactionManagerPort $transactions, private OrganizationQuotaPort $quota, private EquipmentTypeCatalogPort $catalog, private OrganizationAuthorizationPort $authorization)
  {
  }

  public function supportsType(string $organizationId, string $typeCode): bool
  {
    $type = $this->catalog->find($organizationId, $typeCode);

    return null !== $type && !$type->archived;
  }

  public function reserve(EquipmentReserveReceiptRequest $request): EquipmentReserveReceiptResult
  {
    new Uuid($request->organizationId);
    new Uuid($request->actorId);
    if ($request->quantity < 1 || $request->quantity > 100) {
      throw new InvalidValueException('Reserve batches must contain between one and one hundred equipment.');
    }
    $access = $this->authorization->resolveAccess($request->actorId, $request->organizationId, 'organization.equipment.write');
    if (!$access->isGranted()) {
      return new EquipmentReserveReceiptResult([], blockedReason:'missing_equipment_permission');
    }
    if (!$this->supportsType($request->organizationId, $request->typeCode)) {
      return new EquipmentReserveReceiptResult([], blockedReason:'unavailable_type');
    }
    $t = $request->identityTemplate;
    $identity = EquipmentIdentity::fromValues($t['name'] ?? null, $t['assetCode'] ?? null, $t['criticality'] ?? null, $t['technicalProperties'] ?? []);
    if ($request->quantity > 1 && (null !== ($t['assetCode'] ?? null) || null !== ($t['serialNumber'] ?? null))) {
      throw new InvalidValueException('A shared batch template cannot repeat unique asset or serial identities.');
    }

    return $this->transactions->transactional(function () use ($request, $t, $identity): EquipmentReserveReceiptResult {
      try {
        $this->quota->assertCanAddMultiple($request->organizationId, OrganizationQuotaResource::EQUIPMENT, $request->quantity);
      } catch (OrganizationQuotaExceededException) {
        return new EquipmentReserveReceiptResult([], blockedReason:'quota_exceeded');
      }
      $ids = [];
      for ($unit = 0; $unit < $request->quantity; ++$unit) {
        /** @var CreateEquipmentResult $result */
        $result = $this->commands->dispatch(new CreateEquipmentCommand($request->organizationId, $request->typeCode, subType:$t['subType'] ?? null, brand:$t['brand'] ?? null, model:$t['model'] ?? null, serialNumber:$t['serialNumber'] ?? null, name:$identity->name, assetCode:$identity->assetCode, criticality:$identity->criticality, technicalProperties:$identity->technicalProperties));
        $ids[] = $result->equipmentId;
      }

      return new EquipmentReserveReceiptResult($ids);
    });
  }
}
