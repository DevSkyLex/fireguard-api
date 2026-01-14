<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\Provider\Discovery;

use ApiPlatform\Metadata\Operation;
use OAuth\Presentation\Api\Operation\OAuthOperations;
use OAuth\Presentation\Api\Provider\Discovery\OpenIdConfigurationProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Test OpenIdConfigurationProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: OpenIdConfigurationProvider::class)]
final class OpenIdConfigurationProviderTest extends TestCase
{
  // #region Methods
  /**
   * Method testProvideIncludesEndSessionAndPromptValues.
   *
   * Test that the discovery output includes end_session and prompt values.
   *
   * @return void no return value
   */
  #[Test]
  public function testProvideIncludesEndSessionAndPromptValues(): void
  {
    $request = Request::create('https://auth.example.com/.well-known/openid-configuration');
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
    $urlGenerator->method('generate')
      ->willReturnCallback(
        static fn (string $name, array $parameters = [], int $referenceType = 0): string => 'https://auth.example.com/' . $name,
      );

    $provider = new OpenIdConfigurationProvider(
      requestStack: $requestStack,
      urlGenerator: $urlGenerator,
      issuer: null,
      authorizePath: null,
      logoutPath: null,
    );

    $output = $provider->provide(operation: $this->createMock(Operation::class));

    self::assertSame(
      expected: 'https://auth.example.com/' . OAuthOperations::END_SESSION,
      actual: $output->endSessionEndpoint,
    );
    self::assertSame(
      expected: ['none', 'login', 'consent', 'select_account'],
      actual: $output->promptValuesSupported,
    );
  }

  /**
   * Method testProvideUsesConfiguredLogoutPath.
   *
   * Test that a configured logout path is used for end_session_endpoint.
   *
   * @return void no return value
   */
  #[Test]
  public function testProvideUsesConfiguredLogoutPath(): void
  {
    $request = Request::create('https://auth.example.com/.well-known/openid-configuration');
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
    $urlGenerator->method('generate')
      ->willThrowException(new RouteNotFoundException());

    $provider = new OpenIdConfigurationProvider(
      requestStack: $requestStack,
      urlGenerator: $urlGenerator,
      issuer: null,
      authorizePath: null,
      logoutPath: '/api/auth/logout',
    );

    $output = $provider->provide(operation: $this->createMock(Operation::class));

    self::assertSame(
      expected: 'https://auth.example.com/api/auth/logout',
      actual: $output->endSessionEndpoint,
    );
  }

  /**
   * Method testProvideFallsBackToDefaultEndSessionPathWhenRouteMissing.
   *
   * Test that fallback end_session_endpoint is used when route is missing.
   *
   * @return void no return value
   */
  #[Test]
  public function testProvideFallsBackToDefaultEndSessionPathWhenRouteMissing(): void
  {
    $request = Request::create('https://auth.example.com/.well-known/openid-configuration');
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
    $urlGenerator->method('generate')
      ->willThrowException(new RouteNotFoundException());

    $provider = new OpenIdConfigurationProvider(
      requestStack: $requestStack,
      urlGenerator: $urlGenerator,
      issuer: null,
      authorizePath: null,
      logoutPath: null,
    );

    $output = $provider->provide(operation: $this->createMock(Operation::class));

    self::assertSame(
      expected: 'https://auth.example.com/api/oauth2/logout',
      actual: $output->endSessionEndpoint,
    );
  }
  // #endregion
}
