<?php

declare(strict_types=1);

namespace Equipment\Domain\Model\Tag;

use DateTimeImmutable;
use Equipment\Domain\ValueObject\{EquipmentOrganizationId, TagId};

/**
 * Model Tag.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Tag
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param TagId $id the tag identifier
   * @param EquipmentOrganizationId $organizationId the organization identifier
   * @param string $name the tag name
   * @param DateTimeImmutable $createdAt the creation timestamp
   */
  private function __construct(
    private TagId $id,
    private EquipmentOrganizationId $organizationId,
    private string $name,
    private DateTimeImmutable $createdAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * Creates a new tag.
   *
   * @since 1.0.0
   *
   * @param TagId $id the tag identifier
   * @param EquipmentOrganizationId $organizationId the organization identifier
   * @param string $name the tag name
   *
   * @return self the created tag
   */
  public static function create(
    TagId $id,
    EquipmentOrganizationId $organizationId,
    string $name,
  ): self {
    return new self(
      id: $id,
      organizationId: $organizationId,
      name: $name,
      createdAt: new DateTimeImmutable(),
    );
  }

  /**
   * Method reconstitute.
   *
   * Reconstitutes a tag from persisted state.
   *
   * @since 1.0.0
   *
   * @param TagId $id the tag identifier
   * @param EquipmentOrganizationId $organizationId the organization identifier
   * @param string $name the tag name
   * @param DateTimeImmutable $createdAt the creation timestamp
   *
   * @return self the reconstituted tag
   */
  public static function reconstitute(
    TagId $id,
    EquipmentOrganizationId $organizationId,
    string $name,
    DateTimeImmutable $createdAt,
  ): self {
    return new self(
      id: $id,
      organizationId: $organizationId,
      name: $name,
      createdAt: $createdAt,
    );
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   */
  public function id(): TagId
  {
    return $this->id;
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   */
  public function organizationId(): EquipmentOrganizationId
  {
    return $this->organizationId;
  }

  /**
   * Method name.
   *
   * @since 1.0.0
   */
  public function name(): string
  {
    return $this->name;
  }

  /**
   * Method createdAt.
   *
   * @since 1.0.0
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }
  // #endregion
}
