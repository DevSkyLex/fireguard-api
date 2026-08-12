<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\UseCase\Query\Organization\ListOrganizationAuditEvents\{ListOrganizationAuditEventsQuery, OrganizationAuditEventResult};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationMemberNotFoundException, OrganizationNotFoundException};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationAuditEventOutput;
use Organization\Presentation\Api\Provider\Organization\ListOrganizationAuditEventsProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use User\Application\UseCase\Query\User\GetUser\GetUserQuery;

use function iterator_to_array;

/**
 * Test ListOrganizationAuditEventsProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListOrganizationAuditEventsProvider::class)]
final class ListOrganizationAuditEventsProviderTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655447001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655447002';

  private const string OUTSIDE_ACTOR_ID = '550e8400-e29b-41d4-a716-446655447003';

  #[Test]
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn(null);

    $provider = new ListOrganizationAuditEventsProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProvideForwardsTheFiltersAndClampsThePageSizeToOneHundred(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListOrganizationAuditEventsQuery $query): bool {
        self::assertSame(self::ORGANIZATION_ID, $query->organizationId);
        self::assertSame(self::USER_ID, $query->userId);
        self::assertSame('organization.member_added', $query->action);
        self::assertNotNull($query->from);
        self::assertSame('2026-03-01T00:00:00+00:00', $query->from->format('c'));
        self::assertNotNull($query->to);
        self::assertSame('2026-03-31T23:59:59+00:00', $query->to->format('c'));

        // ?itemsPerPage=400 is clamped to 100 here, and the offset is derived
        // from the clamped size — not from what the caller asked for.
        self::assertSame(100, $query->pagination->limit);
        self::assertSame(100, $query->pagination->offset);

        return true;
      }))
      ->willReturn(new PaginatedResult(items: [], total: 0, limit: 100, offset: 100));

    $provider = new ListOrganizationAuditEventsProvider(
      queryBus: $queryBus,
      security: $this->securityFor(self::USER_ID),
    );

    $paginator = $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID], [
      'filters' => [
        'action' => 'organization.member_added',
        'from' => '2026-03-01T00:00:00Z',
        'to' => '2026-03-31T23:59:59Z',
        'page' => '2',
        'itemsPerPage' => '400',
      ],
    ]);

    self::assertInstanceOf(TraversablePaginator::class, $paginator);
    self::assertSame(100.0, $paginator->getItemsPerPage());
  }

  #[Test]
  public function testProvideResolvesTheActorNameOnlyForAnOrganizationMember(): void
  {
    $askedUserIds = [];

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturnCallback(
      function (object $message) use (&$askedUserIds): object {
        if ($message instanceof GetUserQuery) {
          $askedUserIds[] = $message->id;

          throw new RuntimeException('user lookup is irrelevant to this assertion');
        }

        return new PaginatedResult(
          items: [
            $this->makeResult('550e8400-e29b-41d4-a716-446655447010', self::USER_ID, true),
            $this->makeResult('550e8400-e29b-41d4-a716-446655447011', self::OUTSIDE_ACTOR_ID, false),
          ],
          total: 2,
          limit: 30,
          offset: 0,
        );
      },
    );

    $provider = new ListOrganizationAuditEventsProvider(
      queryBus: $queryBus,
      security: $this->securityFor(self::USER_ID),
    );

    $paginator = $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
    /** @var list<OrganizationAuditEventOutput> $outputs */
    $outputs = iterator_to_array($paginator);

    self::assertCount(2, $outputs);
    self::assertNull($outputs[0]->actorDisplayName, 'A failed lookup must not fail the listing.');
    self::assertNull($outputs[1]->actorDisplayName, 'A non-member actor must never be named.');
    self::assertSame(self::OUTSIDE_ACTOR_ID, $outputs[1]->actorId, 'The opaque actor id is still published.');
    self::assertSame(
      [self::USER_ID],
      $askedUserIds,
      'Only the member actor may be looked up — a non-member actor must not even be queried.',
    );
  }

  #[Test]
  public function testProvideMapsAMalformedDateFilterToBadRequest(): void
  {
    $provider = new ListOrganizationAuditEventsProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      security: $this->securityFor(self::USER_ID),
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID], [
      'filters' => ['from' => 'last tuesday-ish'],
    ]);
  }

  #[Test]
  public function testProvideMapsAMissingPermissionToForbidden(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(
      OrganizationAccessDeniedException::missingPermission('organization.audit.read'),
    );

    $provider = new ListOrganizationAuditEventsProvider(
      queryBus: $queryBus,
      security: $this->securityFor(self::USER_ID),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  /**
   * The two causes of a 404 must produce the SAME detail, or the message
   * itself tells an outsider whether the organization exists — the oracle the
   * shared status code is there to close.
   */
  #[Test]
  public function testProvideGivesTheSame404DetailForAnUnknownOrganizationAndANonMember(): void
  {
    $details = [];

    foreach ([
      OrganizationNotFoundException::withId(self::ORGANIZATION_ID),
      OrganizationMemberNotFoundException::forUserInOrganization(self::USER_ID, self::ORGANIZATION_ID),
    ] as $domainException) {
      $queryBus = $this->createStub(QueryBusPort::class);
      $queryBus->method('ask')->willThrowException($domainException);

      $provider = new ListOrganizationAuditEventsProvider(
        queryBus: $queryBus,
        security: $this->securityFor(self::USER_ID),
      );

      try {
        $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
        self::fail('Expected a NotFoundHttpException.');
      } catch (NotFoundHttpException $exception) {
        $details[] = $exception->getMessage();
      }
    }

    self::assertSame($details[0], $details[1]);
    self::assertStringNotContainsString(self::ORGANIZATION_ID, $details[0]);
  }

  #[Test]
  public function testProvideUnwrapsAMissingPermissionWrappedByTheMessengerBus(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(MessengerRuntimeException::wrap(
      new HandlerFailedException(
        new Envelope(new ListOrganizationAuditEventsQuery(self::ORGANIZATION_ID, self::USER_ID)),
        [OrganizationAccessDeniedException::missingPermission('organization.audit.read')],
      ),
    ));

    $provider = new ListOrganizationAuditEventsProvider(
      queryBus: $queryBus,
      security: $this->securityFor(self::USER_ID),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProvideUnwrapsAMissingMembershipWrappedByTheMessengerBus(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(MessengerRuntimeException::wrap(
      new HandlerFailedException(
        new Envelope(new ListOrganizationAuditEventsQuery(self::ORGANIZATION_ID, self::USER_ID)),
        [OrganizationMemberNotFoundException::forUserInOrganization(self::USER_ID, self::ORGANIZATION_ID)],
      ),
    ));

    $provider = new ListOrganizationAuditEventsProvider(
      queryBus: $queryBus,
      security: $this->securityFor(self::USER_ID),
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProvideRethrowsAnUnrecognisedMessengerFailure(): void
  {
    $failure = MessengerRuntimeException::wrap(
      new HandlerFailedException(
        new Envelope(new ListOrganizationAuditEventsQuery(self::ORGANIZATION_ID, self::USER_ID)),
        [new RuntimeException('the ledger is offline')],
      ),
    );

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException($failure);

    $provider = new ListOrganizationAuditEventsProvider(
      queryBus: $queryBus,
      security: $this->securityFor(self::USER_ID),
    );

    $this->expectExceptionObject($failure);

    $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  private function securityFor(string $userId): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: $userId,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return $security;
  }

  private function makeResult(string $id, string $actorId, bool $actorIsOrganizationMember): OrganizationAuditEventResult
  {
    return new OrganizationAuditEventResult(
      id: $id,
      action: 'organization.member_added',
      actorType: 'user',
      actorId: $actorId,
      actorIsOrganizationMember: $actorIsOrganizationMember,
      subjectType: 'organization_member',
      subjectId: '550e8400-e29b-41d4-a716-446655447020',
      metadata: ['user_id' => $actorId],
      occurredAt: '2026-03-15T10:00:00+00:00',
      recordedAt: '2026-03-15T10:00:01+00:00',
    );
  }
}
