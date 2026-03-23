<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use Onboarding\Domain\Model\OrganizationOnboardingSession\{OrganizationOnboardingSession, StepHistoryEntry};
use Onboarding\Domain\Model\OrganizationOnboardingSession\RollbackAction\DeleteOrganizationRollbackAction;
use Onboarding\Domain\ValueObject\{OrganizationOnboardingState, OrganizationOnboardingStep};
use Onboarding\Infrastructure\Persistence\Doctrine\Mapper\OrganizationOnboardingSessionMapper;
use Onboarding\Infrastructure\Persistence\Doctrine\Record\OrganizationOnboardingSessionRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function count;

#[CoversClass(OrganizationOnboardingSessionMapper::class)]
final class OrganizationOnboardingSessionMapperTest extends TestCase
{
  #[Test]
  public function testToRecordMapsAllFields(): void
  {
    $createdAt = new DateTimeImmutable('2026-02-19T08:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-02-19T09:00:00+00:00');
    $rollback = new DeleteOrganizationRollbackAction('org-mapper-001');

    $session = OrganizationOnboardingSession::reconstitute(
      id: '550e8400-e29b-41d4-a716-660000000001',
      userId: '550e8400-e29b-41d4-a716-660000000002',
      flow: 'organization',
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: OrganizationOnboardingStep::INVITE_MEMBERS,
      blockedReason: null,
      targetOrganizationId: 'org-mapper-001',
      targetOrganizationName: 'Mapper Org',
      completedSteps: [OrganizationOnboardingStep::CREATE_ORGANIZATION],
      skippedSteps: [],
      rollbackStack: [$rollback],
      stepHistory: [
        new StepHistoryEntry(
          stepKey: OrganizationOnboardingStep::CREATE_ORGANIZATION,
          occurredAt: '2026-02-19T08:00:00+00:00',
          skipped: false,
        ),
      ],
      createdAt: $createdAt,
      updatedAt: $updatedAt,
    );

    $record = OrganizationOnboardingSessionMapper::toRecord($session);

    self::assertSame('550e8400-e29b-41d4-a716-660000000001', $record->id);
    self::assertSame('550e8400-e29b-41d4-a716-660000000002', $record->userId);
    self::assertSame('organization', $record->flow);
    self::assertSame(OrganizationOnboardingState::IN_PROGRESS, $record->state);
    self::assertSame(OrganizationOnboardingStep::INVITE_MEMBERS, $record->nextStep);
    self::assertNull($record->blockedReason);
    self::assertSame('org-mapper-001', $record->targetOrganizationId);
    self::assertSame('Mapper Org', $record->targetOrganizationName);
    self::assertSame([OrganizationOnboardingStep::CREATE_ORGANIZATION], $record->completedSteps);
    self::assertSame([], $record->skippedSteps);
    self::assertCount(1, $record->rollbackStack);
    self::assertSame('org-mapper-001', $record->rollbackStack[0]['organizationId']);
    self::assertSame(DeleteOrganizationRollbackAction::ACTION_TYPE, $record->rollbackStack[0]['action']);
    self::assertCount(1, $record->stepHistory);
    self::assertSame(OrganizationOnboardingStep::CREATE_ORGANIZATION, $record->stepHistory[0]['stepKey']);
    self::assertFalse($record->stepHistory[0]['skipped']);
    self::assertSame($createdAt, $record->createdAt);
    self::assertSame($updatedAt, $record->updatedAt);
  }

  #[Test]
  public function testToDomainMapsAllFields(): void
  {
    $createdAt = new DateTimeImmutable('2026-02-19T08:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-02-19T09:00:00+00:00');

    $record = new OrganizationOnboardingSessionRecord();
    $record->id = '550e8400-e29b-41d4-a716-660000000010';
    $record->userId = '550e8400-e29b-41d4-a716-660000000011';
    $record->flow = 'organization';
    $record->state = OrganizationOnboardingState::IN_PROGRESS;
    $record->nextStep = OrganizationOnboardingStep::INVITE_MEMBERS;
    $record->blockedReason = null;
    $record->targetOrganizationId = 'org-domain-001';
    $record->targetOrganizationName = 'Domain Org';
    $record->completedSteps = [
      OrganizationOnboardingStep::CREATE_ORGANIZATION,
      OrganizationOnboardingStep::INVITE_MEMBERS,
    ];
    $record->skippedSteps = [];
    $record->rollbackStack = [
      [
        'step' => OrganizationOnboardingStep::CREATE_ORGANIZATION,
        'action' => DeleteOrganizationRollbackAction::ACTION_TYPE,
        'organizationId' => 'org-domain-001',
      ],
    ];
    $record->stepHistory = [
      [
        'stepKey' => OrganizationOnboardingStep::CREATE_ORGANIZATION,
        'occurredAt' => '2026-02-19T08:00:00+00:00',
        'skipped' => false,
      ],
    ];
    $record->createdAt = $createdAt;
    $record->updatedAt = $updatedAt;

    $session = OrganizationOnboardingSessionMapper::toDomain($record);

    self::assertSame('550e8400-e29b-41d4-a716-660000000010', $session->id());
    self::assertSame('550e8400-e29b-41d4-a716-660000000011', $session->userId());
    self::assertSame(OrganizationOnboardingState::IN_PROGRESS, $session->state());
    self::assertSame(OrganizationOnboardingStep::INVITE_MEMBERS, $session->nextStep());
    self::assertSame('org-domain-001', $session->targetOrganizationId());
    self::assertSame('Domain Org', $session->targetOrganizationName());
    self::assertSame([
      OrganizationOnboardingStep::CREATE_ORGANIZATION,
      OrganizationOnboardingStep::INVITE_MEMBERS,
    ], $session->completedSteps());
    self::assertInstanceOf(DeleteOrganizationRollbackAction::class, $session->rollbackStack()[0]);
    self::assertSame('org-domain-001', $session->rollbackStack()[0]->organizationId);
    self::assertCount(1, $session->stepHistory());
    self::assertSame(OrganizationOnboardingStep::CREATE_ORGANIZATION, $session->stepHistory()[0]->stepKey);
  }

  #[Test]
  public function testRoundTripDomainToRecordToDomain(): void
  {
    $session = OrganizationOnboardingSession::reconstitute(
      id: '550e8400-e29b-41d4-a716-660000000020',
      userId: '550e8400-e29b-41d4-a716-660000000021',
      flow: 'organization',
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: OrganizationOnboardingStep::CREATE_FIRST_FACILITY,
      blockedReason: null,
      targetOrganizationId: 'org-roundtrip-002',
      targetOrganizationName: 'Round Trip Org',
      completedSteps: [
        OrganizationOnboardingStep::CREATE_ORGANIZATION,
        OrganizationOnboardingStep::INVITE_MEMBERS,
      ],
      skippedSteps: [],
      rollbackStack: [new DeleteOrganizationRollbackAction('org-roundtrip-002')],
      stepHistory: [],
      createdAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-02-19T10:00:00+00:00'),
    );

    $record = OrganizationOnboardingSessionMapper::toRecord($session);
    $restored = OrganizationOnboardingSessionMapper::toDomain($record);

    self::assertSame($session->id(), $restored->id());
    self::assertSame($session->userId(), $restored->userId());
    self::assertSame($session->state(), $restored->state());
    self::assertSame($session->nextStep(), $restored->nextStep());
    self::assertSame($session->targetOrganizationId(), $restored->targetOrganizationId());
    self::assertSame($session->completedSteps(), $restored->completedSteps());
    self::assertCount(count($session->rollbackStack()), $restored->rollbackStack());
  }

  #[Test]
  public function testToRecordHandlesEmptyCollections(): void
  {
    $session = OrganizationOnboardingSession::start(
      id: '550e8400-e29b-41d4-a716-660000000030',
      userId: '550e8400-e29b-41d4-a716-660000000031',
    );

    $record = OrganizationOnboardingSessionMapper::toRecord($session);

    self::assertSame([], $record->completedSteps);
    self::assertSame([], $record->skippedSteps);
    self::assertSame([], $record->rollbackStack);
    self::assertSame([], $record->stepHistory);
    self::assertNull($record->targetOrganizationId);
    self::assertNull($record->targetOrganizationName);
  }
}
