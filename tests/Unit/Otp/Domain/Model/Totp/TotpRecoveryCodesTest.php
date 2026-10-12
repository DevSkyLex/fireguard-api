<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Domain\Model\Totp;

use DateTimeImmutable;
use Otp\Domain\Model\Totp\TotpEnrollment;
use Otp\Domain\ValueObject\{RecoveryCode, TotpSecret};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Class TotpRecoveryCodesTest
 *
 * Proves normalization, one-time use, lot replacement and erasure at factor removal.
 *
 * @category Test
 */
final class TotpRecoveryCodesTest extends TestCase
{
  public function testCodesNormalizeConsumeOnceReplaceAndClearOnDisable(): void
  {
    $first = 'abcd-abcd-abcd-abcd-abcd-abcd-abcd-abcd';
    $second = '1234-1234-1234-1234-1234-1234-1234-1234';
    $enrollment = TotpEnrollment::startEnrollment('user', new TotpSecret('JBSWY3DPEHPK3PXP'), 5);
    $enrollment->confirmPending(true);
    $date = new DateTimeImmutable();
    $enrollment->replaceRecoveryCodes([self::digest($first), self::digest($second)], $date);
    self::assertSame(2, $enrollment->recoveryCodesRemaining());
    self::assertSame($date, $enrollment->recoveryCodesGeneratedAt());
    self::assertTrue($enrollment->consumeRecoveryCode('ABCDABCDABCDABCDABCDABCDABCDABCD'));
    self::assertFalse($enrollment->consumeRecoveryCode($first));
    self::assertFalse($enrollment->consumeRecoveryCode('123456'));
    self::assertSame(1, $enrollment->recoveryCodesRemaining());
    $enrollment->replaceRecoveryCodes([self::digest($first)], $date);
    self::assertFalse($enrollment->consumeRecoveryCode($second));
    self::assertTrue($enrollment->disable(true));
    self::assertFalse($enrollment->consumeRecoveryCode($first));
    self::assertSame([], $enrollment->recoveryCodeHashes());
    self::assertNull($enrollment->recoveryCodesGeneratedAt());
  }

  public function testPlaintextCannotEnterRecoveryState(): void
  {
    $enrollment = TotpEnrollment::startEnrollment('user', new TotpSecret('JBSWY3DPEHPK3PXP'), 5);
    $enrollment->confirmPending(true);
    $this->expectException(InvalidValueException::class);
    $enrollment->replaceRecoveryCodes(['abcd-abcd-abcd-abcd-abcd-abcd-abcd-abcd'], new DateTimeImmutable());
  }

  public function testDuplicateDigestsCannotAllowTwoUsesOfOneProof(): void
  {
    $enrollment = TotpEnrollment::startEnrollment('user', new TotpSecret('JBSWY3DPEHPK3PXP'), 5);
    $enrollment->confirmPending(true);
    $digest = self::digest('abcd-abcd-abcd-abcd-abcd-abcd-abcd-abcd');
    $this->expectException(InvalidValueException::class);
    $enrollment->replaceRecoveryCodes([$digest, $digest], new DateTimeImmutable());
  }

  private static function digest(string $code): string
  {
    $hash = RecoveryCode::hash($code);
    self::assertIsString($hash);

    return $hash;
  }
}
