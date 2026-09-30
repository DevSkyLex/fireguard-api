<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Response\ListInspectionResponses;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListInspectionResponsesQuery.
 *
 * `organizationId` is required and already resolved — the caller ran
 * `ResolveInspectionResponseScopeQuery` and permission-checked the answer
 * before asking for rows. `recordStatus` is nullable so the handler can
 * apply the endpoint's default, which depends on whether an intervention was
 * named.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInspectionResponsesQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries organization scope, optional linkage filters, and pagination for canonical response listing.
   *
   * @access public
   *
   * @param string $organizationId organization whose responses are listed
   * @param ?string $interventionId optional linked intervention filter
   * @param ?string $inspectionId optional inspection filter
   * @param ?string $recordStatus optional canonical publication status filter
   * @param int $page one-based page number
   * @param int $itemsPerPage maximum number of rows requested for the page
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public ?string $interventionId = null,
    public ?string $inspectionId = null,
    public ?string $recordStatus = null,
    public int $page = 1,
    public int $itemsPerPage = 50,
  ) {
  }
  // #endregion
}
