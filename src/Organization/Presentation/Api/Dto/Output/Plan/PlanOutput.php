<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Plan;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Application\UseCase\Query\Plan\GetPlan\GetPlanResult;
use Organization\Presentation\Api\Serialization\PlanSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

use function array_map;

/**
 * DTO PlanOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PlanOutput
{
  // #region Properties
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[Groups([PlanSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $id = '';

  /**
   * Property key.
   *
   * @since 1.0.0
   */
  #[Groups([PlanSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $key = '';

  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[Groups([PlanSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $name = '';

  /**
   * Property description.
   *
   * @since 1.0.0
   */
  #[Groups([PlanSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $description = null;

  /**
   * Property tagline.
   *
   * Short marketing tagline for the plan comparison card, or null when the
   * plan has none configured. Display copy only — never used to derive what
   * the plan grants (see {@see self::$limits} / {@see self::$quotas} for
   * that).
   *
   * @since 1.0.0
   */
  #[Groups([PlanSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $tagline = null;

  /**
   * Property perks.
   *
   * Marketing perk bullet list for the plan comparison card, in display
   * order; empty when the plan has none configured. Display copy only — NOT
   * an entitlement list: quotas and the relevant module's entitlement port
   * (e.g. Compliance's export entitlement) remain the sole source of truth
   * for what the plan actually grants.
   *
   * @var list<string>
   *
   * @since 1.0.0
   */
  #[Groups([PlanSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $perks = [];

  /**
   * Property limits.
   *
   * Per-resource quantity caps keyed by resource; an absent resource is unlimited.
   *
   * @var array<string, int>
   *
   * @since 1.0.0
   */
  #[Groups([PlanSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $limits = [];

  /**
   * Property quotas.
   *
   * Per-resource allowance descriptors (every resource, with a phrased summary)
   * for rendering rich plan cards.
   *
   * @var list<PlanQuotaOutput>
   *
   * @since 1.0.0
   */
  #[Groups([PlanSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $quotas = [];

  /**
   * Property isActive.
   *
   * @since 1.0.0
   */
  #[Groups([PlanSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $isActive = true;

  /**
   * Property isDefault.
   *
   * @since 1.0.0
   */
  #[Groups([PlanSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $isDefault = false;

  /**
   * Property sortOrder.
   *
   * @since 1.0.0
   */
  #[Groups([PlanSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public int $sortOrder = 0;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[Groups([PlanSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $createdAt = '';

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  #[Groups([PlanSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $updatedAt = '';
  // #endregion

  // #region Methods
  /**
   * Method fromResult.
   *
   * Builds the API output from a plan read model.
   *
   * @since 1.0.0
   *
   * @param GetPlanResult $result the plan read model
   *
   * @return self the API output
   */
  public static function fromResult(GetPlanResult $result): self
  {
    $output = new self();
    $output->id = $result->id;
    $output->key = $result->key;
    $output->name = $result->name;
    $output->description = $result->description;
    $output->tagline = $result->tagline;
    $output->perks = $result->perks;
    $output->limits = $result->limits;
    $output->quotas = array_map(
      static function (array $quota): PlanQuotaOutput {
        $item = new PlanQuotaOutput();
        $item->resource = $quota['resource'];
        $item->label = $quota['label'];
        $item->limit = $quota['limit'];
        $item->summary = $quota['summary'];

        return $item;
      },
      $result->quotas,
    );
    $output->isActive = $result->isActive;
    $output->isDefault = $result->isDefault;
    $output->sortOrder = $result->sortOrder;
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');

    return $output;
  }
  // #endregion
}
