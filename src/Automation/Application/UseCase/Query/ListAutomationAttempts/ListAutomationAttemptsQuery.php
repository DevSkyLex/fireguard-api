<?php

declare(strict_types=1);

namespace Automation\Application\UseCase\Query\ListAutomationAttempts;

use Shared\Application\Message\QueryMessage;

/**
 * Class ListAutomationAttemptsQuery
 *
 * Requests an organization's automation attempt history or a selected attempt.
 *
 * @category Query
 */
final readonly class ListAutomationAttemptsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries the organization history filters and one-based pagination request.
   *
   * @access public
   *
   * @param string $actorUserId the user requesting the history
   * @param string $organizationId the owning organization
   * @param int $page the one-based result page
   * @param int $itemsPerPage the requested page size
   * @param string|null $attemptId the optional attempt identifier filter
   *
   * @return void
   */
  public function __construct(public string $actorUserId, public string $organizationId, public int $page = 1, public int $itemsPerPage = 30, public ?string $attemptId = null)
  {
  }
  // #endregion
}
