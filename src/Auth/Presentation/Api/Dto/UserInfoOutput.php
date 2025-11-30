<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto;

use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * DTO UserInfoOutput
 * @final
 *
 * DTO for OpenID Connect UserInfo endpoint response (RFC 7519).
 * Returns claims about the authenticated End-User.
 *
 * @category DTO
 * @package Auth\Presentation\Api\Dto
 * @version 1.0.0
 *
 * @see https://openid.net/specs/openid-connect-core-1_0.html#UserInfo
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UserInfoOutput
{
  //#region Properties
  /**
   * Property sub
   *
   * Subject - Identifier for the End-User at the Issuer.
   * REQUIRED.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $sub = null;

  /**
   * Property name
   *
   * End-User's full name.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $name = null;

  /**
   * Property givenName
   *
   * Given name(s) or first name(s) of the End-User.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[SerializedName('given_name')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $givenName = null;

  /**
   * Property familyName
   *
   * Surname(s) or last name(s) of the End-User.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[SerializedName('family_name')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $familyName = null;

  /**
   * Property preferredUsername
   *
   * Shorthand name by which the End-User wishes to be referred to.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[SerializedName('preferred_username')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $preferredUsername = null;

  /**
   * Property email
   *
   * End-User's preferred e-mail address.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $email = null;

  /**
   * Property emailVerified
   *
   * True if the End-User's e-mail address has been verified.
   *
   * @access public
   * @since 1.0.0
   *
   * @var bool|null
   */
  #[SerializedName('email_verified')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?bool $emailVerified = null;

  /**
   * Property updatedAt
   *
   * Time the End-User's information was last updated (Unix timestamp).
   *
   * @access public
   * @since 1.0.0
   *
   * @var int|null
   */
  #[SerializedName('updated_at')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?int $updatedAt = null;
  //#endregion
}
