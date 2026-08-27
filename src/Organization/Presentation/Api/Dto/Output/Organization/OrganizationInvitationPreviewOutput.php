<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO OrganizationInvitationPreviewOutput.
 *
 * Minimal, public-safe projection of an invitation resolved by token. Returned
 * by the unauthenticated preview endpoint so an invitee can see who invited
 * them before signing in or creating an account.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationInvitationPreviewOutput
{
  // #region Properties
  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: false)]
  public string $organizationId = '';

  /**
   * Property organizationName.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $organizationName = '';

  /**
   * Property organizationLogoUrl.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $organizationLogoUrl = null;

  /**
   * Property inviterDisplayName.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $inviterDisplayName = '';

  /**
   * Property invitedEmail.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $invitedEmail = '';

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $status = '';

  /**
   * Property expiresAt.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $expiresAt = '';

  /**
   * Property roleNames.
   *
   * Display names of the roles the invitation grants. Names only — this
   * endpoint is unauthenticated, so no role identifiers or permissions are
   * exposed.
   *
   * @var list<string>
   *
   * @since 1.1.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $roleNames = [];
  // #endregion
}
