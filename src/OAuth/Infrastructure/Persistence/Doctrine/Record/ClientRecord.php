<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Record ClientRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'clients')]
final class ClientRecord
{
    // #region Properties
    /**
     * Property id.
     *
     * The client ID.
     *
     * @since 1.0.0
     */
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    public Uuid $id;

    /**
     * Property name.
     *
     * The client name.
     */
    #[ORM\Column(type: 'string', length: 100)]
    public string $name;

    /**
     * Property secret.
     *
     * The hashed client secret.
     *
     * @since 1.0.0
     */
    #[ORM\Column(type: 'string', length: 255)]
    public string $secret;

    /**
     * Property redirectUris.
     *
     * The allowed redirect URIs.
     *
     * @since 1.0.0
     *
     * @var array<string>
     */
    #[ORM\Column(type: 'json')]
    public array $redirectUris = [];

    /**
     * Property grantTypes.
     *
     * The allowed grant types.
     *
     * @since 1.0.0
     *
     * @var array<string>
     */
    #[ORM\Column(type: 'json')]
    public array $grantTypes = [];

    /**
     * Property scopes.
     *
     * The allowed scopes.
     *
     * @since 1.0.0
     *
     * @var array<string>
     */
    #[ORM\Column(type: 'json')]
    public array $scopes = [];

    /**
     * Property isActive.
     *
     * Whether the client is active.
     *
     * @since 1.0.0
     */
    #[ORM\Column(type: 'boolean')]
    public bool $isActive;

    /**
     * Property createdAt.
     *
     * The creation timestamp.
     *
     * @since 1.0.0
     */
    #[ORM\Column(type: 'datetime_immutable')]
    public DateTimeImmutable $createdAt;

    /**
     * Property deletedAt.
     *
     * The deletion timestamp (null if not deleted).
     *
     * @since 1.0.0
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?DateTimeImmutable $deletedAt = null;
    // #endregion
}
