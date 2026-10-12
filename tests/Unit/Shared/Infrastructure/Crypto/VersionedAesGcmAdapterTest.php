<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Crypto;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Crypto\CiphertextRejectedException;
use Shared\Infrastructure\Crypto\VersionedAesGcmAdapter;

use function base64_decode;
use function base64_encode;
use function chr;
use function json_decode;
use function json_encode;
use function ord;
use function str_repeat;

use const JSON_THROW_ON_ERROR;

/** Covers encryption-key rotation and denial of ciphertext/AAD tampering. */
final class VersionedAesGcmAdapterTest extends TestCase
{
  public function testEncryptsWithFreshNonceAndRetainsOldVersionDuringRotation(): void
  {
    $keys = ['old' => base64_encode(str_repeat('o', 32)), 'new' => base64_encode(str_repeat('n', 32))];
    $old = new VersionedAesGcmAdapter($keys, 'old');
    $rotated = new VersionedAesGcmAdapter($keys, 'new');
    $first = $old->encrypt('transient recovery code', 'receipt:tenant:operation');
    self::assertStringNotContainsString('transient recovery code', $first);
    self::assertNotSame($first, $old->encrypt('transient recovery code', 'receipt:tenant:operation'));
    self::assertSame('transient recovery code', $rotated->decrypt($first, 'receipt:tenant:operation'));
    $envelope = json_decode($rotated->encrypt('secret', 'binding'), true, 16, JSON_THROW_ON_ERROR);
    self::assertIsArray($envelope);
    self::assertSame('new', $envelope['k']);
  }

  #[DataProvider('tampering')]
  public function testRejectsChangedAuthenticatedEnvelope(string $field): void
  {
    $adapter = $this->adapter();
    $envelope = json_decode($adapter->encrypt('secret', 'binding'), true, 16, JSON_THROW_ON_ERROR);
    self::assertIsArray($envelope);
    if ('v' === $field) {
      $envelope[$field] = 2;
    } elseif ('k' === $field) {
      $envelope[$field] = 'missing';
    } else {
      self::assertIsString($envelope[$field]);
      $decoded = base64_decode($envelope[$field], true);
      self::assertIsString($decoded);
      $decoded[0] = chr(ord($decoded[0]) ^ 1);
      $envelope[$field] = base64_encode($decoded);
    }
    $this->expectException(CiphertextRejectedException::class);
    $adapter->decrypt(json_encode($envelope, JSON_THROW_ON_ERROR), 'binding');
  }

  /**
   * @return iterable<string, array{string}>
   */
  public static function tampering(): iterable
  {
    foreach (['v', 'k', 'n', 't', 'c'] as $field) {
      yield $field => [$field];
    }
  }

  public function testRejectsChangedScopeBinding(): void
  {
    $adapter = $this->adapter();
    $ciphertext = $adapter->encrypt('secret', 'scope-one');
    $this->expectException(CiphertextRejectedException::class);
    $adapter->decrypt($ciphertext, 'scope-two');
  }

  public function testKeyVersionIsAuthenticatedEvenWhenTwoConfiguredVersionsShareBytes(): void
  {
    $key = base64_encode(str_repeat('k', 32));
    $adapter = new VersionedAesGcmAdapter(['first' => $key, 'second' => $key], 'first');
    $envelope = json_decode($adapter->encrypt('secret', 'binding'), true, 16, JSON_THROW_ON_ERROR);
    self::assertIsArray($envelope);
    $envelope['k'] = 'second';
    $this->expectException(CiphertextRejectedException::class);
    $adapter->decrypt(json_encode($envelope, JSON_THROW_ON_ERROR), 'binding');
  }

  public function testRejectsRetiredKey(): void
  {
    $ciphertext = $this->adapter()->encrypt('secret', 'binding');
    $this->expectException(CiphertextRejectedException::class);
    new VersionedAesGcmAdapter(['new' => base64_encode(str_repeat('n', 32))], 'new')->decrypt($ciphertext, 'binding');
  }

  public function testRejectsMalformedCiphertextWithoutSecretDiagnostics(): void
  {
    $this->expectException(CiphertextRejectedException::class);
    $this->adapter()->decrypt('raw-secret-malformed', 'binding');
  }

  public function testRejectsEmptyAuthenticatedData(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->adapter()->encrypt('secret', '');
  }

  public function testRejectsNonAes256Keys(): void
  {
    $this->expectException(InvalidArgumentException::class);
    new VersionedAesGcmAdapter(['test' => base64_encode('short')], 'test');
  }

  public function testRejectsOversizedPlaintextBeforeCreatingAnUnreplayableEnvelope(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->adapter()->encrypt(str_repeat('x', 8388609), 'binding');
  }

  private function adapter(): VersionedAesGcmAdapter
  {
    return new VersionedAesGcmAdapter(['test' => base64_encode(str_repeat('k', 32))], 'test');
  }
}
