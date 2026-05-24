<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Provider\NonConformity;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Presentation\Api\Provider\NonConformity\ListNonConformityStatusesProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[CoversClass(ListNonConformityStatusesProvider::class)]
final class ListNonConformityStatusesProviderTest extends TestCase
{
  #[Test]
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new ListNonConformityStatusesProvider($security);

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideReturnsSupportedNonConformityStatuses(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $provider = new ListNonConformityStatusesProvider($security);

    $outputs = $provider->provide(new GetCollection());

    self::assertCount(4, $outputs);
    self::assertSame('open', $outputs[0]->value);
    self::assertSame('Open', $outputs[0]->label);
    self::assertSame('in_progress', $outputs[1]->value);
    self::assertSame('In progress', $outputs[1]->label);
    self::assertSame('done', $outputs[2]->value);
    self::assertSame('waived', $outputs[3]->value);
  }

  private function createSecurityUser(): SecurityUser
  {
    return new SecurityUser(
      id: '550e8400-e29b-41d4-a716-446655440001',
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }
}
