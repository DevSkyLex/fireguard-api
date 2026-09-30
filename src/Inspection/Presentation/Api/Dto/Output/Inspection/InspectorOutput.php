<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Output\Inspection;

use ApiPlatform\Metadata\ApiProperty;
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Class InspectorOutput
 *
 * Exposes the inspector identity and display fields for an inspection response.
 *
 * @category OutputDto
 */
final class InspectorOutput
{
  // #region Properties
  /** Property type
   *
   * Inspector category represented by this output.
   *
   * @access public
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $type = '';

  /** Property id
   *
   * Optional identifier of the inspector.
   *
   * @access public
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $id = null;

  /** Property firstName
   *
   * Optional inspector given name.
   *
   * @access public
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $firstName = null;

  /** Property lastName
   *
   * Optional inspector family name.
   *
   * @access public
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $lastName = null;

  /** Property displayName
   *
   * Inspector name prepared for display.
   *
   * @access public
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $displayName = '';

  /** Property avatarUrl
   *
   * Optional inspector avatar URL.
   *
   * @access public
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $avatarUrl = null;

  /**
   * Property organizationName
   *
   * Optional organization name associated with the inspector.
   *
   * @access public
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $organizationName = null;
  // #endregion
}
