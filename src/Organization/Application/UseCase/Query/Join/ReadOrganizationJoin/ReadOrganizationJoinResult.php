<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Join\ReadOrganizationJoin;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase ReadOrganizationJoinResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ReadOrganizationJoinResult implements ResultMessage
{
  /**
   * @since 1.0.0
   *
   * @param array<string,mixed> $data the use-case projection
   */
  public function __construct(public array $data)
  {
  }
}
