<?php

declare(strict_types=1);

namespace Automation\Presentation\Api\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** Input RetryAutomationAttemptInput. Optimistic precondition on the failed attempt. */
final class RetryAutomationAttemptInput
{
  // #region Properties
  /**
   * Property attemptId
   */
  #[Assert\NotBlank]
  #[Assert\Uuid]
  public string $attemptId = '';
  // #endregion
}
