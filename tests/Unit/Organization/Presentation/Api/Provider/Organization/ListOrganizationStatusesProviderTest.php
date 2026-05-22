<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationStatusOptionOutput;
use Organization\Presentation\Api\Provider\Organization\ListOrganizationStatusesProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[CoversClass(ListOrganizationStatusesProvider::class)]
final class ListOrganizationStatusesProviderTest extends TestCase
{
  #[Test]
  public function testProvideThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $provider = new ListOrganizationStatusesProvider(
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideReturnsOrganizationStatusOptions(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441904'));

    $provider = new ListOrganizationStatusesProvider(
      security: $security,
    );

    $output = $provider->provide(new GetCollection());

    self::assertCount(3, $output);
    self::assertInstanceOf(OrganizationStatusOptionOutput::class, $output[0]);

    self::assertSame('active', $output[0]->value);
    self::assertSame('Active', $output[0]->label);
    self::assertSame('suspended', $output[1]->value);
    self::assertSame('Suspended', $output[1]->label);
    self::assertSame('archived', $output[2]->value);
    self::assertSame('Archived', $output[2]->label);
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
