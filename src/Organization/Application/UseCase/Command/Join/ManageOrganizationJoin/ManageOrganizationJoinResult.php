<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Join\ManageOrganizationJoin;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase ManageOrganizationJoinResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ManageOrganizationJoinResult implements ResultMessage
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
