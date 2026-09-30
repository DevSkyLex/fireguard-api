<?php

declare(strict_types=1);

namespace Automation\Application\UseCase\Command\RetryAutomationAttempt;

use Shared\Application\Message\CommandMessage;

/**
 * Class RetryAutomationAttemptCommand
 *
 * Requests a retry of an automation run attempt by an organization actor.
 *
 * @category Command
 */
final readonly class RetryAutomationAttemptCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries the actor, organization, run, and newly requested attempt identifiers for a retry.
   *
   * @access public
   *
   * @param string $actorUserId the user requesting the retry
   * @param string $organizationId the owning organization
   * @param string $runId the automation run identifier
   * @param string $attemptId the new attempt identifier
   *
   * @return void
   */
  public function __construct(public string $actorUserId, public string $organizationId, public string $runId, public string $attemptId)
  {
  }
  // #endregion
}
