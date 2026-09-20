<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Workforce;

/**
 * Contract OrganizationWorkforceMemberProfile.
 *
 * Organization-scoped identity for authorized member pickers, without account roles.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationWorkforceMemberProfile
{
  /**
   * @since 1.0.0
   *
   * @param string $id organization membership identifier
   * @param string $name member display name, or membership identifier if unavailable
   * @param ?string $avatarUrl profile image, when configured
   * @param list<string> $roleNames roles assigned within the requested organization only
   */
  public function __construct(public string $id, public string $name, public ?string $avatarUrl, public array $roleNames)
  {
  }
}
