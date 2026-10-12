<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\Serialization\Decoder;

use OAuth\Domain\Exception\Token\AuthorizationException;
use OAuth\Presentation\Api\Serialization\Decoder\OAuthFormDecoder;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * Pins exact credential bytes, omitted/empty scope and rejection of ambiguous form structures.
 *
 * @category Decoder Tests
 */
final class OAuthFormDecoderTest extends TestCase
{
  #[Test]
  public function scalarCredentialsDecodeWithoutChangingCaseOrEncodedPlusCharacters(): void
  {
    $decoder = new OAuthFormDecoder();
    self::assertTrue($decoder->supportsDecoding('form'));
    self::assertFalse($decoder->supportsDecoding('json'));
    self::assertSame(['grant_type' => 'client_credentials', 'client_secret' => 'Case+Sensitive&Secret', 'scope' => 'READ OPENID'], $decoder->decode('grant_type=client_credentials&client_secret=Case%2BSensitive%26Secret&scope=READ+OPENID', 'form'));
    self::assertSame(['scope' => ''], $decoder->decode('scope=', 'form'));
    self::assertSame([], $decoder->decode('', 'form'));
  }

  #[Test]
  #[DataProvider('ambiguousBodies')]
  public function ambiguousOrNestedCredentialInputFailsAsAnOAuthInvalidRequest(string $body): void
  {
    $this->expectException(AuthorizationException::class);
    $this->expectExceptionCode(400);

    new OAuthFormDecoder()->decode($body, 'form');
  }

  /**
   * @return iterable<string, array{string}>
   */
  public static function ambiguousBodies(): iterable
  {
    yield 'nested secret' => ['client_secret%5B%5D=value'];
    yield 'nested token' => ['token%5Bpayload%5D=value'];
    yield 'duplicate client' => ['client_id=one&client_id=two'];
    yield 'encoded duplicate' => ['client_id=one&%63lient_id=two'];
    yield 'PHP name alias' => ['client.id=one'];
    yield 'invalid escape' => ['client_secret=%GG'];
    yield 'numeric field' => ['0=credential'];
  }
}
