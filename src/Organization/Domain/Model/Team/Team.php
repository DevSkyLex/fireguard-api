<?php

declare(strict_types=1);

namespace Organization\Domain\Model\Team;

use DateTimeImmutable;
use Organization\Domain\ValueObject\{OrganizationId, TeamId, TeamName};

/**
 * Model Team.
 *
 * A named grouping of organization members. Mirrors {@see \Organization\Domain\Model\OrganizationRole\OrganizationRole}:
 * a mutable aggregate with `create()`/`reconstitute()` factories. Team
 * membership itself (the `team_members` join) is a record-level concern,
 * not part of this aggregate's in-memory state.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Team
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the Team class.
   *
   * @since 1.0.0
   *
   * @param TeamId $id the team identifier
   * @param OrganizationId $organizationId the organization identifier
   * @param TeamName $name the team name
   * @param string $description the team description
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   */
  private function __construct(
    private TeamId $id,
    private OrganizationId $organizationId,
    private TeamName $name,
    private string $description,
    private DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * Creates a new team aggregate.
   *
   * @since 1.0.0
   *
   * @param TeamId $id the team identifier
   * @param OrganizationId $organizationId the organization identifier
   * @param TeamName $name the team name
   * @param string $description the team description
   *
   * @return self the created team aggregate
   */
  public static function create(
    TeamId $id,
    OrganizationId $organizationId,
    TeamName $name,
    string $description = '',
  ): self {
    $now = new DateTimeImmutable();

    return new self(
      id: $id,
      organizationId: $organizationId,
      name: $name,
      description: $description,
      createdAt: $now,
      updatedAt: $now,
    );
  }

  /**
   * Method reconstitute.
   *
   * Reconstitutes a team aggregate from persisted state.
   *
   * @since 1.0.0
   *
   * @param TeamId $id the team identifier
   * @param OrganizationId $organizationId the organization identifier
   * @param TeamName $name the team name
   * @param string $description the team description
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   *
   * @return self the reconstituted team aggregate
   */
  public static function reconstitute(
    TeamId $id,
    OrganizationId $organizationId,
    TeamName $name,
    string $description,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
  ): self {
    return new self($id, $organizationId, $name, $description, $createdAt, $updatedAt);
  }

  /**
   * Method id.
   *
   * Returns the team identifier.
   *
   * @since 1.0.0
   *
   * @return TeamId the team identifier
   */
  public function id(): TeamId
  {
    return $this->id;
  }

  /**
   * Method organizationId.
   *
   * Returns the organization identifier.
   *
   * @since 1.0.0
   *
   * @return OrganizationId the organization identifier
   */
  public function organizationId(): OrganizationId
  {
    return $this->organizationId;
  }

  /**
   * Method name.
   *
   * Returns the team name.
   *
   * @since 1.0.0
   *
   * @return TeamName the team name
   */
  public function name(): TeamName
  {
    return $this->name;
  }

  /**
   * Method description.
   *
   * Returns the team description.
   *
   * @since 1.0.0
   *
   * @return string the team description
   */
  public function description(): string
  {
    return $this->description;
  }

  /**
   * Method createdAt.
   *
   * Returns the creation timestamp.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the creation timestamp
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Method updatedAt.
   *
   * Returns the last update timestamp.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the last update timestamp
   */
  public function updatedAt(): DateTimeImmutable
  {
    return $this->updatedAt;
  }

  /**
   * Method rename.
   *
   * Renames the team.
   *
   * @since 1.0.0
   *
   * @param TeamName $name the new team name
   */
  public function rename(TeamName $name): void
  {
    $this->name = $name;
    $this->touch();
  }

  /**
   * Method describe.
   *
   * Updates the team description.
   *
   * @since 1.0.0
   *
   * @param string $description the new description
   */
  public function describe(string $description): void
  {
    $this->description = $description;
    $this->touch();
  }

  /**
   * Method touch.
   *
   * Refreshes the last update timestamp.
   *
   * @since 1.0.0
   */
  private function touch(): void
  {
    $this->updatedAt = new DateTimeImmutable();
  }
  // #endregion
}
