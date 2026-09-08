<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\Service;

use DateTimeImmutable;
use Inspection\Application\Service\{NonConformitySlaNotifier, NonConformitySlaRecipientResolver};
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationNotificationPolicyPort};
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationNotificationSettings};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_keys;
use function array_map;

/**
 * Test NonConformitySlaNotifierTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(NonConformitySlaNotifier::class)]
final class NonConformitySlaNotifierTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string NC_ID = '550e8400-e29b-41d4-a716-446655440003';

  #[Test]
  public function testEscalateSendsABreachNotificationToEveryAdministrator(): void
  {
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::exactly(2))
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => 'non_conformity.sla_breached' === $request->type
        && [NotificationChannel::MERCURE, NotificationChannel::EMAIL] === $request->channels
        && self::NC_ID === $request->payload['nonConformityId']
        && self::INSPECTION_ID === $request->payload['inspectionId']
        && 'critical' === $request->payload['severity']
        && 7 === $request->payload['slaDays']
        && self::ORG_ID === $request->organizationId))
      ->willReturn($this->sent());

    $notifier = new NonConformitySlaNotifier($notifications, $this->policy(), $this->recipients(['user-1', 'user-2']));

    $notifier->escalate(self::ORG_ID, self::INSPECTION_ID, self::NC_ID, 'critical', 7, new DateTimeImmutable('2026-01-01'));
  }

  #[Test]
  public function testEscalateDoesNothingWhenTheSlaBreachedCategoryIsDisabled(): void
  {
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    $notifier = new NonConformitySlaNotifier(
      $notifications,
      $this->policy(nonConformitySlaBreached: false),
      $this->recipients(['user-1']),
    );

    $notifier->escalate(self::ORG_ID, self::INSPECTION_ID, self::NC_ID, 'high', 14, new DateTimeImmutable());
  }

  #[Test]
  public function testEscalateDropsTheEmailChannelWhenEmailDeliveryIsDisabled(): void
  {
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::once())
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => [NotificationChannel::MERCURE] === $request->channels))
      ->willReturn($this->sent());

    $notifier = new NonConformitySlaNotifier(
      $notifications,
      $this->policy(emailEnabled: false),
      $this->recipients(['user-1']),
    );

    $notifier->escalate(self::ORG_ID, self::INSPECTION_ID, self::NC_ID, 'high', 14, new DateTimeImmutable());
  }

  #[Test]
  public function testEscalateDoesNothingWhenNoChannelRemainsEnabled(): void
  {
    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    $notifier = new NonConformitySlaNotifier(
      $notifications,
      $this->policy(emailEnabled: false, inAppEnabled: false),
      $this->recipients(['user-1']),
    );

    $notifier->escalate(self::ORG_ID, self::INSPECTION_ID, self::NC_ID, 'low', 90, new DateTimeImmutable());
  }

  #[Test]
  public function testEscalateNeverThrowsWhenDeliveryFails(): void
  {
    $notifications = $this->createStub(NotificationPort::class);
    $notifications->method('send')->willThrowException(new RuntimeException('unavailable'));

    $notifier = new NonConformitySlaNotifier($notifications, $this->policy(), $this->recipients(['user-1']));

    $notifier->escalate(self::ORG_ID, self::INSPECTION_ID, self::NC_ID, 'medium', 30, new DateTimeImmutable());

    self::addToAssertionCount(1);
  }

  #[Test]
  public function testEscalateNeverThrowsWhenTheNotificationPolicyIsUnreadable(): void
  {
    $policy = $this->createStub(OrganizationNotificationPolicyPort::class);
    $policy->method('notificationPolicy')->willThrowException(new RuntimeException('policy store down'));

    $notifications = $this->createMock(NotificationPort::class);
    $notifications->expects(self::never())->method('send');

    $notifier = new NonConformitySlaNotifier($notifications, $policy, $this->recipients(['user-1']));

    $notifier->escalate(self::ORG_ID, self::INSPECTION_ID, self::NC_ID, 'medium', 30, new DateTimeImmutable());

    self::addToAssertionCount(1);
  }

  /**
   * Builds a real resolver (the concrete class is final and cannot be
   * doubled) backed by stubbed ports, so it deterministically resolves to
   * the given user ids as active organization administrators.
   *
   * @param list<string> $userIds
   */
  private function recipients(array $userIds): NonConformitySlaRecipientResolver
  {
    $members = array_map(
      static fn (int $index, string $userId): OrganizationMember => OrganizationMember::reconstitute(
        OrganizationMemberId::fromString('550e8400-e29b-41d4-a716-4466554400' . (20 + $index)),
        OrganizationId::fromString(self::ORG_ID),
        $userId,
        true,
        new DateTimeImmutable(),
      ),
      array_keys($userIds),
      $userIds,
    );

    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findByOrganizationId')->willReturn($members);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('getUserPermissions')->willReturn(['organization.inspection.write']);

    return new NonConformitySlaRecipientResolver($memberRepository, $authorization);
  }

  private function policy(bool $nonConformitySlaBreached = true, bool $inAppEnabled = true, bool $emailEnabled = true): OrganizationNotificationPolicyPort
  {
    $policy = $this->createStub(OrganizationNotificationPolicyPort::class);
    $policy->method('notificationPolicy')->willReturn(new OrganizationNotificationSettings(
      emailEnabled: $emailEnabled,
      inAppEnabled: $inAppEnabled,
      nonConformitySlaBreached: $nonConformitySlaBreached,
    ));

    return $policy;
  }

  private function sent(): SentNotification
  {
    return new SentNotification(
      '550e8400-e29b-41d4-a716-446655440099',
      'non_conformity.sla_breached',
      'Non-conformity resolution SLA breached',
      'body',
      [NotificationChannel::MERCURE->value],
      [],
      [NotificationChannel::MERCURE->value => true],
      new DateTimeImmutable(),
      'user-1',
      null,
    );
  }
}
