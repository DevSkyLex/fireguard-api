<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\Service;

use DateTimeImmutable;
use Intervention\Application\Service\{InterventionNotificationService, InterventionRecurrenceRecipientResolver, InterventionReviewerRecipientResolver};
use Notification\Application\Service\NotificationService;
use Notification\Application\UseCase\Command\Notification\SendNotification\SendNotificationHandler;
use Notification\Infrastructure\Adapter\Channel\EmailNotificationChannelAdapter;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationNotificationPolicyPort};
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationNotificationSettings};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{DurableEventContextPort, IdempotentConsumerPort};
use Tests\Support\Notification\EmailPipelineTestTrait;

#[CoversClass(InterventionNotificationService::class)]
#[CoversClass(NotificationService::class)]
#[CoversClass(SendNotificationHandler::class)]
#[CoversClass(EmailNotificationChannelAdapter::class)]
final class InterventionNotificationEmailTest extends TestCase
{
  use EmailPipelineTestTrait;

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440100';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655440101';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440102';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440103';

  private const string NAME = '<a href="https://untrusted.invalid">Review & "confirm"</a>';

  #[Test]
  #[DataProvider('notificationSources')]
  public function interventionMarkupIsTextInRenderedEmail(string $source, string $type, string $subject, string $plainBody): void
  {
    $member = OrganizationMember::reconstitute(
      id: new OrganizationMemberId(self::MEMBER_ID),
      organizationId: new OrganizationId(self::ORGANIZATION_ID),
      userId: self::USER_ID,
      isActive: true,
      joinedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
    $members = $this->createMock(OrganizationMemberRepositoryPort::class);
    if ('submitted' === $source) {
      $members->expects(self::never())->method('findById');
    } else {
      $members->expects(self::once())->method('findById')->with(new OrganizationMemberId(self::MEMBER_ID))->willReturn($member);
    }
    $resolvesOrganizationRecipients = 'dueSoon' !== $source;
    $members->expects($resolvesOrganizationRecipients ? self::once() : self::never())->method('findByOrganizationId')
      ->with(new OrganizationId(self::ORGANIZATION_ID))->willReturn([$member]);
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects($resolvesOrganizationRecipients ? self::once() : self::never())->method('getUserPermissions')
      ->with(self::USER_ID, self::ORGANIZATION_ID)->willReturn(['organization.interventions.review', 'organization.interventions.plan']);
    $policy = $this->createMock(OrganizationNotificationPolicyPort::class);
    $policy->expects(self::once())->method('notificationPolicy')->with(self::ORGANIZATION_ID)
      ->willReturn(OrganizationNotificationSettings::fromArray(['email_enabled' => true, 'in_app_enabled' => true]));
    $eventContext = $this->createStub(DurableEventContextPort::class);
    $eventContext->method('eventId')->willReturn(null);
    $consumer = $this->createMock(IdempotentConsumerPort::class);
    $consumer->expects(self::never())->method('consume');
    $service = new InterventionNotificationService(
      notifications: $this->emailPipeline(self::USER_ID, 'reviewer@example.com', 'intervention', $subject, resolveEmail: true),
      members: $members,
      policy: $policy,
      reviewers: new InterventionReviewerRecipientResolver($members, $authorization),
      admins: new InterventionRecurrenceRecipientResolver($members, $authorization),
      eventContext: $eventContext,
      eventConsumer: $consumer,
    );

    if ('submitted' === $source) {
      $service->submitted(self::INTERVENTION_ID, self::NAME, self::ORGANIZATION_ID, 'submitting-user');
    } else {
      $service->{$source}(
        self::INTERVENTION_ID,
        12,
        self::NAME,
        self::ORGANIZATION_ID,
        new DateTimeImmutable('2026-01-11T00:00:00+00:00'),
        [self::MEMBER_ID],
      );
    }

    $this->assertEmailPipelinePreservesPlainText($type, $plainBody, ['interventionId' => self::INTERVENTION_ID], self::USER_ID, self::ORGANIZATION_ID);
  }

  /**
   * @return iterable<string, array{string, string, string, string}>
   */
  public static function notificationSources(): iterable
  {
    yield 'submitted for review' => [
      'submitted',
      'intervention.submitted',
      'Intervention submitted for review',
      '"' . self::NAME . '" was submitted and awaits review.',
    ];
    yield 'due soon' => [
      'dueSoon',
      'intervention.due_soon',
      'Intervention due soon',
      '"' . self::NAME . '" (FG-12) is due 2026-01-11. /organizations/' . self::ORGANIZATION_ID . '/interventions/' . self::INTERVENTION_ID,
    ];
    yield 'overdue' => [
      'overdue',
      'intervention.overdue',
      'Intervention overdue',
      '"' . self::NAME . '" (FG-12) is due 2026-01-11. /organizations/' . self::ORGANIZATION_ID . '/interventions/' . self::INTERVENTION_ID,
    ];
  }
}
