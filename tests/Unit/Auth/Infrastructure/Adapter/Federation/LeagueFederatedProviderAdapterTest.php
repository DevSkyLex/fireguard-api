<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Adapter\Federation;

use Auth\Domain\ValueObject\Federation\FederatedProvider;
use Auth\Infrastructure\Adapter\Federation\{GooglePkceProviderAdapter, LeagueFederatedProviderAdapter, OidcPkceProviderAdapter};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

use function parse_str;
use function parse_url;

use const PHP_URL_QUERY;

/**
 * Test LeagueFederatedProviderAdapterTest.
 *
 * @category Adapter Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(LeagueFederatedProviderAdapter::class)]
#[CoversClass(GooglePkceProviderAdapter::class)]
#[CoversClass(OidcPkceProviderAdapter::class)]
final class LeagueFederatedProviderAdapterTest extends TestCase
{
  /**
   * Supplies every supported identity provider.
   *
   * @since 1.0.0
   *
   * @return iterable<string, array{FederatedProvider}>
   */
  public static function provider(): iterable
  {
    yield 'Google' => [FederatedProvider::GOOGLE];
    yield 'Microsoft' => [FederatedProvider::MICROSOFT];
  }

  /**
   * Verifies modern prompt parameters and PKCE S256 for authorization starts.
   *
   * @since 1.0.0
   */
  #[Test]
  #[DataProvider('provider')]
  public function testStartUsesPkceWithoutLegacyApprovalPrompt(FederatedProvider $provider): void
  {
    $adapter = new LeagueFederatedProviderAdapter(
      googleEnabled: true,
      googleClientId: 'google-client',
      googleClientSecret: 'google-secret',
      microsoftEnabled: true,
      microsoftClientId: 'microsoft-client',
      microsoftClientSecret: 'microsoft-secret',
    );

    $authorization = $adapter->start(
      provider: $provider,
      redirectUri: 'https://app.example.com/oauth/callback',
      state: 'expected-state',
    );

    $query = parse_url($authorization->authorizationUrl, PHP_URL_QUERY);
    self::assertIsString($query);
    parse_str($query, $parameters);

    self::assertSame('expected-state', $parameters['state'] ?? null);
    self::assertSame('select_account', $parameters['prompt'] ?? null);
    self::assertArrayNotHasKey('approval_prompt', $parameters);
    self::assertSame('S256', $parameters['code_challenge_method'] ?? null);
    self::assertNotSame('', $parameters['code_challenge'] ?? '');
    self::assertNotSame('', $authorization->codeVerifier);
  }
}
