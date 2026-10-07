<?php

declare(strict_types=1);

namespace Equipment\Application\Contract\Procurement;

/** @category Contract */
final readonly class EquipmentReserveReceiptRequest
{
  /**
   * @param array{name?:?string,brand?:?string,model?:?string,subType?:?string,serialNumber?:?string,assetCode?:?string,criticality?:?string,technicalProperties?:list<array{key:string,value:string,unit:?string}>} $identityTemplate
   */
  public function __construct(public string $organizationId, public string $actorId, public string $typeCode, public array $identityTemplate, public int $quantity)
  {
  }
}
