<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Record;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class ChecklistItemRecord
 *
 * Doctrine record for one ordered item in an inspection checklist.
 *
 * @category Record
 */
#[ORM\Entity]
#[ORM\Table(name: 'checklist_items')]
#[ORM\Index(name: 'idx_checklist_item_checklist', columns: ['checklist_id'])]
class ChecklistItemRecord
{
  // #region Properties
  /**
   * Property id.
   *
   * Checklist item identifier.
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  /**
   * Property checklist.
   *
   * Checklist that owns this item.
   */
  #[ORM\ManyToOne(targetEntity: ChecklistRecord::class, inversedBy: 'items')]
  #[ORM\JoinColumn(name: 'checklist_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?ChecklistRecord $checklist = null;

  /**
   * Property label.
   *
   * Display label used for this checklist item.
   */
  #[ORM\Column(name: 'label', type: 'string', length: 255)]
  public string $label;

  /**
   * Property position.
   *
   * Item ordering position within the checklist.
   */
  #[ORM\Column(name: 'position', type: 'integer')]
  public int $position;

  /**
   * Property required.
   *
   * Whether an inspection must answer this checklist item.
   */
  #[ORM\Column(name: 'required', type: 'boolean')]
  public bool $required;

  /**
   * Property description.
   *
   * Optional supporting text for this checklist item.
   */
  #[ORM\Column(name: 'description', type: 'text', nullable: true)]
  public ?string $description = null;
  // #endregion
}
