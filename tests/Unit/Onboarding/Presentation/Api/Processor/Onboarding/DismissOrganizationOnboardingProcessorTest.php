<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Presentation\Api\Processor\Onboarding;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use Onboarding\Application\Port\Inbound\OrganizationOnboardingServicePort;
use Onboarding\Application\Service\OrganizationOnboardingSessionState;
use Onboarding\Presentation\Api\Processor\Onboarding\DismissOrganizationOnboardingProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Test DismissOrganizationOnboardingProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DismissOrganizationOnboardingProcessor::class)]
final class DismissOrganizationOnboardingProcessorTest extends TestCase
{
  // #region Constants
  private const string USER_ID = 'user-id';
  // #endregion

  // #region Methods
  #[Test]
  public function testProcessDismissesTheFlowForTheCurrentUser(): void
  {
    $flowService = $this->createMock(OrganizationOnboardingServicePort::class);
    $flowService->expects(self::once())
      ->method('dismiss')
      ->with(self::USER_ID)
      ->willReturn($this->state());

    $processor = new DismissOrganizationOnboardingProcessor($flowService, $this->authenticatedSecurity());

    $output = $processor->process(null, new Post());

    self::assertSame('organization', $output->flow);
    self::assertSame('dismissed', $output->state);
    self::assertTrue($output->dismissed);
    self::assertSame('2026-07-01T10:00:00+00:00', $output->dismissedAt);
  }

  #[Test]
  public function testProcessRequiresAuthentication(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new DismissOrganizationOnboardingProcessor(
      $this->createStub(OrganizationOnboardingServicePort::class),
      $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Post());
  }
  // #endregion

  // #region Helpers
  private function authenticatedSecurity(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    return $security;
  }

  private function state(): OrganizationOnboardingSessionState
  {
    return new OrganizationOnboardingSessionState(
      flow: 'organization',
      state: 'dismissed',
      nextStep: null,
      blockedReason: null,
      targetOrganizationId: 'organization-1',
      targetOrganizationName: 'Acme Fire Safety',
      completedSteps: ['create_organization'],
      skippedSteps: [],
      stepHistory: [[
        'stepKey' => 'create_organization',
        'occurredAt' => '2026-06-01T10:00:00+00:00',
        'skipped' => false,
      ]],
      updatedAt: '2026-07-01T10:00:00+00:00',
      canRollback: false,
      lastRollbackableStep: null,
      dismissed: true,
      dismissedAt: '2026-07-01T10:00:00+00:00',
    );
  }
  // #endregion
}
