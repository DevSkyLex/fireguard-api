<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Application\Service;

use Assistant\Application\Port\Outbound\Organization\AssistantOrganizationSettingsPort;
use Assistant\Application\Service\AssistantAccessPolicy;
use Assistant\Domain\Exception\AssistantDisabledException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AssistantAccessPolicyTest.
 *
 * @category Service Tests
 */
#[CoversClass(className: AssistantAccessPolicy::class)]
final class AssistantAccessPolicyTest extends TestCase
{
  // #region Constants
  private const string USER_ID = '11111111-1111-4111-8111-111111111111';

  private const string ORGANIZATION_ID = '22222222-2222-4222-8222-222222222222';
  // #endregion

  // #region Tests
  #[Test]
  public function testGrantsWhenThePermissionIsHeldAndTheOrganizationEnabledTheAssistant(): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('assertGrantedPermissions')
      ->with(self::USER_ID, self::ORGANIZATION_ID, ['organization.assistant.use']);

    $settings = $this->createStub(AssistantOrganizationSettingsPort::class);
    $settings->method('isEnabledFor')->willReturn(true);

    new AssistantAccessPolicy($authorization, $settings)
      ->assertCanUseAssistant(self::USER_ID, self::ORGANIZATION_ID);
  }

  #[Test]
  public function testRefusesWhenTheOrganizationTurnedTheAssistantOff(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);

    $settings = $this->createStub(AssistantOrganizationSettingsPort::class);
    $settings->method('isEnabledFor')->willReturn(false);

    $policy = new AssistantAccessPolicy($authorization, $settings);

    $this->expectException(AssistantDisabledException::class);
    $this->expectExceptionMessage('The assistant is disabled for this organization.');

    $policy->assertCanUseAssistant(self::USER_ID, self::ORGANIZATION_ID);
  }

  #[Test]
  public function testAssertsThePermissionBeforeReadingTheToggle(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')
      ->willThrowException(OrganizationAccessDeniedException::missingPermission('organization.assistant.use'));

    $settings = $this->createMock(AssistantOrganizationSettingsPort::class);
    $settings->expects(self::never())->method('isEnabledFor');

    $policy = new AssistantAccessPolicy($authorization, $settings);

    $this->expectException(OrganizationAccessDeniedException::class);

    $policy->assertCanUseAssistant(self::USER_ID, self::ORGANIZATION_ID);
  }
  // #endregion
}
