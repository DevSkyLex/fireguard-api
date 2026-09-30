<?php

declare(strict_types=1);

namespace Organization\Domain\Model\OrganizationMember;

use DateTimeImmutable;
use DateTimeZone;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};

/**
 * Model OrganizationMember.
 *
 * @category Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationMember
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Initializes membership identity, organization scope and active lifecycle state.
   *
   * @access private
   *
   * @param OrganizationMemberId $id organization member identifier
   * @param OrganizationId $organizationId organization identifier for this membership
   * @param string $userId user account linked to the membership
   * @param bool $isActive whether the membership is active
   * @param DateTimeImmutable $joinedAt timestamp when the membership began
   *
   * @return void
   */
  private function __construct(
    private OrganizationMemberId $id,
    private OrganizationId $organizationId,
    private string $userId,
    private bool $isActive,
    private DateTimeImmutable $joinedAt,
  ) {
  }
  // #endregion

  // #region Methods

  /**
   * Method join.
   *
   * Creates a new membership in the supplied organization for the identified user.
   *
   * @access public
   * @since 1.0.0
   *
   * @param OrganizationMemberId $id the id value
   * @param OrganizationId $organizationId the organization identifier
   * @param string $userId the user identifier
   *
   * @return self the new active membership
   */
  public static function join(OrganizationMemberId $id, OrganizationId $organizationId, string $userId): self
  {
    return new self(
      id: $id,
      organizationId: $organizationId,
      userId: $userId,
      isActive: true,
      joinedAt: new DateTimeImmutable('now', new DateTimeZone('UTC')),
    );
  }

  /**
   * Method reconstitute.
   *
   * Reconstitutes an aggregate from persisted state.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberId $id the id value
   * @param OrganizationId $organizationId the organization identifier
   * @param string $userId the user identifier
   * @param bool $isActive the activation flag
   * @param DateTimeImmutable $joinedAt the joined at value
   *
   * @return self the reconstitute result
   */
  public static function reconstitute(
    OrganizationMemberId $id,
    OrganizationId $organizationId,
    string $userId,
    bool $isActive,
    DateTimeImmutable $joinedAt,
  ): self {
    return new self($id, $organizationId, $userId, $isActive, $joinedAt);
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   */
  public function id(): OrganizationMemberId
  {
    return $this->id;
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   */
  public function organizationId(): OrganizationId
  {
    return $this->organizationId;
  }

  /**
   * Method userId.
   *
   * @since 1.0.0
   */
  public function userId(): string
  {
    return $this->userId;
  }

  /**
   * Method isActive.
   *
   * @since 1.0.0
   */
  public function isActive(): bool
  {
    return $this->isActive;
  }

  /**
   * Method joinedAt.
   *
   * @since 1.0.0
   */
  public function joinedAt(): DateTimeImmutable
  {
    return $this->joinedAt;
  }

  /**
   * Method deactivate.
   *
   * @since 1.0.0
   */
  public function deactivate(): void
  {
    $this->isActive = false;
  }

  /**
   * Method activate.
   *
   * @since 1.0.0
   */
  public function activate(): void
  {
    $this->isActive = true;
  }
  // #endregion
}
