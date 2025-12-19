<?php

declare(strict_types=1);

namespace Session\Presentation\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

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
    #[Groups(['session:read'])]
    public string $id = '';

    /**
     * Property userId.
     *
     * The user ID.
     */
    #[Groups(['session:read'])]
    public string $userId = '';

    /**
     * Property ipAddress.
     *
     * The client IP address.
     */
    #[Groups(['session:read'])]
    public string $ipAddress = '';

    /**
     * Property userAgent.
     *
     * The client user agent.
     */
    #[Groups(['session:read'])]
    public string $userAgent = '';

    /**
     * Property deviceType.
     *
     * The device type.
     */
    #[Groups(['session:read'])]
    public ?string $deviceType = null;

    /**
     * Property browser.
     *
     * The browser name.
     */
    #[Groups(['session:read'])]
    public ?string $browser = null;

    /**
     * Property createdAt.
     *
     * The creation timestamp.
     */
    #[Groups(['session:read'])]
    public string $createdAt = '';

    /**
     * Property lastActivityAt.
     *
     * The last activity timestamp.
     */
    #[Groups(['session:read'])]
    public string $lastActivityAt = '';

    /**
     * Property isActive.
     *
     * Whether the session is active.
     */
    #[Groups(['session:read'])]
    public bool $isActive = true;

    /**
     * Property isCurrent.
     *
     * Whether this is the current session.
     */
    #[Groups(['session:read'])]
    public bool $isCurrent = false;
    // #endregion
}
