<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\User\GetUserInfo;

use Shared\Application\Message\QueryMessage;

/**
 * Reads claims within the token's verified scope.
 *
 * @category Query
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetUserInfoQuery implements QueryMessage
{
  /**
   * @param list<string> $scopes the verified token scopes
   */
  public function __construct(public string $userId, public array $scopes)
  {
  }
}
