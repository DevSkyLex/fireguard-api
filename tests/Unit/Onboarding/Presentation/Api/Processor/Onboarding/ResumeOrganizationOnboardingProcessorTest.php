<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Presentation\Api\Processor\Onboarding;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use Onboarding\Application\Port\Inbound\OrganizationOnboardingServicePort;
use Onboarding\Application\Service\OrganizationOnboardingSessionState;
use Onboarding\Presentation\Api\Processor\Onboarding\ResumeOrganizationOnboardingProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Test ResumeOrganizationOnboardingProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ResumeOrganizationOnboardingProcessor::class)]
final class ResumeOrganizationOnboardingProcessorTest extends TestCase
{
  // #region Constants
  private const string USER_ID = 'user-id';
  // #endregion

  // #region Methods
  #[Test]
  public function testProcessResumesTheFlowForTheCurrentUser(): void
  {
    $flowService = $this->createMock(OrganizationOnboardingServicePort::class);
    $flowService->expects(self::once())
      ->method('resume')
      ->with(self::USER_ID)
      ->willReturn($this->state());

    $processor = new ResumeOrganizationOnboardingProcessor($flowService, $this->authenticatedSecurity());

    $output = $processor->process(null, new Post());

    self::assertSame('organization', $output->flow);
    self::assertSame('in_progress', $output->state);
    self::assertSame('create_first_facility', $output->nextStep);
    self::assertFalse($output->dismissed);
    self::assertNull($output->dismissedAt);
    self::assertSame(['create_organization'], $output->completedSteps);
  }

  #[Test]
  public function testProcessRequiresAuthentication(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new ResumeOrganizationOnboardingProcessor(
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
      state: 'in_progress',
      nextStep: 'create_first_facility',
      blockedReason: null,
      targetOrganizationId: 'organization-1',
      targetOrganizationName: 'Acme Fire Safety',
      completedSteps: ['create_organization'],
      skippedSteps: ['invite_members'],
      stepHistory: [[
        'stepKey' => 'create_organization',
        'occurredAt' => '2026-06-01T10:00:00+00:00',
        'skipped' => false,
      ]],
      updatedAt: '2026-07-01T10:00:00+00:00',
      canRollback: true,
      lastRollbackableStep: 'create_organization',
    );
  }
  // #endregion
}
