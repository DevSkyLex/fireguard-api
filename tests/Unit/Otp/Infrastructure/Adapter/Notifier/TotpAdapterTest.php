<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Infrastructure\Adapter\Notifier;

use Otp\Domain\ValueObject\TotpSecret;
use Otp\Infrastructure\Adapter\Notifier\TotpAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

use function in_array;
use function str_pad;
use function time;

use const STR_PAD_LEFT;

/**
 * Test TotpAdapterTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TotpAdapter::class)]
final class TotpAdapterTest extends TestCase
{
  #[Test]
  public function testGenerateSecretReturnsValidSecret(): void
  {
    $adapter = new TotpAdapter();

    $secret = $adapter->generateSecret();

    self::assertInstanceOf(TotpSecret::class, $secret);
    self::assertMatchesRegularExpression('/^[A-Z2-7]+=*$/', $secret->secret);
  }

  #[Test]
  public function testGetProvisioningUriReturnsValidUri(): void
  {
    $adapter = new TotpAdapter();
    $secret = new TotpSecret('JBSWY3DPEHPK3PXP');

    $uri = $adapter->getProvisioningUri($secret, 'test@example.com');

    self::assertStringStartsWith('otpauth://totp/', $uri);
    self::assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $uri);
    self::assertStringContainsString('issuer=FireGuard%20Auth', $uri);
  }

  #[Test]
  public function testVerifyRejectsInvalidFormat(): void
  {
    $adapter = new TotpAdapter();
    $secret = new TotpSecret('JBSWY3DPEHPK3PXP');

    // Invalid format (not 6 digits)
    self::assertFalse($adapter->verify('123', $secret));
    self::assertFalse($adapter->verify('abcdef', $secret));
    self::assertFalse($adapter->verify('1234567', $secret));
  }

  #[Test]
  public function testVerifyAcceptsGeneratedCode(): void
  {
    $adapter = new TotpAdapter();
    $secret = new TotpSecret('JBSWY3DPEHPK3PXP');

    $timestamp = time();
    $secretBytes = $this->invokePrivate($adapter, 'base32Decode', [$secret->secret]);
    $code = $this->invokePrivate($adapter, 'generateCode', [$secretBytes, $timestamp]);
    self::assertIsString($code);

    self::assertTrue($adapter->verify($code, $secret));
  }

  #[Test]
  public function testVerifyRejectsAWellFormedButWrongCode(): void
  {
    $adapter = new TotpAdapter();
    $secret = new TotpSecret('JBSWY3DPEHPK3PXP');

    $timestamp = time();
    $secretBytes = $this->invokePrivate($adapter, 'base32Decode', [$secret->secret]);
    self::assertIsString($secretBytes);

    $accepted = [];
    for ($offset = -2; $offset <= 2; ++$offset) {
      $accepted[] = $this->invokePrivate($adapter, 'generateCode', [$secretBytes, $timestamp + ($offset * 30)]);
    }

    $wrongCode = null;
    for ($candidate = 0; $candidate < 10; ++$candidate) {
      $formatted = str_pad((string) $candidate, 6, '0', STR_PAD_LEFT);
      if (!in_array($formatted, $accepted, true)) {
        $wrongCode = $formatted;

        break;
      }
    }

    self::assertIsString($wrongCode);
    self::assertFalse($adapter->verify($wrongCode, $secret));
  }

  #[Test]
  public function testBase32DecodeSkipsCharactersOutsideTheAlphabet(): void
  {
    $adapter = new TotpAdapter();

    $clean = $this->invokePrivate($adapter, 'base32Decode', ['JBSWY3DPEHPK3PXP']);
    $noisy = $this->invokePrivate($adapter, 'base32Decode', ['JBSWY3DP1EHPK3PXP0===']);

    self::assertIsString($clean);
    self::assertIsString($noisy);
    self::assertSame($clean, $noisy);
  }

  /**
   * @param array<int, mixed> $args
   */
  private function invokePrivate(object $object, string $method, array $args): mixed
  {
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invokeArgs($object, $args);
  }
}
