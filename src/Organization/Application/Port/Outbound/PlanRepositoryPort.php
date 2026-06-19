<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{PlanId, PlanKey};

/**
 * Port PlanRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface PlanRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists a plan aggregate.
   *
   * @since 1.0.0
   *
   * @param Plan $plan the plan aggregate
   */
  public function save(Plan $plan): void;

  /**
   * Method findById.
   *
   * Finds a plan by identifier.
   *
   * @since 1.0.0
   *
   * @param PlanId $id the plan identifier
   *
   * @return ?Plan the plan aggregate when found
   */
  public function findById(PlanId $id): ?Plan;

  /**
   * Method findByKey.
   *
   * Finds a plan by its stable machine key.
   *
   * @since 1.0.0
   *
   * @param PlanKey $key the plan key
   *
   * @return ?Plan the plan aggregate when found
   */
  public function findByKey(PlanKey $key): ?Plan;

  /**
   * Method findDefault.
   *
   * Finds the catalog default plan.
   *
   * @since 1.0.0
   *
   * @return ?Plan the default plan when configured
   */
  public function findDefault(): ?Plan;

  /**
   * Method findAll.
   *
   * Returns every plan ordered by display order.
   *
   * @since 1.0.0
   *
   * @return list<Plan> the plans collection
   */
  public function findAll(): array;

  /**
   * Method findAllActive.
   *
   * Returns every selectable plan ordered by display order.
   *
   * @since 1.0.0
   *
   * @return list<Plan> the active plans collection
   */
  public function findAllActive(): array;

  /**
   * Method delete.
   *
   * Deletes a plan by identifier.
   *
   * @since 1.0.0
   *
   * @param PlanId $id the plan identifier
   */
  public function delete(PlanId $id): void;
  // #endregion
}
