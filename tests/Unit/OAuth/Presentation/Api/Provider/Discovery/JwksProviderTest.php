<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\Provider\Discovery;

use ApiPlatform\Metadata\Operation;
use OAuth\Presentation\Api\Provider\Discovery\JwksProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function defined;
use function file_exists;
use function file_put_contents;
use function getcwd;
use function is_array;
use function openssl_pkey_get_details;
use function openssl_pkey_new;
use function restore_error_handler;
use function set_error_handler;
use function tempnam;
use function unlink;

use const DIRECTORY_SEPARATOR;
use const OPENSSL_KEYTYPE_EC;

/**
 * Test JwksProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: JwksProvider::class)]
final class JwksProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProvideReturnsEmptyKeysWhenFileMissing(): void
  {
    $tempDir = getcwd() . DIRECTORY_SEPARATOR . 'var';
    $tempFile = tempnam($tempDir, 'jwks_');
    file_put_contents($tempFile, 'invalid-key');

    $provider = new JwksProvider(publicKeyPath: $tempFile);

    $output = $provider->provide(operation: $this->createStub(Operation::class));

    self::assertSame([], $output->keys);

    unlink($tempFile);
  }

  #[Test]
  public function testProvideReturnsEmptyKeysWhenFileNotFound(): void
  {
    $path = getcwd() . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'missing_jwks.key';
    if (file_exists($path)) {
      unlink($path);
    }

    $provider = new JwksProvider(publicKeyPath: $path);

    set_error_handler(static fn (): bool => true);

    try {
      $output = $provider->provide(operation: $this->createStub(Operation::class));
    } finally {
      restore_error_handler();
    }

    self::assertSame([], $output->keys);
  }

  #[Test]
  public function testProvideReturnsEmptyKeysWhenKeyIsNotRsa(): void
  {
    if (!defined('OPENSSL_KEYTYPE_EC')) {
      $this->markTestSkipped('OpenSSL EC keys are not supported.');
    }

    $key = openssl_pkey_new([
      'private_key_type' => OPENSSL_KEYTYPE_EC,
      'curve_name' => 'prime256v1',
    ]);

    if (false === $key) {
      $this->markTestSkipped('Unable to generate EC key.');
    }

    $publicKey = openssl_pkey_get_details($key);
    if (!is_array($publicKey) || !isset($publicKey['key'])) {
      $this->markTestSkipped('Unable to extract EC public key.');
    }

    $tempDir = getcwd() . DIRECTORY_SEPARATOR . 'var';
    $tempFile = tempnam($tempDir, 'jwks_ec_');
    file_put_contents($tempFile, $publicKey['key']);

    $provider = new JwksProvider(publicKeyPath: $tempFile);

    $output = $provider->provide(operation: $this->createStub(Operation::class));

    self::assertSame([], $output->keys);

    unlink($tempFile);
  }

  #[Test]
  public function testProvideReturnsEmptyKeysWhenRsaPartsAreNotStrings(): void
  {
    $path = getcwd() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'jwt' . DIRECTORY_SEPARATOR . 'public.key';
    $provider = new JwksProvider(
      publicKeyPath: $path,
      keyDetailsResolver: static fn ($keyData): array => [
        'rsa' => [
          'n' => 123,
          'e' => ['invalid'],
        ],
      ],
    );

    $output = $provider->provide(operation: $this->createStub(Operation::class));

    self::assertSame([], $output->keys);
  }

  #[Test]
  public function testProvideReturnsKeysForValidPublicKey(): void
  {
    $path = getcwd() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'jwt' . DIRECTORY_SEPARATOR . 'public.key';
    $provider = new JwksProvider(publicKeyPath: $path);

    $output = $provider->provide(operation: $this->createStub(Operation::class));

    self::assertNotEmpty($output->keys);
    self::assertSame('RSA', $output->keys[0]['kty'] ?? null);
    self::assertSame('sig', $output->keys[0]['use'] ?? null);
  }
  // #endregion
}
