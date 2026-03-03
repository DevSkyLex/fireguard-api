<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Record;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'checklist_items')]
#[ORM\Index(name: 'idx_checklist_item_checklist', columns: ['checklist_id'])]
final class ChecklistItemRecord
{
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  #[ORM\Column(name: 'checklist_id', type: 'string', length: 36)]
  public string $checklistId;

  #[ORM\Column(name: 'label', type: 'string', length: 255)]
  public string $label;

  #[ORM\Column(name: 'position', type: 'integer')]
  public int $position;

  #[ORM\Column(name: 'required', type: 'boolean')]
  public bool $required;

  #[ORM\Column(name: 'description', type: 'text', nullable: true)]
  public ?string $description = null;
}
