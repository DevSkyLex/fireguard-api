<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\Provider\Discovery;

use ApiPlatform\Metadata\Operation;
use OAuth\Presentation\Api\Provider\Discovery\JwksProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function getcwd;
use function tempnam;
use function unlink;

use const DIRECTORY_SEPARATOR;

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

    $output = $provider->provide(operation: $this->createMock(Operation::class));

    self::assertSame([], $output->keys);

    unlink($tempFile);
  }

  #[Test]
  public function testProvideReturnsKeysForValidPublicKey(): void
  {
    $path = getcwd() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'jwt' . DIRECTORY_SEPARATOR . 'public.key';
    $provider = new JwksProvider(publicKeyPath: $path);

    $output = $provider->provide(operation: $this->createMock(Operation::class));

    self::assertNotEmpty($output->keys);
    self::assertSame('RSA', $output->keys[0]['kty'] ?? null);
    self::assertSame('sig', $output->keys[0]['use'] ?? null);
  }
  // #endregion
}
