<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Application\UseCase\Command\Setup\PrepareOrganizationSetup;

use Onboarding\Application\Contract\Setup\OrganizationSetupConflict;
use Onboarding\Application\Port\Inbound\{OrganizationOnboardingServicePort, OrganizationSetupPort};
use Onboarding\Application\UseCase\Command\Setup\PrepareOrganizationSetup\{PrepareOrganizationSetupCommand, PrepareOrganizationSetupHandler};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Durable organization setup recovery.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PrepareOrganizationSetupHandlerTest extends TestCase
{
  #[Test]
  public function refusedPreparationDoesNotReturnAFalseSuccess(): void
  {
    $flow = $this->createMock(OrganizationOnboardingServicePort::class);
    $flow->expects(self::once())->method('getFlow')->with('user')->willReturn(new \Onboarding\Application\Service\OrganizationOnboardingSessionState('organization', 'in_progress', 'create_organization', null, null, null, [], [], [], null, false, null));
    $setup = $this->createMock(OrganizationSetupPort::class);
    $setup->expects(self::once())->method('prepare')->with('user', 'session', 'create_organization', [['itemKey' => 'one', 'payload' => ['name' => 'ACME']]])->willThrowException(OrganizationSetupConflict::because('Session expired.'));
    $this->expectException(OrganizationSetupConflict::class);
    new PrepareOrganizationSetupHandler($setup, $flow)->__invoke(new PrepareOrganizationSetupCommand('user', 'session', 'create_organization', [['itemKey' => 'one', 'payload' => ['name' => 'ACME']]]));
  }
}
