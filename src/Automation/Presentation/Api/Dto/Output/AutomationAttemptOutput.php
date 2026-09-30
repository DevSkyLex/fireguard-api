<?php

declare(strict_types=1);

namespace Automation\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Automation\Application\Contract\Run\AutomationAttemptView;

/** Output AutomationAttemptOutput. Sanitized attempt history and server-owned retry capability. */
final readonly class AutomationAttemptOutput
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Initializes the AutomationAttemptOutput dependencies and state.
   *
   * @access public
   *
   * @param string $id the identifier
   * @param string $runId the run identifier
   * @param string $organizationId the organization identifier
   * @param string $ruleKey the rule key
   * @param string $subjectId the subject identifier
   * @param int $attemptNumber the attempt number
   * @param string $status the status
   * @param string $createdAt the created time
   * @param ?string $finishedAt the finished time
   * @param ?string $requestedBy the requested by
   * @param ?string $interventionId the intervention identifier
   * @param ?string $errorCode the error code
   * @param bool $canRetry the can retry
   *
   * @return void
   */
  public function __construct(#[ApiProperty(identifier: true)] public string $id, public string $runId, public string $organizationId, public string $ruleKey, public string $subjectId, public int $attemptNumber, public string $status, public string $createdAt, public ?string $finishedAt, public ?string $requestedBy, public ?string $interventionId, public ?string $errorCode, public bool $canRetry)
  {
  }

  // #endregion
  // #region Methods
  /**
   * Method fromView
   *
   * Maps an automation attempt view to its API output.
   *
   * @access public
   *
   * @param AutomationAttemptView $view the view
   *
   * @return self the created instance
   */
  public static function fromView(AutomationAttemptView $view): self
  {
    return new self($view->id, $view->runId, $view->organizationId, $view->ruleKey, $view->subjectId, $view->attemptNumber, $view->status, $view->createdAt, $view->finishedAt, $view->requestedBy, $view->interventionId, $view->errorCode, $view->canRetry);
  }
  // #endregion
}
