<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\Avatar\UploadUserAvatar;

use Shared\Application\Message\ResultMessage;

/**
 * Uploaded account avatar identity.
 *
 * @category Result
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UploadUserAvatarResult implements ResultMessage
{
  public function __construct(public string $userId)
  {
  }
}
