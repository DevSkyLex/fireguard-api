<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationInvitationStatusOptionOutput;
use Organization\Presentation\Api\Provider\Organization\ListOrganizationInvitationStatusesProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[CoversClass(ListOrganizationInvitationStatusesProvider::class)]
final class ListOrganizationInvitationStatusesProviderTest extends TestCase
{
  #[Test]
  public function testProvideThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $provider = new ListOrganizationInvitationStatusesProvider(
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideReturnsOrganizationInvitationStatusOptions(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441905'));

    $provider = new ListOrganizationInvitationStatusesProvider(
      security: $security,
    );

    $output = $provider->provide(new GetCollection());

    self::assertCount(4, $output);
    self::assertInstanceOf(OrganizationInvitationStatusOptionOutput::class, $output[0]);

    self::assertSame('pending', $output[0]->value);
    self::assertSame('Pending', $output[0]->label);
    self::assertSame('accepted', $output[1]->value);
    self::assertSame('Accepted', $output[1]->label);
    self::assertSame('revoked', $output[2]->value);
    self::assertSame('Revoked', $output[2]->label);
    self::assertSame('expired', $output[3]->value);
    self::assertSame('Expired', $output[3]->label);
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
