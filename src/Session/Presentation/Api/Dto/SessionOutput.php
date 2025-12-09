<?php

declare(strict_types=1);

namespace Session\Presentation\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * DTO SessionOutput
 * @final
 *
 * Output DTO for session data.
 *
 * @category DTO
 * @package Session\Presentation\Api\Dto
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SessionOutput
{
  //#region Properties
  /**
   * Property id
   *
   * The session ID.
   *
   * @var string
   */
  #[Groups(['session:read'])]
  public string $id = '';

  /**
   * Property userId
   *
   * The user ID.
   *
   * @var string
   */
  #[Groups(['session:read'])]
  public string $userId = '';

  /**
   * Property ipAddress
   *
   * The client IP address.
   *
   * @var string
   */
  #[Groups(['session:read'])]
  public string $ipAddress = '';

  /**
   * Property userAgent
   *
   * The client user agent.
   *
   * @var string
   */
  #[Groups(['session:read'])]
  public string $userAgent = '';

  /**
   * Property deviceType
   *
   * The device type.
   *
   * @var string|null
   */
  #[Groups(['session:read'])]
  public ?string $deviceType = null;

  /**
   * Property browser
   *
   * The browser name.
   *
   * @var string|null
   */
  #[Groups(['session:read'])]
  public ?string $browser = null;

  /**
   * Property createdAt
   *
   * The creation timestamp.
   *
   * @var string
   */
  #[Groups(['session:read'])]
  public string $createdAt = '';

  /**
   * Property lastActivityAt
   *
   * The last activity timestamp.
   *
   * @var string
   */
  #[Groups(['session:read'])]
  public string $lastActivityAt = '';

  /**
   * Property isActive
   *
   * Whether the session is active.
   *
   * @var bool
   */
  #[Groups(['session:read'])]
  public bool $isActive = true;

  /**
   * Property isCurrent
   *
   * Whether this is the current session.
   *
   * @var bool
   */
  #[Groups(['session:read'])]
  public bool $isCurrent = false;
  //#endregion
}
