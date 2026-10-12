<?php

declare(strict_types=1);

namespace Authorization\Application\Contract\Grant;

/**
 * Current owner grants and registered vocabulary facts used for credential replay invalidation.
 *
 * @category Contract
 */
final readonly class UserGrantSnapshot
{
  /**
   * @param list<string> $roles current scoped role names
   * @param list<string> $permissions current registered scoped permissions
   * @param array<string,mixed> $catalogFacts active technical vocabulary and role policy
   */
  public function __construct(public array $roles, public array $permissions, public array $catalogFacts)
  {
  }
}
