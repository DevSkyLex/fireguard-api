<?php

declare(strict_types=1);

namespace Organization\Domain\Model\Plan;

use DateTimeImmutable;
use Organization\Domain\Catalog\OrganizationQuotaCatalog;
use Organization\Domain\ValueObject\{OrganizationQuotaResource, PlanId, PlanKey};
use Shared\Domain\Exception\InvalidValueException;

use function mb_strlen;
use function trim;

/**
 * Model Plan.
 *
 * Subscription plan aggregate. A plan is a named catalog entry that caps the
 * quantity of countable resources (members, facilities, equipment, inspections)
 * for every organization assigned to it. Limits are stored as a map of resource
 * key => integer cap; a resource absent from the map is unlimited. Plans are
 * managed in the database and editable without a redeploy.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Plan
{
  // #region Properties
  /**
   * Property limits.
   *
   * The per-resource quantity caps. Keyed by {@see OrganizationQuotaResource}
   * value; an absent resource means unlimited.
   *
   * @var array<string, int>
   *
   * @since 1.0.0
   */
  private array $limits;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the Plan class.
   *
   * @since 1.0.0
   *
   * @param PlanId $id the plan identifier
   * @param PlanKey $key the stable machine key
   * @param string $name the display name
   * @param array<string, int> $limits the per-resource quantity caps
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   * @param ?string $description the optional description
   * @param bool $isActive whether the plan can be selected
   * @param bool $isDefault whether the plan is the catalog default
   * @param int $sortOrder the display order
   */
  private function __construct(
    private PlanId $id,
    private PlanKey $key,
    private string $name,
    array $limits,
    private DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
    private ?string $description = null,
    private bool $isActive = true,
    private bool $isDefault = false,
    private int $sortOrder = 0,
  ) {
    $this->name = self::normalizeName($name);
    $this->limits = OrganizationQuotaCatalog::normalizeLimits($limits);
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * Creates a new plan aggregate.
   *
   * @since 1.0.0
   *
   * @param PlanId $id the plan identifier
   * @param PlanKey $key the stable machine key
   * @param string $name the display name
   * @param array<string, int> $limits the per-resource quantity caps
   * @param ?string $description the optional description
   * @param bool $isActive whether the plan can be selected
   * @param bool $isDefault whether the plan is the catalog default
   * @param int $sortOrder the display order
   *
   * @return self the created plan aggregate
   */
  public static function create(
    PlanId $id,
    PlanKey $key,
    string $name,
    array $limits,
    ?string $description = null,
    bool $isActive = true,
    bool $isDefault = false,
    int $sortOrder = 0,
  ): self {
    $now = new DateTimeImmutable();

    return new self(
      id: $id,
      key: $key,
      name: $name,
      limits: $limits,
      createdAt: $now,
      updatedAt: $now,
      description: $description,
      isActive: $isActive,
      isDefault: $isDefault,
      sortOrder: $sortOrder,
    );
  }

  /**
   * Method reconstitute.
   *
   * Reconstitutes a plan aggregate from persisted state.
   *
   * @since 1.0.0
   *
   * @param PlanId $id the plan identifier
   * @param PlanKey $key the stable machine key
   * @param string $name the display name
   * @param array<string, int> $limits the per-resource quantity caps
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   * @param ?string $description the optional description
   * @param bool $isActive whether the plan can be selected
   * @param bool $isDefault whether the plan is the catalog default
   * @param int $sortOrder the display order
   *
   * @return self the reconstituted plan aggregate
   */
  public static function reconstitute(
    PlanId $id,
    PlanKey $key,
    string $name,
    array $limits,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
    ?string $description = null,
    bool $isActive = true,
    bool $isDefault = false,
    int $sortOrder = 0,
  ): self {
    return new self(
      id: $id,
      key: $key,
      name: $name,
      limits: $limits,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      description: $description,
      isActive: $isActive,
      isDefault: $isDefault,
      sortOrder: $sortOrder,
    );
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   *
   * @return PlanId the plan identifier
   */
  public function id(): PlanId
  {
    return $this->id;
  }

  /**
   * Method key.
   *
   * @since 1.0.0
   *
   * @return PlanKey the stable machine key
   */
  public function key(): PlanKey
  {
    return $this->key;
  }

  /**
   * Method name.
   *
   * @since 1.0.0
   *
   * @return string the display name
   */
  public function name(): string
  {
    return $this->name;
  }

  /**
   * Method description.
   *
   * @since 1.0.0
   *
   * @return ?string the description when set
   */
  public function description(): ?string
  {
    return $this->description;
  }

  /**
   * Method limits.
   *
   * Returns the per-resource quantity caps. A resource absent from the map is
   * unlimited.
   *
   * @since 1.0.0
   *
   * @return array<string, int> the limit map
   */
  public function limits(): array
  {
    return $this->limits;
  }

  /**
   * Method limitFor.
   *
   * Returns the cap for a resource, or null when the resource is unlimited.
   *
   * @since 1.0.0
   *
   * @param OrganizationQuotaResource $resource the resource to look up
   *
   * @return ?int the cap, or null when unlimited
   */
  public function limitFor(OrganizationQuotaResource $resource): ?int
  {
    return $this->limits[$resource->value] ?? null;
  }

  /**
   * Method isActive.
   *
   * @since 1.0.0
   *
   * @return bool true when the plan can be selected
   */
  public function isActive(): bool
  {
    return $this->isActive;
  }

  /**
   * Method isDefault.
   *
   * @since 1.0.0
   *
   * @return bool true when the plan is the catalog default
   */
  public function isDefault(): bool
  {
    return $this->isDefault;
  }

  /**
   * Method sortOrder.
   *
   * @since 1.0.0
   *
   * @return int the display order
   */
  public function sortOrder(): int
  {
    return $this->sortOrder;
  }

  /**
   * Method createdAt.
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
   * Updates the display name and description.
   *
   * @since 1.0.0
   *
   * @param string $name the new display name
   * @param ?string $description the new description
   */
  public function rename(string $name, ?string $description): void
  {
    $this->name = self::normalizeName($name);
    $normalized = null !== $description ? trim($description) : null;
    $this->description = '' === $normalized ? null : $normalized;
    $this->touch();
  }

  /**
   * Method changeLimits.
   *
   * Replaces the per-resource quantity caps.
   *
   * @since 1.0.0
   *
   * @param array<string, int> $limits the new limit map
   */
  public function changeLimits(array $limits): void
  {
    $this->limits = OrganizationQuotaCatalog::normalizeLimits($limits);
    $this->touch();
  }

  /**
   * Method activate.
   *
   * Marks the plan as selectable.
   *
   * @since 1.0.0
   */
  public function activate(): void
  {
    $this->isActive = true;
    $this->touch();
  }

  /**
   * Method deactivate.
   *
   * Marks the plan as non-selectable.
   *
   * @since 1.0.0
   */
  public function deactivate(): void
  {
    $this->isActive = false;
    $this->touch();
  }

  /**
   * Method markDefault.
   *
   * Flags the plan as the catalog default.
   *
   * @since 1.0.0
   */
  public function markDefault(): void
  {
    $this->isDefault = true;
    $this->touch();
  }

  /**
   * Method unmarkDefault.
   *
   * Clears the catalog default flag.
   *
   * @since 1.0.0
   */
  public function unmarkDefault(): void
  {
    $this->isDefault = false;
    $this->touch();
  }

  /**
   * Method changeSortOrder.
   *
   * Updates the display order.
   *
   * @since 1.0.0
   *
   * @param int $sortOrder the new display order
   */
  public function changeSortOrder(int $sortOrder): void
  {
    $this->sortOrder = $sortOrder;
    $this->touch();
  }

  /**
   * Method normalizeName.
   *
   * Validates and normalizes a plan display name.
   *
   * @since 1.0.0
   *
   * @param string $name the raw name
   *
   * @return string the normalized name
   */
  private static function normalizeName(string $name): string
  {
    $normalized = trim($name);
    $length = mb_strlen($normalized);

    if ($length < 2 || $length > 120) {
      throw InvalidValueException::because('Plan name must be between 2 and 120 characters.');
    }

    return $normalized;
  }

  /**
   * Method touch.
   *
   * Updates the last modification timestamp.
   *
   * @since 1.0.0
   */
  private function touch(): void
  {
    $this->updatedAt = new DateTimeImmutable();
  }
  // #endregion
}
