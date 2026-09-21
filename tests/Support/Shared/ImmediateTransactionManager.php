<?php

declare(strict_types=1);

namespace App\Tests\Support\Shared;

use Shared\Application\Port\Outbound\TransactionManagerPort;

final readonly class ImmediateTransactionManager implements TransactionManagerPort
{
  public function transactional(callable $operation): mixed
  {
    return $operation();
  }
}
