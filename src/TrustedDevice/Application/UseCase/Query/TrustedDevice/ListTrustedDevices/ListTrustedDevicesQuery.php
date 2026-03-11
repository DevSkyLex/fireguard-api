<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Query\TrustedDevice\ListTrustedDevices;

use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Message\QueryMessage;

/**
 * Query ListTrustedDevicesQuery.
 */
final readonly class ListTrustedDevicesQuery implements QueryMessage
{
  public function __construct(
    public string $userId,
    public Pagination $pagination = new Pagination(),
  ) {
  }
}
