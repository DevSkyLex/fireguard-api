<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Join\ReadOrganizationJoin;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ReadOrganizationJoinQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ReadOrganizationJoinQuery implements QueryMessage
{
  /**
   * @since 1.0.0
   *
   * @param string $operation explicit use case operation
   * @param string $userId authenticated actor
   * @param ?string $organizationId organization scope
   */
  public function __construct(public string $operation, public string $userId, public ?string $organizationId = null)
  {
  }
}
