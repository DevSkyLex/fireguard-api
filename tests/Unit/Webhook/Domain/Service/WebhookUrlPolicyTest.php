<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Domain\Service;

use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Webhook\Domain\Exception\WebhookValidationException;
use Webhook\Domain\Service\WebhookUrlPolicy;

/**
 * Test WebhookUrlPolicyTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(WebhookUrlPolicy::class)]
final class WebhookUrlPolicyTest extends TestCase
{
  #[Test]
  public function itAcceptsAPublicHttpsUrl(): void
  {
    $this->expectNotToPerformAssertions();

    $policy = new WebhookUrlPolicy(allowInsecureUrls: false);

    $policy->assertValidUrl('https://example.com/webhooks/fireguard');
  }

  #[Test]
  public function itRejectsAPlainHttpUrlByDefault(): void
  {
    $policy = new WebhookUrlPolicy(allowInsecureUrls: false);

    $this->expectException(WebhookValidationException::class);

    $policy->assertValidUrl('http://example.com/webhooks');
  }

  #[Test]
  public function itAcceptsAPlainHttpUrlWhenInsecureUrlsAreAllowed(): void
  {
    $this->expectNotToPerformAssertions();

    $policy = new WebhookUrlPolicy(allowInsecureUrls: true);

    $policy->assertValidUrl('http://example.com/webhooks');
  }

  #[Test]
  public function itRejectsAMalformedUrl(): void
  {
    $policy = new WebhookUrlPolicy(allowInsecureUrls: false);

    $this->expectException(WebhookValidationException::class);

    $policy->assertValidUrl('not-a-url');
  }

  #[Test]
  #[DataProvider('privateAndReservedHosts')]
  public function itRejectsAUrlTargetingAPrivateLoopbackOrReservedLiteralIp(string $host): void
  {
    $policy = new WebhookUrlPolicy(allowInsecureUrls: true);

    $this->expectException(WebhookValidationException::class);

    $policy->assertValidUrl('https://' . $host . '/webhooks');
  }

  /**
   * Method privateAndReservedHosts.
   *
   * @static
   *
   * @return iterable<string, list<string>>
   */
  public static function privateAndReservedHosts(): iterable
  {
    yield 'loopback' => ['127.0.0.1'];
    yield 'private class A' => ['10.0.0.5'];
    yield 'private class B' => ['172.16.0.5'];
    yield 'private class C' => ['192.168.1.5'];
    yield 'link-local / cloud metadata' => ['169.254.169.254'];
  }

  #[Test]
  public function isPrivateOrReservedIpReturnsFalseForAPublicAddress(): void
  {
    $policy = new WebhookUrlPolicy();

    self::assertFalse($policy->isPrivateOrReservedIp('93.184.216.34'));
  }

  #[Test]
  public function isPrivateOrReservedIpReturnsTrueForAPrivateAddress(): void
  {
    $policy = new WebhookUrlPolicy();

    self::assertTrue($policy->isPrivateOrReservedIp('10.0.0.1'));
  }

  #[Test]
  public function isPrivateOrReservedIpReturnsFalseForANonIpHostname(): void
  {
    $policy = new WebhookUrlPolicy();

    // Not a literal IP at all — callers resolving a hostname must pass the
    // resolved address here, not the original host.
    self::assertFalse($policy->isPrivateOrReservedIp('example.com'));
  }
}
