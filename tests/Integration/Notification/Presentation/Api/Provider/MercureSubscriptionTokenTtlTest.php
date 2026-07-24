<?php

declare(strict_types=1);

namespace Tests\Integration\Notification\Presentation\Api\Provider;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;

use function base64_decode;
use function explode;
use function is_numeric;
use function json_decode;
use function strtr;

use const JSON_THROW_ON_ERROR;

/**
 * Test MercureSubscriptionTokenTtlTest.
 *
 * Pins the wiring behind the subscriber-token TTL, which unit tests cannot
 * reach: the providers receive their lifetime through the env expression
 * `%env(default:mercure_subscriber_token_ttl:int:MERCURE_SUBSCRIBER_TOKEN_TTL)%`,
 * so a typo in the processor chain, a missing fallback parameter, or a
 * regression back to the bundle's null lifetime would all still pass the
 * mocked unit tests while silently minting eternal credentials again.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MercureSubscriptionTokenTtlTest extends KernelTestCase
{
  #[Test]
  public function testTheTtlFallbackParameterResolvesWithoutTheEnvVariable(): void
  {
    self::bootKernel();

    $ttl = static::getContainer()->getParameter('mercure_subscriber_token_ttl');

    self::assertTrue(is_numeric($ttl));
    $ttlSeconds = (int) $ttl;
    self::assertGreaterThan(0, $ttlSeconds);

    // Short by design. A generous ceiling here still catches the two regressions
    // that matter: a lifetime accidentally expressed in ms, or one widened to
    // hours/days, at which point the token stops being meaningfully short-lived.
    self::assertLessThanOrEqual(3600, $ttlSeconds);
  }

  #[Test]
  public function testTheHubTokenFactoryStillEmitsNoExpiryOfItsOwn(): void
  {
    self::bootKernel();

    $factory = static::getContainer()->get('mercure.hub.default.jwt.factory');
    self::assertInstanceOf(TokenFactoryInterface::class, $factory);

    $claims = $this->decodeClaims($factory->create(['/topic'], []));

    // The Mercure bundle hardcodes a null lifetime, so the factory itself never
    // sets `exp`. This is the exact reason the providers pass the claim
    // explicitly — if this assertion ever fails the bundle changed its default
    // and the per-call-site claim can be reconsidered.
    self::assertArrayNotHasKey(
      'exp',
      $claims,
      'The Mercure token factory now sets its own expiry; revisit the explicit exp claim in the subscription providers.',
    );
  }

  /**
   * Decodes a JWT payload into its claim array without pulling in the JWT
   * parser (whose non-empty-string contract the raw factory output does not
   * statically satisfy). The token is trusted here — it was just minted by the
   * container's own factory — so no signature check is needed.
   *
   * @return array<string, mixed>
   */
  private function decodeClaims(string $jwt): array
  {
    $segments = explode('.', $jwt);
    self::assertCount(3, $segments, 'Expected a three-segment JWT.');

    $payload = base64_decode(strtr($segments[1], '-_', '+/'), true);
    self::assertNotFalse($payload, 'JWT payload is not valid base64url.');

    $claims = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
    self::assertIsArray($claims);

    /** @var array<string, mixed> $claims */
    return $claims;
  }
}
