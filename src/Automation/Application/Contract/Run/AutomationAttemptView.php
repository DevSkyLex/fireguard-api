<?php

declare(strict_types=1);

namespace Automation\Application\Contract\Run;

/** View AutomationAttemptView. Safe organization-scoped execution history. */
final readonly class AutomationAttemptView
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Projects one organization-scoped automation attempt for execution history and retry decisions.
   *
   * @access public
   *
   * @param string $id identifier of the automation attempt
   * @param string $runId identifier of the run containing the attempt
   * @param string $organizationId organization that owns the run
   * @param string $ruleKey automation rule that produced the attempt
   * @param string $subjectId domain subject evaluated by the rule
   * @param int $attemptNumber ordinal number of this attempt
   * @param string $status outcome state recorded for the attempt
   * @param string $createdAt timestamp when the attempt was created
   * @param ?string $finishedAt completion timestamp, when available
   * @param ?string $requestedBy actor identifier that requested execution, when available
   * @param ?string $interventionId created intervention identifier, when one exists
   * @param ?string $errorCode stable failure code, when the attempt failed
   * @param bool $canRetry whether current policy allows this attempt to be retried
   *
   * @return void
   */
  public function __construct(public string $id, public string $runId, public string $organizationId, public string $ruleKey, public string $subjectId, public int $attemptNumber, public string $status, public string $createdAt, public ?string $finishedAt, public ?string $requestedBy, public ?string $interventionId, public ?string $errorCode, public bool $canRetry)
  {
  }
  // #endregion
}
