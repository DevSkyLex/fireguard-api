<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** The last message received by another authenticated browser. */
final class AcknowledgeDeliveryInput
{
  // #region Properties
  /**
   * Property messageId
   */
  #[Assert\NotBlank]
  public string $messageId = '';
  // #endregion
}
