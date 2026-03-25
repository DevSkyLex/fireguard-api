<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Provider\Facility;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use Facility\Presentation\Api\Dto\Output\Facility\FacilityStatusOptionOutput;
use Facility\Presentation\Api\Provider\Facility\ListFacilityStatusesProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[CoversClass(ListFacilityStatusesProvider::class)]
final class ListFacilityStatusesProviderTest extends TestCase
{
  #[Test]
  public function testProvideThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $provider = new ListFacilityStatusesProvider(
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideReturnsFacilityStatusOptions(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441903'));

    $provider = new ListFacilityStatusesProvider(
      security: $security,
    );

    $output = $provider->provide(new GetCollection());

    self::assertCount(2, $output);
    self::assertInstanceOf(FacilityStatusOptionOutput::class, $output[0]);

    self::assertSame('active', $output[0]->value);
    self::assertSame('Active', $output[0]->label);
    self::assertSame('archived', $output[1]->value);
    self::assertSame('Archived', $output[1]->label);
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
