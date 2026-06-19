<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationQuota;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetOrganizationQuotaResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationQuotaResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetOrganizationQuotaResult class.
   *
   * @since 1.0.0
   *
   * @param list<array{resource: string, used: int, limit: int|null}> $items the per-resource quota usage
   */
  public function __construct(
    public array $items,
  ) {
  }
  // #endregion
}
