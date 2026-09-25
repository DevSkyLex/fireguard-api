<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Infrastructure\Adapter\Crypto;

use Otp\Infrastructure\Adapter\Crypto\OpensslTotpSecretCipherAdapter;
use Otp\Infrastructure\Exception\TotpSecretCipherException;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

use function base64_encode;
use function str_repeat;
use function substr;

/**
 * Test OpensslTotpSecretCipherAdapterTest.
 *
 * @category Unit Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OpensslTotpSecretCipherAdapterTest extends TestCase
{
  #[Test]
  public function authenticatesTheSecretAndUsesADifferentNonceForEachWrite(): void
  {
    $cipher = $this->cipher();
    $first = $cipher->encrypt('JBSWY3DPEHPK3PXP', 'user-1:active');
    $second = $cipher->encrypt('JBSWY3DPEHPK3PXP', 'user-1:active');

    self::assertNotSame($first, $second);
    self::assertStringStartsWith('totp:v1:current:', $first);
    self::assertStringNotContainsString('JBSWY3DPEHPK3PXP', $first);
    self::assertSame('JBSWY3DPEHPK3PXP', $cipher->decrypt($first, 'user-1:active'));
    self::assertFalse($cipher->needsRotation($first));
  }

  #[Test]
  #[DataProvider('wrongContexts')]
  public function rejectsMovingAnEnvelopeToAnotherAccountOrSlot(string $context): void
  {
    $cipher = $this->cipher();
    $envelope = $cipher->encrypt('JBSWY3DPEHPK3PXP', 'user-1:active');
    $this->expectException(TotpSecretCipherException::class);
    $cipher->decrypt($envelope, $context);
  }

  /**
   * Method wrongContexts.
   *
   * @return iterable<string, array{string}>
   */
  public static function wrongContexts(): iterable
  {
    yield 'other account' => ['user-2:active'];
    yield 'pending slot' => ['user-1:pending'];
  }

  #[Test]
  public function rejectsTampering(): void
  {
    $cipher = $this->cipher();
    $envelope = $cipher->encrypt('JBSWY3DPEHPK3PXP', 'user-1:active');
    $envelope = substr($envelope, 0, -8) . 'AAAAAAAA';

    $this->expectException(TotpSecretCipherException::class);
    $cipher->decrypt($envelope, 'user-1:active');
  }

  #[Test]
  public function retainedKeysPermitRotationWithoutChangingAuthenticatorSecrets(): void
  {
    $oldKey = base64_encode(str_repeat('a', 32));
    $newKey = base64_encode(str_repeat('b', 32));
    $old = new OpensslTotpSecretCipherAdapter(['old' => $oldKey], 'old');
    $envelope = $old->encrypt('JBSWY3DPEHPK3PXP', 'user-1:active');
    $new = new OpensslTotpSecretCipherAdapter(['old' => $oldKey, 'new' => $newKey], 'new');

    self::assertTrue($new->needsRotation($envelope));
    $rotated = $new->encrypt($new->decrypt($envelope, 'user-1:active'), 'user-1:active');
    self::assertSame('JBSWY3DPEHPK3PXP', new OpensslTotpSecretCipherAdapter(['new' => $newKey], 'new')->decrypt($rotated, 'user-1:active'));
    self::assertFalse($new->needsRotation($rotated));

    $this->expectException(TotpSecretCipherException::class);
    new OpensslTotpSecretCipherAdapter(['new' => $newKey], 'new')->decrypt($envelope, 'user-1:active');
  }

  #[Test]
  public function rejectsWrongKeyLengthsInsteadOfAllowingOpenSslPadding(): void
  {
    $this->expectException(TotpSecretCipherException::class);
    new OpensslTotpSecretCipherAdapter(['bad' => base64_encode('too-short')], 'bad');
  }

  #[Test]
  public function anUnconfiguredWriteKeyCannotEncrypt(): void
  {
    $cipher = new OpensslTotpSecretCipherAdapter([], '');
    self::assertFalse($cipher->canEncrypt());
    $this->expectException(TotpSecretCipherException::class);
    $cipher->encrypt('JBSWY3DPEHPK3PXP', 'user-1:active');
  }

  private function cipher(): OpensslTotpSecretCipherAdapter
  {
    return new OpensslTotpSecretCipherAdapter(['current' => base64_encode(str_repeat('x', 32))], 'current');
  }
}
