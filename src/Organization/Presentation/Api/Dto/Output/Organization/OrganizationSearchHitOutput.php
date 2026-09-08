<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO OrganizationSearchHitOutput.
 *
 * One row of the organization global search. The frontend builds the target
 * route from `type` + `id` — the API deliberately ships no URL.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationSearchHitOutput
{
  // #region Properties
  /**
   * Property type.
   *
   * The result type: `equipment`, `facility`, `intervention`, `inspection`
   * or `non_conformity`.
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $type = '';

  /**
   * Property id.
   *
   * The matched record identifier (UUID) within its own module.
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $id = '';

  /**
   * Property title.
   *
   * The primary display line — per type: equipment brand+model (or type),
   * facility name, intervention name, inspection checklist reference code
   * (or inspection id), non-conformity description (truncated).
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $title = '';

  /**
   * Property subtitle.
   *
   * The secondary display line — per type: equipment serial number,
   * facility code, intervention number, inspection status, non-conformity
   * severity. Null when the source field is empty.
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $subtitle = null;

  /**
   * Property extra.
   *
   * An optional tertiary hint — per type: equipment location label,
   * facility address, inspection result, non-conformity status. Always null
   * for interventions.
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $extra = null;

  /**
   * Property parentId.
   *
   * The owning record's identifier when the matched record has no page of
   * its own — a non-conformity carries its inspection's id so the client
   * can deep-link to the inspection detail. Null for every other type.
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $parentId = null;
  // #endregion
}
