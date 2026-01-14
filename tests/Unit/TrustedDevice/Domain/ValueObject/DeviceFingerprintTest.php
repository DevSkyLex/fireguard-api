<?php

declare(strict_types=1);

namespace Tests\Unit\TrustedDevice\Domain\ValueObject;

use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use TrustedDevice\Domain\ValueObject\DeviceFingerprint;

/**
 * Test DeviceFingerprintTest.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: DeviceFingerprint::class)]
final class DeviceFingerprintTest extends TestCase
{
  // #region Methods
  /**
   * Method testCreateGeneratesStableHashAndMatches.
   *
   * Tests that create generates a stable hash
   * and matches identical fingerprints.
   */
  #[Test]
  public function testCreateGeneratesStableHashAndMatches(): void
  {
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0';
    $ipAddress = '127.0.0.1';
    $acceptLanguage = 'en-US';

    $fingerprint = DeviceFingerprint::create(
      userAgent: $userAgent,
      ipAddress: $ipAddress,
      acceptLanguage: $acceptLanguage,
    );

    $other = DeviceFingerprint::create(
      userAgent: $userAgent,
      ipAddress: $ipAddress,
      acceptLanguage: $acceptLanguage,
    );

    self::assertSame($userAgent, $fingerprint->userAgent);
    self::assertSame($ipAddress, $fingerprint->ipAddress);
    self::assertTrue($fingerprint->matches($other));
  }

  /**
   * Method testFromHashKeepsProvidedValues.
   *
   * Tests that fromHash uses the
   * provided hash and metadata.
   */
  #[Test]
  public function testFromHashKeepsProvidedValues(): void
  {
    $fingerprint = DeviceFingerprint::fromHash(
      hash: 'hash-value',
      userAgent: 'Test Agent',
      ipAddress: null,
    );

    self::assertSame('hash-value', $fingerprint->value);
    self::assertSame('Test Agent', $fingerprint->userAgent);
    self::assertNull($fingerprint->ipAddress);
  }

  /**
   * Method testGetDeviceNameDetectsBrowserAndOs.
   *
   * Tests that getDeviceName returns
   * expected name for various user agents.
   */
  #[Test]
  #[DataProvider('deviceNameProvider')]
  public function testGetDeviceNameDetectsBrowserAndOs(string $userAgent, string $expected): void
  {
    $fingerprint = new DeviceFingerprint(
      value: 'hash',
      userAgent: $userAgent,
      ipAddress: null,
    );

    self::assertSame($expected, $fingerprint->getDeviceName());
  }

  /**
   * Method deviceNameProvider.
   *
   * @return array<string, array{string, string}>
   */
  public static function deviceNameProvider(): array
  {
    return [
      'chrome windows' => [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
        'Chrome on Windows',
      ],
      'firefox linux' => [
        'Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/119.0',
        'Firefox on Linux',
      ],
      'safari macos' => [
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Version/17.0 Safari/605.1.15',
        'Safari on macOS',
      ],
      'edge windows' => [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Edge/18.0',
        'Edge on Windows',
      ],
      'chrome android' => [
        'Mozilla/5.0 (Android 12; Mobile) AppleWebKit/537.36 Chrome/120.0.0.0 Mobile Safari/537.36',
        'Chrome on Android',
      ],
      'safari ios' => [
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1',
        'Safari on iOS',
      ],
    ];
  }
  // #endregion
}
