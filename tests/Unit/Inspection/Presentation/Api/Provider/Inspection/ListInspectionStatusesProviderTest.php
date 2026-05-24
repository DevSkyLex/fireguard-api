<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Provider\Inspection;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Presentation\Api\Provider\Inspection\ListInspectionStatusesProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[CoversClass(ListInspectionStatusesProvider::class)]
final class ListInspectionStatusesProviderTest extends TestCase
{
  #[Test]
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new ListInspectionStatusesProvider($security);

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideReturnsSupportedStatuses(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $provider = new ListInspectionStatusesProvider($security);

    $outputs = $provider->provide(new GetCollection());

    self::assertCount(3, $outputs);
    self::assertSame('draft', $outputs[0]->value);
    self::assertSame('Draft', $outputs[0]->label);
    self::assertSame('submitted', $outputs[1]->value);
    self::assertSame('closed', $outputs[2]->value);
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
