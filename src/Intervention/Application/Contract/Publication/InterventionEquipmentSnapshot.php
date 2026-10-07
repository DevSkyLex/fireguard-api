<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Publication;

/**
 * Class InterventionEquipmentSnapshot
 *
 * Carries equipment identity and root ownership captured by its owner, without contacts or financial data.
 *
 * @category Contract
 */
final readonly class InterventionEquipmentSnapshot
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Nullable labels retain the actual declared identity rather than synthesizing historical names.
   *
   * @access public
   *
   * @param string $id stable equipment identifier
   * @param ?string $name declared label
   * @param ?string $assetReference organization asset reference
   * @param string $type retained catalog code
   * @param ?string $brand declared brand
   * @param ?string $model declared model
   * @param ?string $serialNumber declared serial
   * @param ?string $facilityId location identifier at capture
   * @param ?array{id:string,name:string} $site root site identity at capture
   * @param ?array{id:string,name:string} $customer optional internal client identity at capture
   *
   * @return void
   */
  public function __construct(public string $id, public ?string $name, public ?string $assetReference, public string $type, public ?string $brand, public ?string $model, public ?string $serialNumber, public ?string $facilityId, public ?array $site, public ?array $customer)
  {
  }
  // #endregion
}
