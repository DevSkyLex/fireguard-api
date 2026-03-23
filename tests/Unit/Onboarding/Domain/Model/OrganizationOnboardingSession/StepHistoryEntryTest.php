<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Domain\Model\OrganizationOnboardingSession;

use Onboarding\Domain\Model\OrganizationOnboardingSession\StepHistoryEntry;
use Onboarding\Domain\ValueObject\OrganizationOnboardingStep;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(StepHistoryEntry::class)]
final class StepHistoryEntryTest extends TestCase
{
  #[Test]
  public function testToArrayReturnsExpectedStructure(): void
  {
    $entry = new StepHistoryEntry(
      stepKey: OrganizationOnboardingStep::CREATE_ORGANIZATION,
      occurredAt: '2026-02-19T08:00:00+00:00',
      skipped: false,
    );

    $array = $entry->toArray();

    self::assertSame(OrganizationOnboardingStep::CREATE_ORGANIZATION, $array['stepKey']);
    self::assertSame('2026-02-19T08:00:00+00:00', $array['occurredAt']);
    self::assertFalse($array['skipped']);
  }

  #[Test]
  public function testToArrayWithSkippedTrue(): void
  {
    $entry = new StepHistoryEntry(
      stepKey: OrganizationOnboardingStep::INVITE_MEMBERS,
      occurredAt: '2026-02-20T09:00:00+00:00',
      skipped: true,
    );

    $array = $entry->toArray();

    self::assertSame(OrganizationOnboardingStep::INVITE_MEMBERS, $array['stepKey']);
    self::assertTrue($array['skipped']);
  }

  #[Test]
  public function testFromArrayReconstitutesAllFields(): void
  {
    $data = [
      'stepKey' => OrganizationOnboardingStep::INVITE_MEMBERS,
      'occurredAt' => '2026-02-19T12:00:00+00:00',
      'skipped' => true,
    ];

    $entry = StepHistoryEntry::fromArray($data);

    self::assertSame(OrganizationOnboardingStep::INVITE_MEMBERS, $entry->stepKey);
    self::assertSame('2026-02-19T12:00:00+00:00', $entry->occurredAt);
    self::assertTrue($entry->skipped);
  }

  #[Test]
  public function testFromArrayDefaultsToFalseForMissingSkipped(): void
  {
    $entry = StepHistoryEntry::fromArray([
      'stepKey' => OrganizationOnboardingStep::CREATE_ORGANIZATION,
      'occurredAt' => '2026-02-19T08:00:00+00:00',
    ]);

    self::assertFalse($entry->skipped);
  }

  #[Test]
  public function testFromArraySafelyHandlesMissingFields(): void
  {
    $entry = StepHistoryEntry::fromArray([]);

    self::assertSame('', $entry->stepKey);
    self::assertSame('', $entry->occurredAt);
    self::assertFalse($entry->skipped);
  }

  #[Test]
  public function testRoundTripToArrayFromArray(): void
  {
    $original = new StepHistoryEntry(
      stepKey: OrganizationOnboardingStep::CREATE_FIRST_FACILITY,
      occurredAt: '2026-03-01T15:30:00+00:00',
      skipped: false,
    );

    $restored = StepHistoryEntry::fromArray($original->toArray());

    self::assertSame($original->stepKey, $restored->stepKey);
    self::assertSame($original->occurredAt, $restored->occurredAt);
    self::assertSame($original->skipped, $restored->skipped);
  }
}
