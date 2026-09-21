<?php

declare(strict_types=1);

namespace Automation\Application\Contract\Run;

/** View AutomationAttemptView. Safe organization-scoped execution history. */
final readonly class AutomationAttemptView
{
  public function __construct(public string $id, public string $runId, public string $organizationId, public string $ruleKey, public string $subjectId, public int $attemptNumber, public string $status, public string $createdAt, public ?string $finishedAt, public ?string $requestedBy, public ?string $interventionId, public ?string $errorCode, public bool $canRetry)
  {
  }
}
