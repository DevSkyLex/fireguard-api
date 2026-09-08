<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO OrganizationSearchOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationSearchOutput
{
  // #region Properties
  /**
   * Property query.
   *
   * The normalized (trimmed) search term the results answer.
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $query = '';

  /**
   * Property results.
   *
   * The flat hit list, grouped by type in a stable order (`equipment`,
   * `facility`, `intervention`, `inspection`, `non_conformity`), at most 5
   * hits per type. A type the caller may not read contributes no rows —
   * indistinguishable from a type with no match.
   *
   * @var list<OrganizationSearchHitOutput>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $results = [];
  // #endregion
}
