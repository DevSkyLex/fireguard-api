<?php

declare(strict_types=1);

namespace Onboarding\Application\UseCase\Command\Setup\PrepareOrganizationSetup;

use Onboarding\Application\Port\Inbound\{OrganizationOnboardingServicePort, OrganizationSetupPort};
use Shared\Application\Message\CommandHandler;

/**
 * Durable organization setup recovery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PrepareOrganizationSetupHandler implements CommandHandler
{
  /**
   * @since 1.0.0
   */
  public function __construct(private OrganizationSetupPort $setup, private OrganizationOnboardingServicePort $flow)
  {
  }

  /**
   * @since 1.0.0
   */
  public function __invoke(PrepareOrganizationSetupCommand $command): PrepareOrganizationSetupResult
  {
    $this->flow->getFlow($command->userId);
    $this->setup->prepare($command->userId, $command->sessionId, $command->stepKey, $command->items);

    return new PrepareOrganizationSetupResult($this->flow->getFlow($command->userId));
  }
}
