<?php

declare(strict_types=1);

namespace Auth\Application\Contract\User;

/**
 * Verified identity context used for tenant and scope decisions.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface AuthenticatedPrincipal extends AuthenticatedUser
{
  public function getUserIdentifier(): string;

  public function getTenantId(): ?string;

  /**
   * @return list<string> the verified scopes
   */
  public function getScopes(): array;

  public function hasScope(string $scope): bool;
}
