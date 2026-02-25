<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Provider\Facility;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use Facility\Presentation\Api\Dto\Output\Facility\FacilityTypeOptionOutput;
use Facility\Presentation\Api\Provider\Facility\ListFacilityTypesProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[CoversClass(ListFacilityTypesProvider::class)]
final class ListFacilityTypesProviderTest extends TestCase
{
  #[Test]
  public function testProvideThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $provider = new ListFacilityTypesProvider(
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideReturnsFacilityTypeOptions(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441902'));

    $provider = new ListFacilityTypesProvider(
      security: $security,
    );

    $output = $provider->provide(new GetCollection());

    self::assertCount(5, $output);
    self::assertInstanceOf(FacilityTypeOptionOutput::class, $output[0]);

    self::assertSame('site', $output[0]->value);
    self::assertSame('Site', $output[0]->label);
    self::assertSame('building', $output[1]->value);
    self::assertSame('floor', $output[2]->value);
    self::assertSame('zone', $output[3]->value);
    self::assertSame('area', $output[4]->value);
  }

  private function createSecurityUser(string $id): SecurityUser
  {
    return new SecurityUser(
      id: $id,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }
}
