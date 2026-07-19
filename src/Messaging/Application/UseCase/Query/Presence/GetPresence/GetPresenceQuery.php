<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Presence\GetPresence;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetPresenceQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetPresenceQuery implements QueryMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $organizationId the organization id value
   * @param list<string> $memberIds the organization member ids to check presence for (a bounded, caller-supplied multi-get — never "list all online members")
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public array $memberIds,
  ) {
  }
}
