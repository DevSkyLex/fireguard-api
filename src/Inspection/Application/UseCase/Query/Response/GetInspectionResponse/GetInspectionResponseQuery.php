<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Response\GetInspectionResponse;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetInspectionResponseQuery.
 *
 * Deliberately unscoped by organization: `InspectionResponseProcessor` asks
 * this BEFORE it can permission-check, because the organization to check
 * against is the one carried by the response itself. The gate that follows
 * is what makes the read safe, and it answers 404 for a response outside the
 * caller's scope.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetInspectionResponseQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $responseId,
  ) {
  }
  // #endregion
}
