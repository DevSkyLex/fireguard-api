<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Output\Checklist;

use ApiPlatform\Metadata\ApiProperty;
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Class ChecklistOutput
 *
 * Presents checklist metadata, item details and operation-specific editing capabilities.
 *
 * @category OutputDto
 */
final class ChecklistOutput
{
  // #region Properties
  /**
   * Property id
   *
   * Checklist identifier.
   *
   * @access public
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $id = '';

  /**
   * Property organizationId
   *
   * Identifier of the organization that owns the checklist.
   *
   * @access public
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $organizationId = '';

  /**
   * Property name
   *
   * Checklist display name.
   *
   * @access public
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $name = '';

  /**
   * Property referenceCode
   *
   * Optional human-facing checklist reference code.
   *
   * @access public
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $referenceCode = null;

  /**
   * Property version
   *
   * Checklist version label.
   *
   * @access public
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $version = '';

  /**
   * Property status
   *
   * Current checklist lifecycle status.
   *
   * @access public
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $status = '';

  /**
   * Property itemCount
   *
   * Number of items on the checklist. Always populated, on every operation
   * (including the list collection, where the full `items` array below is
   * intentionally omitted — see `items` docblock).
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public int $itemCount = 0;

  /**
   * Property items
   *
   * Full item list. Populated on create/get/patch/archive responses. The
   * list (`GetCollection`) response leaves this empty and relies on
   * `itemCount` instead, to avoid shipping every row's full item payload
   * just so clients can count them.
   *
   * @var list<ChecklistItemOutput>
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $items = [];

  /**
   * Property createdAt
   *
   * Checklist creation timestamp.
   *
   * @access public
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $createdAt = '';

  /**
   * Property updatedAt
   *
   * Timestamp of the latest checklist update.
   *
   * @access public
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $updatedAt = '';

  /**
   * Property previousChecklistId
   *
   * Identifier of the preceding checklist revision, when present.
   *
   * @access public
   */
  #[Groups([InspectionSerializationGroup::READ])]
  public ?string $previousChecklistId = null;

  /**
   * Property canEditMetadata
   *
   * Whether the current caller may edit checklist metadata.
   *
   * @access public
   */
  #[Groups([InspectionSerializationGroup::READ])]
  public bool $canEditMetadata = false;

  /**
   * Property canEditItems
   *
   * Whether the current caller may edit checklist items.
   *
   * @access public
   */
  #[Groups([InspectionSerializationGroup::READ])]
  public bool $canEditItems = false;

  /**
   * Property canCreateRevision
   *
   * Whether the current caller may create a checklist revision.
   *
   * @access public
   */
  #[Groups([InspectionSerializationGroup::READ])]
  public bool $canCreateRevision = false;
  // #endregion
}
