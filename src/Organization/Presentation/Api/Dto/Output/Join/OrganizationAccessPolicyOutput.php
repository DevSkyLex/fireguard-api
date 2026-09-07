<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Join;

use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO OrganizationAccessPolicyOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationAccessPolicyOutput
{
  /**
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  public string $mode = 'invitation_only';

  /**
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  public ?string $roleId = null;

  /**
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  public ?string $roleLabel = null;

  /**
   * @since 1.0.0
   *
   * @var list<array<string,mixed>>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  public array $domains = [];

  /**
   * @since 1.0.0
   *
   * @var list<array{id:string,label:string}>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  public array $eligibleRoles = [];
}
