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
use Symfony\Component\HttpFoundation\{Request, RequestStack};
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
      requestStack: new RequestStack(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideReturnsLegalTypeOptions(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441901'));

    $provider = new ListOrganizationLegalTypesProvider(
      security: $security,
      requestStack: new RequestStack(),
    );

    $output = $provider->provide(new GetCollection());

    self::assertCount(5, $output);
    self::assertInstanceOf(OrganizationLegalTypeOptionOutput::class, $output[0]);

    self::assertSame('FR', $output[0]->countryCode);
    self::assertSame('company', $output[0]->value);
    self::assertSame('Company', $output[0]->label);
    self::assertTrue($output[0]->requirements->registrationNumber->required);
    self::assertSame('SIREN / RCS', $output[0]->requirements->registrationNumber->label);
    self::assertSame('/^(?:[0-9]{9}|RCS-[A-Z]{3}-[0-9]{9})$/', $output[0]->requirements->registrationNumber->pattern);
    self::assertFalse($output[0]->requirements->vatNumber->required);
    self::assertSame('/^FR[A-Z0-9]{2}[0-9]{9}$/', $output[0]->requirements->vatNumber->pattern);

    self::assertSame('non_profit', $output[1]->value);
    self::assertTrue($output[1]->requirements->registrationNumber->required);

    self::assertSame('public_sector', $output[2]->value);
    self::assertFalse($output[2]->requirements->registrationNumber->required);
  }

  #[Test]
  public function testProvideCanResolveCountrySpecificRulesFromQuery(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441902'));

    $requestStack = new RequestStack();
    $requestStack->push(new Request(query: ['countryCode' => 'BE']));

    $provider = new ListOrganizationLegalTypesProvider(
      security: $security,
      requestStack: $requestStack,
    );

    $output = $provider->provide(new GetCollection());

    self::assertSame('BE', $output[0]->countryCode);
    self::assertSame('company', $output[0]->value);
    self::assertSame('KBO/BCE number', $output[0]->requirements->registrationNumber->label);
    self::assertSame('/^(?:[0-9]{10}|[0-9]{4}\.[0-9]{3}\.[0-9]{3})$/', $output[0]->requirements->registrationNumber->pattern);
    self::assertSame('/^BE0?[0-9]{9}$/', $output[0]->requirements->vatNumber->pattern);
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
