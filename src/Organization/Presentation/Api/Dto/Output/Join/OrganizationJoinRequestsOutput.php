<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Join;

use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO OrganizationJoinRequestsOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationJoinRequestsOutput
{
  #[Groups([OrganizationSerializationGroup::READ])]
  public int $totalItems = 0;

  /**
   * @since 1.0.0
   *
   * @var list<array<string,mixed>>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  public array $member = [];

  /**
   * @since 1.0.0
   *
   * @var list<array{id:string,label:string}>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  public array $assignableRoles = [];
}
