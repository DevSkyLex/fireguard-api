<?php

declare(strict_types=1);

namespace User\Presentation\Api\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use User\Presentation\Api\Serialization\UserSerializationGroup;

/**
 * DTO UserInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UserInput
{
    // #region Properties
    /**
     * Property username.
     *
     * The username.
     */
    #[Groups([UserSerializationGroup::WRITE])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 50)]
    public ?string $username = null;

    /**
     * Property email.
     *
     * The user email.
     */
    #[Groups([UserSerializationGroup::WRITE])]
    #[Assert\NotBlank]
    #[Assert\Email]
    public ?string $email = null;

    /**
     * Property password.
     *
     * The user password.
     */
    #[Groups([UserSerializationGroup::WRITE])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 8)]
    public ?string $password = null;

    /**
     * Property firstName.
     *
     * The first name.
     */
    #[Groups([UserSerializationGroup::WRITE])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public ?string $firstName = null;

    /**
     * Property lastName.
     *
     * The last name.
     */
    #[Groups([UserSerializationGroup::WRITE])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public ?string $lastName = null;

    /**
     * Property avatarUrl.
     *
     * The avatar URL.
     */
    #[Groups([UserSerializationGroup::WRITE])]
    public ?string $avatarUrl = null;

    /**
     * Property tenantId.
     *
     * The tenant ID.
     */
    #[Groups([UserSerializationGroup::WRITE])]
    public ?string $tenantId = null;
    // #endregion
}
