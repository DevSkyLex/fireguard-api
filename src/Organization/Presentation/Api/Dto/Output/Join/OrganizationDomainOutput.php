<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Join;

use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO OrganizationDomainOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationDomainOutput
{
  /**
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  public string $id = '';

  /**
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  public string $domain = '';

  /**
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  public string $status = '';

  /**
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  public string $dnsName = '';

  /**
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  public string $dnsValue = '';

  /**
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  public ?string $verifiedAt = null;

  /**
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  public ?string $lastCheckedAt = null;
}
