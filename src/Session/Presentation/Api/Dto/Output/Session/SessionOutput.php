<?php

declare(strict_types=1);

namespace Session\Presentation\Api\Dto\Output\Session;

use Session\Presentation\Api\Serialization\SessionSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO SessionOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SessionOutput
{
  // #region Properties
  /**
   * Property id.
   *
   * The session ID.
   */
  #[Groups(groups: [SessionSerializationGroup::READ])]
  public string $id = '';

  /**
   * Property userId.
   *
   * The user ID.
   */
  #[Groups(groups: [SessionSerializationGroup::READ])]
  public string $userId = '';

  /**
   * Property ipAddress.
   *
   * The client IP address.
   */
  #[Groups(groups: [SessionSerializationGroup::READ])]
  public string $ipAddress = '';

  /**
   * Property userAgent.
   *
   * The client user agent.
   */
  #[Groups(groups: [SessionSerializationGroup::READ])]
  public string $userAgent = '';

  /**
   * Property deviceType.
   *
   * The device type.
   */
  #[Groups(groups: [SessionSerializationGroup::READ])]
  public ?string $deviceType = null;

  /**
   * Property browser.
   *
   * The browser name.
   */
  #[Groups(groups: [SessionSerializationGroup::READ])]
  public ?string $browser = null;

  /**
   * Property createdAt.
   *
   * The creation timestamp.
   */
  #[Groups(groups: [SessionSerializationGroup::READ])]
  public string $createdAt = '';

  /**
   * Property lastActivityAt.
   *
   * The last activity timestamp.
   */
  #[Groups(groups: [SessionSerializationGroup::READ])]
  public string $lastActivityAt = '';

  /**
   * Property isActive.
   *
   * Whether the session is active.
   */
  #[Groups(groups: [SessionSerializationGroup::READ])]
  public bool $isActive = true;

  /**
   * Property isCurrent.
   *
   * Whether this is the current session.
   */
  #[Groups(groups: [SessionSerializationGroup::READ])]
  public bool $isCurrent = false;
  // #endregion
}
