<?php

declare(strict_types=1);

namespace Onboarding\Application\Contract\Setup;

/**
 * Durable organization setup recovery.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationSetupContext
{
  /**
   * @since 1.0.0
   */
  public function __construct(public string $userId, public string $sessionId, public string $itemKey)
  {
  }

  /**
   * @since 1.0.0 Parse optional HTTP fields as one inseparable receipt.
   */
  public static function fromOptional(string $userId, ?string $sessionId, ?string $itemKey): ?self
  {
    if (null === $sessionId && null === $itemKey) {
      return null;
    }
    if (null === $sessionId || null === $itemKey || '' === $sessionId || '' === $itemKey) {
      throw OrganizationSetupConflict::because('Both onboarding receipt fields are required.');
    }

    return new self($userId, $sessionId, $itemKey);
  }
}
