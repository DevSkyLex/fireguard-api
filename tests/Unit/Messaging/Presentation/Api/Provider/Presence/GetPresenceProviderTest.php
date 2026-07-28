<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Provider\Presence;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use Messaging\Application\Contract\Presence\MemberPresenceView;
use Messaging\Application\UseCase\Query\Presence\GetPresence\{GetPresenceQuery, GetPresenceResult};
use Messaging\Domain\Exception\MessagingAccessDeniedException;
use Messaging\Presentation\Api\Dto\Output\PresenceOutput;
use Messaging\Presentation\Api\Provider\Presence\GetPresenceProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function implode;

/**
 * Test GetPresenceProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetPresenceProvider::class)]
final class GetPresenceProviderTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440500';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';

  private const string MEMBER_A = '550e8400-e29b-41d4-a716-446655441301';

  private const string MEMBER_B = '550e8400-e29b-41d4-a716-446655441302';

  #[Test]
  public function testProvideParsesTheMemberIdsFilterAndMapsThePresences(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetPresenceQuery $query): bool => self::USER_ID === $query->userId
        && self::ORG_ID === $query->organizationId
        && [self::MEMBER_A, self::MEMBER_B] === $query->memberIds))
      ->willReturn(new GetPresenceResult([
        new MemberPresenceView(self::MEMBER_A, true, '2026-01-01T00:00:00+00:00'),
        new MemberPresenceView(self::MEMBER_B, false, null),
      ]));

    $requestStack = new RequestStack();
    $requestStack->push(new Request([
      'organization' => '/api/organizations/' . self::ORG_ID,
      'memberIds' => self::MEMBER_A . ',' . self::MEMBER_B,
    ]));

    $provider = new GetPresenceProvider($queryBus, $this->securityWithUser(), $requestStack);

    $result = $provider->provide(new GetCollection());

    self::assertCount(2, $result);
    self::assertInstanceOf(PresenceOutput::class, $result[0]);
    self::assertSame(self::MEMBER_A, $result[0]->memberId);
    self::assertTrue($result[0]->online);
    self::assertSame('2026-01-01T00:00:00+00:00', $result[0]->lastSeenAt);
    self::assertSame(self::MEMBER_B, $result[1]->memberId);
    self::assertFalse($result[1]->online);
    self::assertNull($result[1]->lastSeenAt);
  }

  #[Test]
  public function testProvideDeduplicatesAndTrimsMemberIds(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetPresenceQuery $query): bool => [self::MEMBER_A] === $query->memberIds))
      ->willReturn(new GetPresenceResult([]));

    $requestStack = new RequestStack();
    $requestStack->push(new Request([
      'organization' => '/api/organizations/' . self::ORG_ID,
      'memberIds' => ' ' . self::MEMBER_A . ' , ' . self::MEMBER_A . ',,',
    ]));

    $provider = new GetPresenceProvider($queryBus, $this->securityWithUser(), $requestStack);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideThrowsWhenOrganizationIsMissing(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(new Request(['memberIds' => self::MEMBER_A]));

    $provider = new GetPresenceProvider($this->createStub(QueryBusPort::class), $this->securityWithUser(), $requestStack);

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideThrowsWhenMemberIdsIsMissing(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(new Request(['organization' => '/api/organizations/' . self::ORG_ID]));

    $provider = new GetPresenceProvider($this->createStub(QueryBusPort::class), $this->securityWithUser(), $requestStack);

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideThrowsWhenMoreThanTheMaximumMemberIdsAreRequested(): void
  {
    $tooMany = [];
    for ($i = 0; $i < 101; ++$i) {
      $tooMany[] = 'member-' . $i;
    }

    $requestStack = new RequestStack();
    $requestStack->push(new Request([
      'organization' => '/api/organizations/' . self::ORG_ID,
      'memberIds' => implode(',', $tooMany),
    ]));

    $provider = new GetPresenceProvider($this->createStub(QueryBusPort::class), $this->securityWithUser(), $requestStack);

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideMapsMessagingExceptionsRaisedByTheQueryBus(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new MessagingAccessDeniedException('Not a member.'));

    $requestStack = new RequestStack();
    $requestStack->push(new Request([
      'organization' => '/api/organizations/' . self::ORG_ID,
      'memberIds' => self::MEMBER_A,
    ]));

    $provider = new GetPresenceProvider($queryBus, $this->securityWithUser(), $requestStack);

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideThrowsWhenTheUserIsNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new GetPresenceProvider($this->createStub(QueryBusPort::class), $security, new RequestStack());

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection());
  }

  private function securityWithUser(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return $security;
  }
}
