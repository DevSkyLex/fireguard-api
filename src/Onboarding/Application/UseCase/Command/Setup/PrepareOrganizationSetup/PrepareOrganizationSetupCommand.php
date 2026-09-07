<?php

declare(strict_types=1);

namespace Onboarding\Application\UseCase\Command\Setup\PrepareOrganizationSetup;

use Shared\Application\Message\CommandMessage;

/**
 * Durable organization setup recovery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PrepareOrganizationSetupCommand implements CommandMessage
{
  /**
   * @since 1.0.0
   *
   * @param list<array{itemKey:string,payload:array<string,mixed>}> $items batch
   */
  public function __construct(public string $userId, public string $sessionId, public string $stepKey, public array $items)
  {
  }
}
