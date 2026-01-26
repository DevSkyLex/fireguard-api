<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Domain\ValueObject;

use Otp\Domain\ValueObject\OtpPurpose;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OtpPurposeTest.
 *
 * @category ValueObject Tests
 */
#[CoversClass(className: OtpPurpose::class)]
final class OtpPurposeTest extends TestCase
{
  // #region Tests
  #[Test]
  #[DataProvider('purposeProvider')]
  public function testDefaultsAndLabels(
    OtpPurpose $purpose,
    int $ttlSeconds,
    int $maxAttempts,
    string $label,
  ): void {
    self::assertSame($ttlSeconds, $purpose->getDefaultTtlSeconds());
    self::assertSame($maxAttempts, $purpose->getDefaultMaxAttempts());
    self::assertSame($label, $purpose->getLabel());
  }
  // #endregion

  /**
   * @return array<string, array{0: OtpPurpose, 1: int, 2: int, 3: string}>
   */
  public static function purposeProvider(): array
  {
    return [
      'login' => [OtpPurpose::LOGIN, 300, 5, 'Login 2FA'],
      'password_reset' => [OtpPurpose::PASSWORD_RESET, 900, 5, 'Password Reset'],
      'email_verification' => [OtpPurpose::EMAIL_VERIFICATION, 3600, 10, 'Email Verification'],
      'phone_verification' => [OtpPurpose::PHONE_VERIFICATION, 300, 5, 'Phone Verification'],
      'sensitive' => [OtpPurpose::SENSITIVE_OPERATION, 180, 3, 'Sensitive Operation'],
      'transaction' => [OtpPurpose::TRANSACTION_APPROVAL, 120, 3, 'Transaction Approval'],
    ];
  }
}
