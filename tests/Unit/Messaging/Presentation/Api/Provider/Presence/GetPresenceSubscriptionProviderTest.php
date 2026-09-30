<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Provider\Presence;

use ApiPlatform\Metadata\Get;
use Messaging\Application\UseCase\Query\Presence\GetPresenceSubscription\{GetPresenceSubscriptionQuery, GetPresenceSubscriptionResult};
use Messaging\Presentation\Api\Provider\Presence\GetPresenceSubscriptionProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

/**
 * Test GetPresenceSubscriptionProviderTest.
 *
 * @category Test
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GetPresenceSubscriptionProviderTest extends TestCase
{
  #[Test]
  /**
   * @since 1.0.0
   */
  public function translatesOrganizationIriAndCurrentActor(): void
  {
    $id = '550e8400-e29b-41d4-a716-446655440500';
    $actor = $this->createStub(CurrentActorPort::class);
    $actor->method('userId')->willReturn('user');
    $requests = new RequestStack();
    $requests->push(new Request(['organization' => '/api/organizations/' . $id]));
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::once())->method('ask')->with(self::callback(static fn (GetPresenceSubscriptionQuery $query): bool => 'user' === $query->userId && $id === $query->organizationId))->willReturn(new GetPresenceSubscriptionResult('/organizations/' . $id . '/presence', 'token', 'expires'));
    $output = new GetPresenceSubscriptionProvider($bus, $actor, $requests)->provide(new Get());
    self::assertSame('token', $output->token);
    self::assertSame('/organizations/' . $id . '/presence', $output->topic);
    self::assertSame('expires', $output->expiresAt);
  }

  #[Test]
  /**
   * @since 1.0.0
   */
  public function rejectsMissingOrganization(): void
  {
    $actor = $this->createStub(CurrentActorPort::class);
    $actor->method('userId')->willReturn('user');
    $this->expectException(BadRequestHttpException::class);
    new GetPresenceSubscriptionProvider($this->createStub(QueryBusPort::class), $actor, new RequestStack())->provide(new Get());
  }

  #[Test]
  /**
   * @since 1.0.0
   */
  public function rejectsAnonymous(): void
  {
    $this->expectException(AccessDeniedHttpException::class);
    new GetPresenceSubscriptionProvider($this->createStub(QueryBusPort::class), $this->createStub(CurrentActorPort::class), new RequestStack())->provide(new Get());
  }
}
