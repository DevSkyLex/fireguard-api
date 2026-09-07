<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Presentation\Api\Processor\Onboarding;

use ApiPlatform\Metadata\Post;
use Onboarding\Presentation\Api\Dto\Input\Onboarding\PrepareOrganizationSetupInput;
use Onboarding\Presentation\Api\Processor\Onboarding\PrepareOrganizationSetupProcessor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Durable organization setup recovery.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PrepareOrganizationSetupProcessorTest extends TestCase
{
  #[Test]
  public function anonymousPreparationNeverDispatchesACommand(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::never())->method('dispatch');
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);
    $this->expectException(AccessDeniedHttpException::class);
    new PrepareOrganizationSetupProcessor($bus, $security)->process(new PrepareOrganizationSetupInput(), new Post());
  }
}
