<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Join;

use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO OrganizationJoinRequestOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationJoinRequestOutput
{
  /**
   * @since 1.0.0 Visible only in the administrator's authorized projection.
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  public ?string $applicantEmail = null;

  /**
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  public string $id = '';

  /**
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  public string $organizationId = '';

  /**
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  public string $organizationName = '';

  /**
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  public string $status = '';

  /**
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  public string $createdAt = '';

  /**
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  public string $expiresAt = '';

  /**
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  public array $actions = [];
}
