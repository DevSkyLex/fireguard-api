<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\Dto\Output\Consent;

use OAuth\Presentation\Api\Dto\Output\Consent\CheckConsentOutput;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test CheckConsentOutputTest.
 *
 * @category DTO Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CheckConsentOutput::class)]
final class CheckConsentOutputTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testConstructorAssignsProperties(): void
  {
    $output = new CheckConsentOutput(
      hasConsent: true,
      grantedScopes: ['openid'],
      missingScopes: ['email'],
      requiresConsentScreen: false,
    );

    self::assertTrue($output->hasConsent);
    self::assertSame(['openid'], $output->grantedScopes);
    self::assertSame(['email'], $output->missingScopes);
    self::assertFalse($output->requiresConsentScreen);
  }
  // #endregion
}
