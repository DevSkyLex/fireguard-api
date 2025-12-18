<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Record ClientRecord
 * @final
 *
 * Doctrine entity for Client persistence.
 *
 * @category Record
 * @package OAuth\Infrastructure\Persistence\Record
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'clients')]
final class ClientRecord
{
	//#region Properties
	/**
	 * Property id
	 *
	 * The client ID.
	 *
	 * @access public
	 * @since 1.0.0
	 *
	 * @var Uuid
	 */
	#[ORM\Id]
	#[ORM\Column(type: UuidType::NAME, unique: true)]
	public Uuid $id;

	/**
	 * Property name
	 *
	 * The client name.
	 *
	 * @var string
	 */
	#[ORM\Column(type: 'string', length: 100)]
	public string $name;

	/**
	 * Property secret
	 *
	 * The hashed client secret.
	 *
	 * @access public
	 * @since 1.0.0
	 *
	 * @var string
	 */
	#[ORM\Column(type: 'string', length: 255)]
	public string $secret;

	/**
	 * Property redirectUris
	 *
	 * The allowed redirect URIs.
	 *
	 * @access public
	 * @since 1.0.0
	 *
	 * @var array<string>
	 */
	#[ORM\Column(type: 'json')]
	public array $redirectUris = [];

	/**
	 * Property grantTypes
	 *
	 * The allowed grant types.
	 *
	 * @access public
	 * @since 1.0.0
	 *
	 * @var array<string>
	 */
	#[ORM\Column(type: 'json')]
	public array $grantTypes = [];

	/**
	 * Property scopes
	 *
	 * The allowed scopes.
	 *
	 * @access public
	 * @since 1.0.0
	 *
	 * @var array<string>
	 */
	#[ORM\Column(type: 'json')]
	public array $scopes = [];

	/**
	 * Property isActive
	 *
	 * Whether the client is active.
	 *
	 * @access public
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	#[ORM\Column(type: 'boolean')]
	public bool $isActive;

	/**
	 * Property createdAt
	 *
	 * The creation timestamp.
	 *
	 * @access public
	 * @since 1.0.0
	 *
	 * @var DateTimeImmutable
	 */
	#[ORM\Column(type: 'datetime_immutable')]
	public DateTimeImmutable $createdAt;

	/**
	 * Property deletedAt
	 *
	 * The deletion timestamp (null if not deleted).
	 *
	 * @access public
	 * @since 1.0.0
	 *
	 * @var DateTimeImmutable|null
	 */
	#[ORM\Column(type: 'datetime_immutable', nullable: true)]
	public ?DateTimeImmutable $deletedAt = null;
	//#endregion
}
