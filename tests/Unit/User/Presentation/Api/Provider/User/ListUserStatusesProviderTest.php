<?php

declare(strict_types=1);

namespace Tests\Unit\User\Presentation\Api\Provider\User;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use User\Presentation\Api\Provider\User\ListUserStatusesProvider;

#[CoversClass(ListUserStatusesProvider::class)]
final class ListUserStatusesProviderTest extends TestCase
{
  #[Test]
  public function testProvideThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new ListUserStatusesProvider($security);

    $this->expectException(UnauthorizedHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideReturnsSupportedUserStatuses(): void
  {
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(id: 'admin-1', email: 'admin@example.com', password: ''),
    );

    $provider = new ListUserStatusesProvider($security);

    $outputs = $provider->provide(new GetCollection());

    self::assertCount(4, $outputs);
    self::assertSame('active', $outputs[0]->value);
    self::assertSame('Active', $outputs[0]->label);
    self::assertSame('inactive', $outputs[1]->value);
    self::assertSame('locked', $outputs[2]->value);
    self::assertSame('pending_verification', $outputs[3]->value);
    self::assertSame('Pending Verification', $outputs[3]->label);
  }
}
