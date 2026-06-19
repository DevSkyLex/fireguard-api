<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Plan\GetPlan;

use DateTimeImmutable;
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\OrganizationQuotaResource;
use Shared\Application\Message\ResultMessage;

use function array_map;

/**
 * UseCase GetPlanResult.
 *
 * Read model for a single subscription plan, shared by the get and list plan
 * queries.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetPlanResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetPlanResult class.
   *
   * @since 1.0.0
   *
   * @param string $id the plan identifier
   * @param string $key the stable machine key
   * @param string $name the display name
   * @param array<string, int> $limits the per-resource quantity caps
   * @param list<array{resource: string, label: string, limit: int|null, summary: string}> $quotas the per-resource allowance descriptors (every resource, phrased)
   * @param bool $isActive whether the plan can be selected
   * @param bool $isDefault whether the plan is the catalog default
   * @param int $sortOrder the display order
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   * @param ?string $description the optional description
   */
  public function __construct(
    public string $id,
    public string $key,
    public string $name,
    public array $limits,
    public array $quotas,
    public bool $isActive,
    public bool $isDefault,
    public int $sortOrder,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public ?string $description = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method fromDomain.
   *
   * Builds a read model from a plan aggregate.
   *
   * @since 1.0.0
   *
   * @param Plan $plan the plan aggregate
   *
   * @return self the plan read model
   */
  public static function fromDomain(Plan $plan): self
  {
    $quotas = array_map(
      static function (OrganizationQuotaResource $resource) use ($plan): array {
        $limit = $plan->limitFor($resource);

        return [
          'resource' => $resource->value,
          'label' => $resource->label(),
          'limit' => $limit,
          'summary' => $resource->summarize($limit),
        ];
      },
      OrganizationQuotaResource::cases(),
    );

    return new self(
      id: (string) $plan->id(),
      key: (string) $plan->key(),
      name: $plan->name(),
      limits: $plan->limits(),
      quotas: $quotas,
      isActive: $plan->isActive(),
      isDefault: $plan->isDefault(),
      sortOrder: $plan->sortOrder(),
      createdAt: $plan->createdAt(),
      updatedAt: $plan->updatedAt(),
      description: $plan->description(),
    );
  }
  // #endregion
}
