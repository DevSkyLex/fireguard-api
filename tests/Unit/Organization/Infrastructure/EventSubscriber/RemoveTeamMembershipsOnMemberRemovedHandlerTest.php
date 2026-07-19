<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\EventSubscriber;

use Organization\Application\Port\Outbound\TeamRepositoryPort;
use Organization\Domain\Event\Member\OrganizationMemberRemovedEvent;
use Organization\Domain\ValueObject\OrganizationMemberId;
use Organization\Infrastructure\EventSubscriber\RemoveTeamMembershipsOnMemberRemovedHandler;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[CoversClass(RemoveTeamMembershipsOnMemberRemovedHandler::class)]
final class RemoveTeamMembershipsOnMemberRemovedHandlerTest extends TestCase
{
  #[Test]
  public function testImplementsEventSubscriberInterface(): void
  {
    self::assertInstanceOf(
      EventSubscriberInterface::class,
      new RemoveTeamMembershipsOnMemberRemovedHandler($this->createStub(TeamRepositoryPort::class)),
    );
  }

  #[Test]
  public function testGetSubscribedEventsMapsTheOrganizationMemberRemovedEvent(): void
  {
    self::assertSame(
      ['organization.organization_member_removed_event' => 'onMemberRemoved'],
      RemoveTeamMembershipsOnMemberRemovedHandler::getSubscribedEvents(),
    );
  }

  #[Test]
  public function testOnMemberRemovedDeletesTeamMembershipsForTheMember(): void
  {
    $memberId = '550e8400-e29b-41d4-a716-446655440402';

    /** @var TeamRepositoryPort&MockObject $teamRepository */
    $teamRepository = $this->createMock(TeamRepositoryPort::class);
    $teamRepository->expects(self::once())
      ->method('deleteMembershipsForMember')
      ->with(self::callback(
        static fn (OrganizationMemberId $id): bool => $memberId === (string) $id,
      ));

    $handler = new RemoveTeamMembershipsOnMemberRemovedHandler($teamRepository);

    $handler->onMemberRemoved(new OrganizationMemberRemovedEvent(
      organizationId: '550e8400-e29b-41d4-a716-446655440400',
      memberId: $memberId,
      userId: '550e8400-e29b-41d4-a716-446655440999',
    ));
  }
}
