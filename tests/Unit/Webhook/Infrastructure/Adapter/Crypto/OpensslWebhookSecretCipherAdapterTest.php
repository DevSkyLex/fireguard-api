<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Infrastructure\Adapter\Crypto;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Webhook\Infrastructure\Adapter\Crypto\OpensslWebhookSecretCipherAdapter;

use function base64_encode;
use function random_bytes;
use function substr;

/**
 * Test OpensslWebhookSecretCipherAdapterTest.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OpensslWebhookSecretCipherAdapter::class)]
final class OpensslWebhookSecretCipherAdapterTest extends TestCase
{
  #[Test]
  public function encryptThenDecryptRoundTripsToTheOriginalPlaintext(): void
  {
    $cipher = new OpensslWebhookSecretCipherAdapter(random_bytes(32));

    $ciphertext = $cipher->encrypt('whsec_abcdef0123456789');

    self::assertNotSame('whsec_abcdef0123456789', $ciphertext);
    self::assertSame('whsec_abcdef0123456789', $cipher->decrypt($ciphertext));
  }

  #[Test]
  public function encryptingTheSameSecretTwiceProducesDifferentCiphertexts(): void
  {
    $cipher = new OpensslWebhookSecretCipherAdapter(random_bytes(32));

    $first = $cipher->encrypt('whsec_same-secret');
    $second = $cipher->encrypt('whsec_same-secret');

    // A fresh random nonce per call must make ciphertexts diverge even for
    // identical plaintext.
    self::assertNotSame($first, $second);
    self::assertSame('whsec_same-secret', $cipher->decrypt($first));
    self::assertSame('whsec_same-secret', $cipher->decrypt($second));
  }

  #[Test]
  public function decryptingATamperedCiphertextThrows(): void
  {
    $cipher = new OpensslWebhookSecretCipherAdapter(random_bytes(32));
    $ciphertext = $cipher->encrypt('whsec_original-secret');

    // Flip the last base64 character to corrupt the GCM authentication tag.
    $tampered = substr($ciphertext, 0, -1) . ('A' === substr($ciphertext, -1) ? 'B' : 'A');

    $this->expectException(RuntimeException::class);

    $cipher->decrypt($tampered);
  }

  #[Test]
  public function decryptingWithADifferentKeyThrows(): void
  {
    $encryptingCipher = new OpensslWebhookSecretCipherAdapter(random_bytes(32));
    $ciphertext = $encryptingCipher->encrypt('whsec_original-secret');

    $decryptingCipher = new OpensslWebhookSecretCipherAdapter(random_bytes(32));

    $this->expectException(RuntimeException::class);

    $decryptingCipher->decrypt($ciphertext);
  }

  #[Test]
  public function decryptingMalformedCiphertextThrows(): void
  {
    $cipher = new OpensslWebhookSecretCipherAdapter(random_bytes(32));

    $this->expectException(RuntimeException::class);

    $cipher->decrypt(base64_encode('too-short'));
  }
}
