<?php

declare(strict_types=1);

namespace Automation\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Automation\Application\Contract\Run\AutomationAttemptView;

/** Output AutomationAttemptOutput. Sanitized attempt history and server-owned retry capability. */
final readonly class AutomationAttemptOutput
{
  public function __construct(#[ApiProperty(identifier: true)] public string $id, public string $runId, public string $organizationId, public string $ruleKey, public string $subjectId, public int $attemptNumber, public string $status, public string $createdAt, public ?string $finishedAt, public ?string $requestedBy, public ?string $interventionId, public ?string $errorCode, public bool $canRetry)
  {
  }

  public static function fromView(AutomationAttemptView $view): self
  {
    return new self($view->id, $view->runId, $view->organizationId, $view->ruleKey, $view->subjectId, $view->attemptNumber, $view->status, $view->createdAt, $view->finishedAt, $view->requestedBy, $view->interventionId, $view->errorCode, $view->canRetry);
  }
}
