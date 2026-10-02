<?php

declare(strict_types=1);

namespace Tests\Performance;

use Psr\Log\AbstractLogger;
use Stringable;

/** Counts executed statements without retaining SQL, parameters or credentials. */
final class SqlQueryCounter extends AbstractLogger
{
  /**
   * DAMA retains the connection's logger when the Symfony test kernel reboots.
   */
  public int $queries {
    get => self::$executedQueries;
    set {
      self::$executedQueries = $value;
    }
  }

  private static int $executedQueries = 0;

  /**
   * @param array<string, mixed> $context
   */
  public function log(mixed $level, string|Stringable $message, array $context = []): void
  {
    if (isset($context['sql'])) {
      ++self::$executedQueries;
    }
  }
}
