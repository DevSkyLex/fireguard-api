<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\GetOrganizationMember\{GetOrganizationMemberQuery, GetOrganizationMemberResult};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationMemberOutput;
use Organization\Presentation\Api\Provider\Organization\GetOrganizationMemberProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

/**
 * Test GetOrganizationMemberProviderTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetOrganizationMemberProvider::class)]
final class GetOrganizationMemberProviderTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655442800';

  private const string OTHER_ORG_ID = '550e8400-e29b-41d4-a716-446655442899';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655442801';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655442802';

  #[Test]
  public function testProvideThrowsWhenUnauthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new GetOrganizationMemberProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['organizationId' => self::ORG_ID, 'memberId' => self::MEMBER_ID]);
  }

  #[Test]
  public function testProvideThrowsWhenUriVariablesMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $provider = new GetOrganizationMemberProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new Get(), ['organizationId' => self::ORG_ID]);
  }

  #[Test]
  public function testProvideThrowsWhenPermissionIsMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with(self::USER_ID, self::ORG_ID, 'organization.members.read')
      ->willReturn(false);

    $provider = new GetOrganizationMemberProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['organizationId' => self::ORG_ID, 'memberId' => self::MEMBER_ID]);
  }

  #[Test]
  public function testProvideMapsResult(): void
  {
    $joinedAt = new DateTimeImmutable('-3 days');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetOrganizationMemberQuery $query): bool => self::MEMBER_ID === $query->memberId))
      ->willReturn(new GetOrganizationMemberResult(
        id: self::MEMBER_ID,
        organizationId: self::ORG_ID,
        userId: self::USER_ID,
        isActive: true,
        joinedAt: $joinedAt,
      ));

    $provider = new GetOrganizationMemberProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(new Get(), ['organizationId' => self::ORG_ID, 'memberId' => self::MEMBER_ID]);

    self::assertInstanceOf(OrganizationMemberOutput::class, $output);
    self::assertSame(self::MEMBER_ID, $output->id);
    self::assertSame(self::ORG_ID, $output->organizationId);
    self::assertSame(self::USER_ID, $output->userId);
    self::assertTrue($output->isActive);
    self::assertSame($joinedAt->format('c'), $output->joinedAt);
  }

  #[Test]
  public function testProvideReturns404WhenTheMemberBelongsToAnotherOrganization(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new GetOrganizationMemberResult(
      id: self::MEMBER_ID,
      organizationId: self::OTHER_ORG_ID,
      userId: self::USER_ID,
      isActive: true,
      joinedAt: new DateTimeImmutable('-1 day'),
    ));

    $provider = new GetOrganizationMemberProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get(), ['organizationId' => self::ORG_ID, 'memberId' => self::MEMBER_ID]);
  }

  private function createSecurityUser(): SecurityUser
  {
    return new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }
}
