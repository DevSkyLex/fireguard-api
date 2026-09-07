<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Dto\Output\Facility;

use ApiPlatform\Metadata\ApiProperty;
use Facility\Presentation\Api\Serialization\FacilitySerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO SuggestAddressesOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SuggestAddressesOutput
{
  // #region Properties
  /**
   * Property id.
   *
   * The organization scope identifies the transient lookup operation.
   *
   * @since 1.0.0
   */
  #[ApiProperty(identifier: true, readable: false, writable: false)]
  public string $id = '';

  /**
   * Property member.
   *
   * @since 1.0.0
   *
   * @var list<AddressSuggestionOutput>
   */
  #[Groups([FacilitySerializationGroup::READ])]
  public array $member = [];

  /**
   * Property totalItems.
   *
   * @since 1.0.0
   */
  #[Groups([FacilitySerializationGroup::READ])]
  public int $totalItems = 0;
  // #endregion
}
