<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Persistence\Doctrine\Record;

use Doctrine\ORM\Mapping as ORM;

/**
 * Record InterventionTemplateItemRecord.
 *
 * One planned item of an intervention template, seeded as a draft work item
 * (in position order) when the template is instantiated.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'intervention_template_items')]
#[ORM\Index(name: 'idx_intervention_template_item_template', columns: ['template_id'])]
class InterventionTemplateItemRecord
{
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  /**
   * Property template.
   *
   * @since 1.0.0
   */
  #[ORM\ManyToOne(targetEntity: InterventionTemplateRecord::class, inversedBy: 'items')]
  #[ORM\JoinColumn(name: 'template_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?InterventionTemplateRecord $template = null;

  /**
   * Property position.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'integer')]
  public int $position = 0;

  /**
   * Property action.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 60)]
  public string $action;

  /**
   * Property target.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'text', nullable: true)]
  public ?string $target = null;

  /**
   * Property resultResource.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'result_resource', type: 'string', length: 255, nullable: true)]
  public ?string $resultResource = null;

  /**
   * Property required.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'boolean')]
  public bool $required = true;

  /**
   * Property defaultAssigneeId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'default_assignee_id', type: 'string', length: 36, nullable: true)]
  public ?string $defaultAssigneeId = null;
}
