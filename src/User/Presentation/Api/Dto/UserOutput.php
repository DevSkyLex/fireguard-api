<?php

declare(strict_types=1);

namespace User\Presentation\Api\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use User\Presentation\Api\Serialization\UserSerializationGroup;

/**
 * DTO UserOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UserOutput
{
    // #region Properties
    /**
     * Property id.
     *
     * The user ID.
     */
    #[Groups(groups: [UserSerializationGroup::READ])]
    public ?string $id = null;

    /**
     * Property username.
     *
     * The username.
     */
    #[Groups(groups: [UserSerializationGroup::READ])]
    public ?string $username = null;

    /**
     * Property email.
     *
     * The user email.
     */
    #[Groups(groups: [UserSerializationGroup::READ])]
    public ?string $email = null;

    /**
     * Property firstName.
     *
     * The first name.
     */
    #[Groups(groups: [UserSerializationGroup::READ])]
    public ?string $firstName = null;

    /**
     * Property lastName.
     *
     * The last name.
     */
    #[Groups(groups: [UserSerializationGroup::READ])]
    public ?string $lastName = null;

    /**
     * Property avatarUrl.
     *
     * The avatar URL.
     */
    #[Groups(groups: [UserSerializationGroup::READ])]
    public ?string $avatarUrl = null;

    /**
     * Property status.
     *
     * The user status.
     */
    #[Groups(groups: [UserSerializationGroup::READ])]
    public ?string $status = null;

    /**
     * Property emailVerified.
     *
     * Whether the email is verified.
     */
    #[Groups(groups: [UserSerializationGroup::READ])]
    public bool $emailVerified = false;

    /**
     * Property tenantId.
     *
     * The tenant ID.
     */
    #[Groups(groups: [UserSerializationGroup::READ])]
    public ?string $tenantId = null;

    /**
     * Property createdAt.
     *
     * The creation timestamp.
     */
    #[Groups(groups: [UserSerializationGroup::READ])]
    public ?string $createdAt = null;

    /**
     * Property lastLoginAt.
     *
     * The last login timestamp.
     */
    #[Groups(groups: [UserSerializationGroup::READ])]
    public ?string $lastLoginAt = null;
    // #endregion
}
