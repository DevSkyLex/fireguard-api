<?php

declare(strict_types=1);

namespace Workload\Application\UseCase\Query\Projection\GetWorkload;

use Shared\Application\Message\QueryMessage;

/**
 * GetWorkloadQuery.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetWorkloadQuery implements QueryMessage
{
  /**
   * @since 1.0.0
   *
   * @param string $userId authenticated account identifier used for authorization
   * @param string $organizationId organization identifier that scopes this operation
   * @param string $from inclusive first local date in the requested period
   * @param string $to inclusive last local date in the requested period
   * @param ?string $memberId optional member filter; null retains the authorized member set
   * @param ?string $teamId optional team filter; selected members retain their full organization load
   * @param bool $overloadedOnly whether to retain only members with daily overload
   * @param int $page one-based member page, applied after projection and filtering
   * @param int $pageSize maximum members returned on one page
   */
  public function __construct(public string $userId, public string $organizationId, public string $from, public string $to, public ?string $memberId = null, public ?string $teamId = null, public bool $overloadedOnly = false, public int $page = 1, public int $pageSize = 10)
  {
  }
}
