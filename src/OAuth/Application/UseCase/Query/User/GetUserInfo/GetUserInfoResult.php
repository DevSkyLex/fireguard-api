<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\User\GetUserInfo;

use Shared\Application\Message\ResultMessage;

/**
 * UserInfo claim projection or authentication failure.
 *
 * @category Result
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetUserInfoResult implements ResultMessage
{
  /**
   * @param array<string, mixed>|null $claims the scope-filtered claims
   */
  public function __construct(public ?array $claims, public ?string $error = null)
  {
  }
}
