<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Presentation\Api\Provider\MercureSubscription;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Notification\Presentation\Api\Dto\Output\MercureSubscription\MercureSubscriptionOutput;
use Notification\Presentation\Api\Provider\MercureSubscription\GetMercureSubscriptionProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;

/**
 * Test GetMercureSubscriptionProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetMercureSubscriptionProvider::class)]
final class GetMercureSubscriptionProviderTest extends TestCase
{
  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64e01';

  #[Test]
  public function testProvideRequiresAuthentication(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new GetMercureSubscriptionProvider(
      $security,
      $this->createStub(TokenFactoryInterface::class),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get());
  }

  #[Test]
  public function testProvideMintsATokenScopedToTheCallersOwnTopic(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->securityUser());

    $tokenFactory = $this->createMock(TokenFactoryInterface::class);
    $tokenFactory->expects(self::once())
      ->method('create')
      ->with(['/users/' . self::USER_ID . '/notifications'], self::anything(), self::anything())
      ->willReturn('jwt-token');

    $provider = new GetMercureSubscriptionProvider($security, $tokenFactory);

    $output = $provider->provide(new Get());

    self::assertInstanceOf(MercureSubscriptionOutput::class, $output);
    self::assertSame('jwt-token', $output->token);
    self::assertSame('/users/' . self::USER_ID . '/notifications', $output->topic);
  }

  #[Test]
  public function testProvideGrantsNoPublishRight(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->securityUser());

    $tokenFactory = $this->createMock(TokenFactoryInterface::class);
    $tokenFactory->expects(self::once())
      ->method('create')
      ->with(self::anything(), [], self::anything())
      ->willReturn('jwt-token');

    $provider = new GetMercureSubscriptionProvider($security, $tokenFactory);

    $provider->provide(new Get());
  }

  #[Test]
  public function testProvideMintsAShortLivedToken(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->securityUser());

    $tokenFactory = $this->createMock(TokenFactoryInterface::class);
    $tokenFactory->expects(self::once())
      ->method('create')
      ->with(
        self::anything(),
        self::anything(),
        self::callback(static function (array $claims): bool {
          self::assertArrayHasKey('exp', $claims);
          self::assertInstanceOf(DateTimeImmutable::class, $claims['exp']);

          // The Mercure bundle emits no `exp` of its own, so without this claim
          // the token would be an eternal, non-revocable read credential for
          // the user's whole notification stream.
          $secondsFromNow = $claims['exp']->getTimestamp() - new DateTimeImmutable()->getTimestamp();
          self::assertGreaterThan(0, $secondsFromNow);
          self::assertLessThanOrEqual(60, $secondsFromNow);

          return true;
        }),
      )
      ->willReturn('jwt-token');

    $provider = new GetMercureSubscriptionProvider($security, $tokenFactory, tokenTtl: 60);

    $provider->provide(new Get());
  }

  private function securityUser(): SecurityUser
  {
    return new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true);
  }
}
