<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Application\UseCase\Command\FeedToken\RotateCalendarFeedToken;

use Calendar\Application\Port\Outbound\FeedToken\CalendarFeedTokenRepositoryPort;
use Calendar\Application\Service\CalendarFeedTokenSecretFactory;
use Calendar\Application\UseCase\Command\FeedToken\RotateCalendarFeedToken\{RotateCalendarFeedTokenCommand, RotateCalendarFeedTokenHandler, RotateCalendarFeedTokenResult};
use Calendar\Domain\Event\{CalendarFeedTokenCreatedEvent, CalendarFeedTokenRevokedEvent};
use Calendar\Domain\Model\FeedToken\CalendarFeedToken;
use Calendar\Domain\ValueObject\CalendarFeedTokenId;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{EventDispatcherPort, UuidGeneratorPort};

use function hash;

/**
 * Test RotateCalendarFeedTokenHandlerTest.
 *
 * The secret-handling contract is the point here: the aggregate persisted
 * through the repository must hold the SHA-256 of the returned secret and
 * never the secret itself, and no dispatched event may carry either.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RotateCalendarFeedTokenHandler::class)]
final class RotateCalendarFeedTokenHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  private const string NEW_TOKEN_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64aff';

  #[Test]
  public function itEnforcesTheFeedReadPermission(): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('assertGrantedPermissions')
      ->with(self::USER_ID, self::ORGANIZATION_ID, ['organization.events.read'])
      ->willThrowException(new OrganizationAccessDeniedException('Missing permission.'));

    $repository = $this->createMock(CalendarFeedTokenRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    $handler = $this->handler($repository, $authorization);

    $this->expectException(OrganizationAccessDeniedException::class);

    $handler->__invoke(new RotateCalendarFeedTokenCommand(self::ORGANIZATION_ID, self::USER_ID));
  }

  #[Test]
  public function itCreatesATokenPersistingOnlyTheHashOfTheReturnedSecret(): void
  {
    $repository = $this->createMock(CalendarFeedTokenRepositoryPort::class);
    $repository->method('findActiveByOrganizationAndUser')->willReturn(null);

    /** @var list<CalendarFeedToken> $saved */
    $saved = [];
    $repository->expects(self::once())
      ->method('save')
      ->willReturnCallback(static function (CalendarFeedToken $token) use (&$saved): void {
        $saved[] = $token;
      });

    $dispatched = [];
    $dispatcher = $this->createStub(EventDispatcherPort::class);
    $dispatcher->method('dispatch')->willReturnCallback(static function (object $event) use (&$dispatched): void {
      $dispatched[] = $event;
    });

    $handler = $this->handler($repository, null, $dispatcher);

    $result = $handler->__invoke(new RotateCalendarFeedTokenCommand(self::ORGANIZATION_ID, self::USER_ID));

    self::assertInstanceOf(RotateCalendarFeedTokenResult::class, $result);
    self::assertFalse($result->rotated);
    self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $result->secret);

    self::assertCount(1, $saved);
    $token = $saved[0];
    self::assertSame(hash('sha256', $result->secret), $token->tokenHash());
    self::assertStringNotContainsString($result->secret, $token->tokenHash());
    self::assertSame(self::ORGANIZATION_ID, $token->organizationId());
    self::assertSame(self::USER_ID, $token->userId());

    // A fresh creation dispatches exactly one event — created, never revoked.
    self::assertCount(1, $dispatched);
    $event = $dispatched[0];
    self::assertInstanceOf(CalendarFeedTokenCreatedEvent::class, $event);
    self::assertFalse($event->rotated);
    self::assertSame(self::NEW_TOKEN_ID, $event->tokenId);
  }

  #[Test]
  public function itRevokesThePreviousTokenOnRotationAndAuditsBothSides(): void
  {
    $previous = CalendarFeedToken::create(
      id: CalendarFeedTokenId::fromString('018f0b68-6758-7a12-8a1d-3f0d97f64a11'),
      organizationId: self::ORGANIZATION_ID,
      userId: self::USER_ID,
      tokenHash: hash('sha256', 'old-secret'),
    );

    $repository = $this->createMock(CalendarFeedTokenRepositoryPort::class);
    $repository->method('findActiveByOrganizationAndUser')->willReturn($previous);
    $repository->expects(self::exactly(2))->method('save');

    $dispatched = [];
    $dispatcher = $this->createStub(EventDispatcherPort::class);
    $dispatcher->method('dispatch')->willReturnCallback(static function (object $event) use (&$dispatched): void {
      $dispatched[] = $event;
    });

    $handler = $this->handler($repository, null, $dispatcher);

    $result = $handler->__invoke(new RotateCalendarFeedTokenCommand(self::ORGANIZATION_ID, self::USER_ID));

    self::assertTrue($result->rotated);
    self::assertTrue($previous->isRevoked());

    self::assertCount(2, $dispatched);
    $revoked = $dispatched[0];
    self::assertInstanceOf(CalendarFeedTokenRevokedEvent::class, $revoked);
    self::assertSame('rotated', $revoked->reason);
    self::assertSame('018f0b68-6758-7a12-8a1d-3f0d97f64a11', $revoked->tokenId);
    $created = $dispatched[1];
    self::assertInstanceOf(CalendarFeedTokenCreatedEvent::class, $created);
    self::assertTrue($created->rotated);
  }

  /**
   * Method handler.
   *
   * @param CalendarFeedTokenRepositoryPort $repository the repository double
   * @param ?OrganizationAuthorizationPort $authorization the authorization double, or a permissive stub
   * @param ?EventDispatcherPort $dispatcher the dispatcher double, or a silent stub
   *
   * @return RotateCalendarFeedTokenHandler the handler under test
   */
  private function handler(
    CalendarFeedTokenRepositoryPort $repository,
    ?OrganizationAuthorizationPort $authorization = null,
    ?EventDispatcherPort $dispatcher = null,
  ): RotateCalendarFeedTokenHandler {
    $generator = $this->createStub(UuidGeneratorPort::class);
    $generator->method('generate')->willReturn(self::NEW_TOKEN_ID);

    return new RotateCalendarFeedTokenHandler(
      repository: $repository,
      authorization: $authorization ?? $this->createStub(OrganizationAuthorizationPort::class),
      secretFactory: new CalendarFeedTokenSecretFactory(),
      uuidFactory: new UuidFactory($generator),
      eventDispatcher: $dispatcher ?? $this->createStub(EventDispatcherPort::class),
    );
  }
}
