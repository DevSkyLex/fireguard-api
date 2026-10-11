<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\Avatar\GetUserAvatar;

use Shared\Application\Message\ResultMessage;

/**
 * Public avatar bytes or a stable missing-resource reason.
 *
 * @category Result
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetUserAvatarResult implements ResultMessage
{
  public function __construct(public ?string $contents, public ?string $missingReason = null)
  {
  }
}
