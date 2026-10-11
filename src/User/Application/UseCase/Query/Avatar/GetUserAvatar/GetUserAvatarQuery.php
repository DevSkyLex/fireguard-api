<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\Avatar\GetUserAvatar;

use Shared\Application\Message\QueryMessage;

/**
 * Reads a public avatar variant.
 *
 * @category Query
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetUserAvatarQuery implements QueryMessage
{
  public function __construct(public string $userId, public int $requestedSize = 256)
  {
  }
}
