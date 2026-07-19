<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationLegalTypeOptionOutput;
use Organization\Presentation\Api\Provider\Organization\ListOrganizationLegalTypesProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[CoversClass(ListOrganizationLegalTypesProvider::class)]
final class ListOrganizationLegalTypesProviderTest extends TestCase
{
  #[Test]
  public function testProvideThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $provider = new ListOrganizationLegalTypesProvider(
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideReturnsOrganizationLegalTypeOptions(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441904'));

    $provider = new ListOrganizationLegalTypesProvider(
      security: $security,
    );

    $output = $provider->provide(new GetCollection());

    self::assertCount(7, $output);
    self::assertInstanceOf(OrganizationLegalTypeOptionOutput::class, $output[0]);

    self::assertSame('sole_proprietorship', $output[0]->value);
    self::assertSame('Sole proprietorship', $output[0]->label);
    self::assertSame('limited_liability_company', $output[2]->value);
    self::assertSame('Limited liability company', $output[2]->label);
    self::assertSame('other', $output[6]->value);
    self::assertSame('Other', $output[6]->label);
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
