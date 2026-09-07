<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Join;

use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO OrganizationJoinOptionsOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationJoinOptionsOutput
{
  /**
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  public bool $emailProofRequired = false;

  /**
   * @since 1.0.0
   *
   * @var list<array{id:string,organizationId:string,organizationName:string,expiresAt:string}>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  public array $invitations = [];

  /**
   * @since 1.0.0
   *
   * @var list<array{id:string,name:string,logoUrl:?string,domain:string,roleLabel:?string,actions:list<string>}>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  public array $organizations = [];

  /**
   * @since 1.0.0
   *
   * @var list<array<string,mixed>>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  public array $requests = [];
}
