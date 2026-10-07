<?php

declare(strict_types=1);

namespace Tests\Unit\ServiceRequest\Domain\Model\ServiceRequest;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use ServiceRequest\Domain\Exception\ServiceRequestException;
use ServiceRequest\Domain\Model\ServiceRequest\ServiceRequest;
use stdClass;

use function fclose;
use function fopen;
use function str_repeat;

use const INF;
use const NAN;

/**
 * Class ServiceRequestTest
 *
 * Covers immutable maintenance requests, lifecycle decisions and retained targets.
 *
 * @category Unit Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ServiceRequestTest extends TestCase
{
  private const string ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string SITE_ID = '550e8400-e29b-41d4-a716-446655440004';

  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655440005';

  private const string NON_CONFORMITY_ID = '550e8400-e29b-41d4-a716-446655440006';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440007';

  private const string TASK_ID = '550e8400-e29b-41d4-a716-446655440008';

  // #region Methods
  #[Test]
  public function createsANormalizedRequestWithStableTargetAndOrigin(): void
  {
    $now = self::now();
    $snapshot = ['equipment' => ['name' => 'Extinguisher', 'assetCode' => 'EXT-01'], 'site' => ['name' => 'Warehouse'], 'customer' => null];
    $request = ServiceRequest::create(self::ID, self::ORGANIZATION_ID, self::EQUIPMENT_ID, self::SITE_ID, $snapshot, '  Repair extinguisher  ', "  Pressure gauge damaged\nCheck the seal.  ", $now, originInspectionId: self::INSPECTION_ID, originNonConformityId: self::NON_CONFORMITY_ID);

    self::assertSame(self::ID, $request->id);
    self::assertSame(self::ORGANIZATION_ID, $request->organizationId);
    self::assertSame(self::EQUIPMENT_ID, $request->equipmentId);
    self::assertSame(self::SITE_ID, $request->siteId);
    self::assertSame($snapshot, $request->targetSnapshot);
    self::assertSame('Repair extinguisher', $request->title);
    self::assertSame("Pressure gauge damaged\nCheck the seal.", $request->description);
    self::assertSame('normal', $request->priority);
    self::assertSame('requested', $request->status);
    self::assertSame(1, $request->revision);
    self::assertSame($now, $request->requestedAt);
    self::assertSame($now, $request->updatedAt);
    self::assertSame(self::INSPECTION_ID, $request->originInspectionId);
    self::assertSame(self::NON_CONFORMITY_ID, $request->originNonConformityId);
    self::assertNull($request->qualificationNote);
    self::assertNull($request->decisionReason);
    self::assertNull($request->qualifiedAt);
    self::assertNull($request->rejectedAt);
    self::assertNull($request->cancelledAt);
    self::assertNull($request->convertedAt);
    self::assertNull($request->interventionId);
    self::assertNull($request->taskId);

    $snapshot['equipment']['name'] = 'Changed after creation';
    self::assertSame('Extinguisher', $request->targetSnapshot['equipment']['name']);
  }

  /**
   * @return iterable<string, array{?string, ?string}>
   */
  public static function validTargets(): iterable
  {
    yield 'equipment' => [self::EQUIPMENT_ID, null];
    yield 'site' => [null, self::SITE_ID];
    yield 'equipment and site' => [self::EQUIPMENT_ID, self::SITE_ID];
  }

  #[Test]
  #[DataProvider('validTargets')]
  public function acceptsAnEquipmentOrASiteTarget(?string $equipmentId, ?string $siteId): void
  {
    $request = ServiceRequest::create(self::ID, self::ORGANIZATION_ID, $equipmentId, $siteId, [], 'Repair', 'Repair needed', self::now());

    self::assertSame($equipmentId, $request->equipmentId);
    self::assertSame($siteId, $request->siteId);
    self::assertSame([], $request->targetSnapshot);
    self::assertNull($request->originInspectionId);
    self::assertNull($request->originNonConformityId);
  }

  #[Test]
  public function requiresAnEquipmentBeforeQualifyingASiteRequest(): void
  {
    $request = ServiceRequest::create(self::ID, self::ORGANIZATION_ID, null, self::SITE_ID, ['site' => ['label' => 'Warehouse']], 'Repair', 'Leak in the building', self::now());
    $this->expectException(ServiceRequestException::class);

    $request->qualify('Repair approved', self::now());
  }

  #[Test]
  public function assignsTheEquipmentOfASiteRequestBeforeQualification(): void
  {
    $request = ServiceRequest::create(self::ID, self::ORGANIZATION_ID, null, self::SITE_ID, ['site' => ['label' => 'Warehouse']], 'Repair', 'Leak in the building', self::now(), originInspectionId: self::INSPECTION_ID, originNonConformityId: self::NON_CONFORMITY_ID);
    $assignedAt = self::now()->modify('+1 hour');
    $snapshot = ['site' => ['label' => 'Warehouse'], 'equipment' => ['label' => 'Valve', 'rating' => 1.0]];
    $assigned = $request->assignEquipment(self::EQUIPMENT_ID, self::SITE_ID, $snapshot, $assignedAt);

    self::assertNull($request->equipmentId);
    self::assertSame(['site' => ['label' => 'Warehouse']], $request->targetSnapshot);
    self::assertSame(self::EQUIPMENT_ID, $assigned->equipmentId);
    self::assertSame(self::SITE_ID, $assigned->siteId);
    self::assertSame($snapshot, $assigned->targetSnapshot);
    self::assertSame('requested', $assigned->status);
    self::assertSame($assignedAt, $assigned->updatedAt);
    self::assertSame(2, $assigned->revision);
    self::assertSame($request->id, $assigned->id);
    self::assertSame($request->organizationId, $assigned->organizationId);
    self::assertSame($request->requestedAt, $assigned->requestedAt);
    self::assertSame($request->title, $assigned->title);
    self::assertSame($request->description, $assigned->description);
    self::assertSame($request->originInspectionId, $assigned->originInspectionId);
    self::assertSame($request->originNonConformityId, $assigned->originNonConformityId);

    $qualified = $assigned->qualify('Valve selected', $assignedAt->modify('+1 hour'));
    self::assertSame('qualified', $qualified->status);
    self::assertSame(3, $qualified->revision);
    self::assertRetainedTarget($assigned, $qualified);
  }

  /**
   * @return iterable<string, array{string, ?string}>
   */
  public static function invalidAssignments(): iterable
  {
    yield 'invalid equipment' => ['invalid', self::SITE_ID];
    yield 'invalid site' => [self::EQUIPMENT_ID, 'invalid'];
    yield 'different site' => [self::EQUIPMENT_ID, self::TASK_ID];
    yield 'missing original site' => [self::EQUIPMENT_ID, null];
  }

  #[Test]
  #[DataProvider('invalidAssignments')]
  public function refusesAssignmentsThatLoseOrChangeTheOriginalSite(string $equipmentId, ?string $siteId): void
  {
    $request = ServiceRequest::create(self::ID, self::ORGANIZATION_ID, null, self::SITE_ID, [], 'Repair', 'Leak in the building', self::now());
    $this->expectException(ServiceRequestException::class);

    $request->assignEquipment($equipmentId, $siteId, [], self::now());
  }

  /**
   * @return iterable<string, array{string}>
   */
  public static function assignedStates(): iterable
  {
    foreach (['requested', 'qualified', 'rejected', 'cancelled', 'converted'] as $state) {
      yield $state => [$state];
    }
  }

  #[Test]
  #[DataProvider('assignedStates')]
  public function refusesRetargetingAnExistingEquipment(string $state): void
  {
    $request = self::inState($state);
    $this->expectException(ServiceRequestException::class);

    $request->assignEquipment(self::TASK_ID, self::SITE_ID, [], self::now()->modify('+3 hours'));
  }

  #[Test]
  public function refusesAnEquipmentAssignmentBeforeTheLatestRevision(): void
  {
    $request = ServiceRequest::create(self::ID, self::ORGANIZATION_ID, null, self::SITE_ID, [], 'Repair', 'Leak in the building', self::now())->change(['priority' => 'high'], self::now()->modify('+1 hour'));
    $this->expectException(ServiceRequestException::class);

    $request->assignEquipment(self::EQUIPMENT_ID, self::SITE_ID, [], self::now());
  }

  /**
   * @return iterable<string, array{string, string, ?string, ?string, ?string, ?string}>
   */
  public static function invalidIdentifiers(): iterable
  {
    yield 'request' => ['invalid', self::ORGANIZATION_ID, self::EQUIPMENT_ID, self::SITE_ID, null, null];
    yield 'organization' => [self::ID, '', self::EQUIPMENT_ID, self::SITE_ID, null, null];
    yield 'equipment' => [self::ID, self::ORGANIZATION_ID, 'invalid', self::SITE_ID, null, null];
    yield 'site' => [self::ID, self::ORGANIZATION_ID, self::EQUIPMENT_ID, 'invalid', null, null];
    yield 'inspection origin' => [self::ID, self::ORGANIZATION_ID, self::EQUIPMENT_ID, self::SITE_ID, 'invalid', null];
    yield 'nonconformity origin' => [self::ID, self::ORGANIZATION_ID, self::EQUIPMENT_ID, self::SITE_ID, null, 'invalid'];
    yield 'missing target' => [self::ID, self::ORGANIZATION_ID, null, null, null, null];
  }

  #[Test]
  #[DataProvider('invalidIdentifiers')]
  public function refusesInvalidIdentifiersAndMissingTargets(string $id, string $organizationId, ?string $equipmentId, ?string $siteId, ?string $inspectionId, ?string $nonConformityId): void
  {
    $this->expectException(ServiceRequestException::class);

    ServiceRequest::create($id, $organizationId, $equipmentId, $siteId, [], 'Repair', 'Repair needed', self::now(), originInspectionId: $inspectionId, originNonConformityId: $nonConformityId);
  }

  /**
   * @return iterable<string, array{string}>
   */
  public static function priorities(): iterable
  {
    yield 'low' => ['low'];
    yield 'normal' => ['normal'];
    yield 'high' => ['high'];
    yield 'urgent' => ['urgent'];
  }

  #[Test]
  #[DataProvider('priorities')]
  public function acceptsEveryDeclaredPriority(string $priority): void
  {
    $request = ServiceRequest::create(self::ID, self::ORGANIZATION_ID, self::EQUIPMENT_ID, null, [], 'Repair', 'Repair needed', self::now(), $priority);

    self::assertSame($priority, $request->priority);
  }

  /**
   * @return iterable<string, array{string, string, string}>
   */
  public static function invalidContent(): iterable
  {
    yield 'empty title' => [' ', 'Repair needed', 'normal'];
    yield 'long title' => [str_repeat('é', 161), 'Repair needed', 'normal'];
    yield 'empty description' => ['Repair', ' ', 'normal'];
    yield 'long description' => ['Repair', str_repeat('é', 10001), 'normal'];
    yield 'unknown priority' => ['Repair', 'Repair needed', 'critical'];
    yield 'empty priority' => ['Repair', 'Repair needed', ''];
  }

  #[Test]
  #[DataProvider('invalidContent')]
  public function refusesInvalidTextAndPriority(string $title, string $description, string $priority): void
  {
    $this->expectException(ServiceRequestException::class);

    ServiceRequest::create(self::ID, self::ORGANIZATION_ID, self::EQUIPMENT_ID, null, [], $title, $description, self::now(), $priority);
  }

  #[Test]
  public function countsUnicodeCharactersAtTheAcceptedTextLimits(): void
  {
    $title = str_repeat('é', 160);
    $description = str_repeat('é', 10000);
    $request = ServiceRequest::create(self::ID, self::ORGANIZATION_ID, self::EQUIPMENT_ID, null, [], $title, $description, self::now());

    self::assertSame($title, $request->title);
    self::assertSame($description, $request->description);
    self::assertSame($description, $request->qualify($description, self::now())->qualificationNote);
  }

  /**
   * @return iterable<string, array{array<string, mixed>}>
   */
  public static function invalidSnapshots(): iterable
  {
    yield 'object' => [['target' => new stdClass()]];
    yield 'infinity' => [['measure' => INF]];
    yield 'not a number' => [['measure' => NAN]];
    yield 'invalid utf8' => [['label' => "\xB1\x31"]];
    yield 'oversized' => [['label' => str_repeat('a', 65537)]];
    $deep = ['label' => 'Extinguisher'];
    for ($depth = 0; $depth < 17; ++$depth) {
      $deep = ['target' => $deep];
    }
    yield 'too deep' => [$deep];
  }

  /**
   * @param array<string, mixed> $snapshot
   */
  #[Test]
  #[DataProvider('invalidSnapshots')]
  public function refusesSnapshotsThatCannotBeRetainedAsBoundedJson(array $snapshot): void
  {
    $this->expectException(ServiceRequestException::class);

    ServiceRequest::create(self::ID, self::ORGANIZATION_ID, self::EQUIPMENT_ID, null, $snapshot, 'Repair', 'Repair needed', self::now());
  }

  #[Test]
  public function refusesAResourceInsideTheSnapshot(): void
  {
    $stream = fopen('php://memory', 'r+');
    self::assertIsResource($stream);
    $this->expectException(ServiceRequestException::class);

    try {
      ServiceRequest::create(self::ID, self::ORGANIZATION_ID, self::EQUIPMENT_ID, null, ['stream' => $stream], 'Repair', 'Repair needed', self::now());
    } finally {
      fclose($stream);
    }
  }

  #[Test]
  public function isolatesMutableReferencesInsideTheRetainedSnapshot(): void
  {
    $label = 'Extinguisher';
    $snapshot = ['nested' => ['label' => &$label]];
    $request = ServiceRequest::create(self::ID, self::ORGANIZATION_ID, self::EQUIPMENT_ID, null, $snapshot, 'Repair', 'Repair needed', self::now());
    $label = 'Changed afterwards';

    self::assertIsArray($request->targetSnapshot['nested']);
    self::assertSame('Extinguisher', $request->targetSnapshot['nested']['label']);
  }

  #[Test]
  public function changesOnlyEditableFieldsWithoutMutatingTheOriginal(): void
  {
    $request = self::request();
    $changedAt = self::now()->modify('+1 hour');
    $changed = $request->change(['title' => '  Replace gauge  ', 'description' => '  Replacement required  ', 'priority' => 'urgent'], $changedAt);

    self::assertNotSame($request, $changed);
    self::assertSame('Repair', $request->title);
    self::assertSame('normal', $request->priority);
    self::assertSame(1, $request->revision);
    self::assertSame('Replace gauge', $changed->title);
    self::assertSame('Replacement required', $changed->description);
    self::assertSame('urgent', $changed->priority);
    self::assertSame('requested', $changed->status);
    self::assertSame(2, $changed->revision);
    self::assertSame($changedAt, $changed->updatedAt);
    self::assertRetainedTarget($request, $changed);
  }

  #[Test]
  public function preservesOmittedFieldsAndReturnsTheSameInstanceForNoOpChanges(): void
  {
    $request = self::request();
    self::assertSame($request, $request->change([], self::now()->modify('+1 hour')));
    self::assertSame($request, $request->change(['title' => ' Repair ', 'description' => ' Repair needed ', 'priority' => 'normal'], self::now()->modify('+1 hour')));
    $changed = $request->change(['priority' => 'high'], self::now()->modify('+1 hour'));

    self::assertSame($request->title, $changed->title);
    self::assertSame($request->description, $changed->description);
    self::assertSame('high', $changed->priority);
    self::assertSame(2, $changed->revision);
    self::assertEquals(self::now(), $request->updatedAt);
  }

  /**
   * @return iterable<string, array{array<string, mixed>}>
   */
  public static function invalidChanges(): iterable
  {
    yield 'null title' => [['title' => null]];
    yield 'numeric title' => [['title' => 42]];
    yield 'empty title' => [['title' => ' ']];
    yield 'long title' => [['title' => str_repeat('a', 161)]];
    yield 'null description' => [['description' => null]];
    yield 'array description' => [['description' => []]];
    yield 'empty description' => [['description' => ' ']];
    yield 'long description' => [['description' => str_repeat('a', 10001)]];
    yield 'null priority' => [['priority' => null]];
    yield 'unknown priority' => [['priority' => 'critical']];
    yield 'replace target' => [['equipmentId' => self::TASK_ID]];
    yield 'replace snapshot' => [['targetSnapshot' => []]];
    yield 'force status' => [['status' => 'converted']];
    yield 'force revision' => [['revision' => 10]];
    yield 'unknown field' => [['label' => 'Repair']];
  }

  /**
   * @param array<string, mixed> $changes
   */
  #[Test]
  #[DataProvider('invalidChanges')]
  public function refusesInvalidChangesAndMutationOfIdentityOrHistory(array $changes): void
  {
    $request = self::request();
    $this->expectException(ServiceRequestException::class);

    $request->change($changes, self::now());
  }

  /**
   * @return iterable<string, array{?string, ?string}>
   */
  public static function qualificationNotes(): iterable
  {
    yield 'absent' => [null, null];
    yield 'blank' => [' ', null];
    yield 'trimmed' => ['  Repair approved  ', 'Repair approved'];
  }

  #[Test]
  #[DataProvider('qualificationNotes')]
  public function qualifiesAndRetainsTheTargetWithoutMutatingTheRequestedState(?string $note, ?string $expectedNote): void
  {
    $request = self::request();
    $qualifiedAt = self::now()->modify('+1 hour');
    $qualified = $request->qualify($note, $qualifiedAt);

    self::assertSame('requested', $request->status);
    self::assertSame('qualified', $qualified->status);
    self::assertSame($expectedNote, $qualified->qualificationNote);
    self::assertSame($qualifiedAt, $qualified->qualifiedAt);
    self::assertSame($qualifiedAt, $qualified->updatedAt);
    self::assertSame(2, $qualified->revision);
    self::assertRetainedTarget($request, $qualified);

    self::assertSame($qualifiedAt, $qualified->qualifiedAt);
    self::assertSame($expectedNote, $qualified->qualificationNote);
  }

  #[Test]
  public function refusesAnOversizedQualificationNote(): void
  {
    $this->expectException(ServiceRequestException::class);

    self::request()->qualify(str_repeat('é', 10001), self::now());
  }

  /**
   * @return iterable<string, array{string, string}>
   */
  public static function decisions(): iterable
  {
    yield 'reject requested' => ['requested', 'reject'];
    yield 'reject qualified' => ['qualified', 'reject'];
    yield 'cancel requested' => ['requested', 'cancel'];
    yield 'cancel qualified' => ['qualified', 'cancel'];
  }

  #[Test]
  #[DataProvider('decisions')]
  public function recordsTheDecisionWithoutDiscardingItsHistory(string $state, string $operation): void
  {
    $request = self::inState($state);
    $decidedAt = self::now()->modify('+2 hours');
    $decided = 'reject' === $operation ? $request->reject('  Outside maintenance scope  ', $decidedAt) : $request->cancel('  Outside maintenance scope  ', $decidedAt);

    self::assertSame('reject' === $operation ? 'rejected' : 'cancelled', $decided->status);
    self::assertSame('Outside maintenance scope', $decided->decisionReason);
    self::assertSame($decidedAt, $decided->updatedAt);
    self::assertSame($request->revision + 1, $decided->revision);
    self::assertSame($request->qualificationNote, $decided->qualificationNote);
    self::assertSame($request->qualifiedAt, $decided->qualifiedAt);
    self::assertSame('reject' === $operation ? $decidedAt : null, $decided->rejectedAt);
    self::assertSame('cancel' === $operation ? $decidedAt : null, $decided->cancelledAt);
    self::assertNull($decided->convertedAt);
    self::assertNull($decided->interventionId);
    self::assertNull($decided->taskId);
    self::assertRetainedTarget($request, $decided);
  }

  /**
   * @return iterable<string, array{string, string}>
   */
  public static function invalidDecisionReasons(): iterable
  {
    yield 'blank rejection' => ['reject', ' '];
    yield 'long rejection' => ['reject', str_repeat('é', 2001)];
    yield 'blank cancellation' => ['cancel', ' '];
    yield 'long cancellation' => ['cancel', str_repeat('é', 2001)];
  }

  #[Test]
  #[DataProvider('invalidDecisionReasons')]
  public function requiresABoundedDecisionReason(string $operation, string $reason): void
  {
    $request = self::request();
    $this->expectException(ServiceRequestException::class);

    if ('reject' === $operation) {
      $request->reject($reason, self::now());
    } else {
      $request->cancel($reason, self::now());
    }
  }

  #[Test]
  public function convertsAQualifiedRequestWithExactReceiptReferences(): void
  {
    $request = self::inState('qualified');
    $convertedAt = self::now()->modify('+2 hours');
    $converted = $request->convert(self::INTERVENTION_ID, self::TASK_ID, $convertedAt);

    self::assertSame('qualified', $request->status);
    self::assertSame('converted', $converted->status);
    self::assertSame(self::INTERVENTION_ID, $converted->interventionId);
    self::assertSame(self::TASK_ID, $converted->taskId);
    self::assertSame($convertedAt, $converted->convertedAt);
    self::assertSame($convertedAt, $converted->updatedAt);
    self::assertSame(3, $converted->revision);
    self::assertSame($request->qualifiedAt, $converted->qualifiedAt);
    self::assertSame($request->qualificationNote, $converted->qualificationNote);
    self::assertNull($converted->decisionReason);
    self::assertNull($converted->rejectedAt);
    self::assertNull($converted->cancelledAt);
    self::assertRetainedTarget($request, $converted);
  }

  /**
   * @return iterable<string, array{string, string}>
   */
  public static function invalidConversionReferences(): iterable
  {
    yield 'intervention' => ['invalid', self::TASK_ID];
    yield 'task' => [self::INTERVENTION_ID, 'invalid'];
  }

  #[Test]
  #[DataProvider('invalidConversionReferences')]
  public function refusesInvalidConversionReceiptReferences(string $interventionId, string $taskId): void
  {
    $request = self::inState('qualified');
    $this->expectException(ServiceRequestException::class);

    $request->convert($interventionId, $taskId, self::now()->modify('+2 hours'));
  }

  /**
   * @return iterable<string, array{string, string}>
   */
  public static function forbiddenTransitions(): iterable
  {
    yield 'requested conversion' => ['requested', 'convert'];
    yield 'qualified qualification' => ['qualified', 'qualify'];
    yield 'qualified edit' => ['qualified', 'change'];
    foreach (['rejected', 'cancelled', 'converted'] as $state) {
      foreach (['change', 'qualify', 'reject', 'cancel', 'convert'] as $operation) {
        yield $state . ' ' . $operation => [$state, $operation];
      }
    }
  }

  #[Test]
  #[DataProvider('forbiddenTransitions')]
  public function refusesInvalidTransitionsIncludingEveryTerminalState(string $state, string $operation): void
  {
    $request = self::inState($state);

    try {
      self::act($request, $operation, self::now()->modify('+3 hours'));
      self::fail('The transition must preserve the request lifecycle.');
    } catch (ServiceRequestException $exception) {
      self::assertSame('service_request_transition_conflict', $exception->reason);
      self::assertSame($state, $request->status);
    }
  }

  /**
   * @return iterable<string, array{string, string}>
   */
  public static function datedMutations(): iterable
  {
    yield 'requested edit' => ['requested', 'change'];
    yield 'qualification' => ['requested', 'qualify'];
    yield 'requested rejection' => ['requested', 'reject'];
    yield 'requested cancellation' => ['requested', 'cancel'];
    yield 'qualified rejection' => ['qualified', 'reject'];
    yield 'qualified cancellation' => ['qualified', 'cancel'];
    yield 'conversion' => ['qualified', 'convert'];
  }

  #[Test]
  #[DataProvider('datedMutations')]
  public function refusesMutationDatesBeforeTheLatestRevision(string $state, string $operation): void
  {
    $request = self::inState($state);
    $this->expectException(ServiceRequestException::class);

    self::act($request, $operation, $request->updatedAt->modify('-1 second'));
  }

  #[Test]
  #[DataProvider('datedMutations')]
  public function acceptsMutationDatesEqualToTheLatestRevision(string $state, string $operation): void
  {
    $request = self::inState($state);
    $changed = self::act($request, $operation, $request->updatedAt);

    self::assertSame($request->updatedAt, $changed->updatedAt);
    self::assertSame($request->revision + 1, $changed->revision);
    self::assertRetainedTarget($request, $changed);
  }

  private static function now(): DateTimeImmutable
  {
    return new DateTimeImmutable('2026-10-06T12:00:00Z');
  }

  private static function request(): ServiceRequest
  {
    return ServiceRequest::create(self::ID, self::ORGANIZATION_ID, self::EQUIPMENT_ID, self::SITE_ID, ['equipment' => ['label' => 'Extinguisher'], 'site' => ['label' => 'Warehouse']], 'Repair', 'Repair needed', self::now(), originInspectionId: self::INSPECTION_ID, originNonConformityId: self::NON_CONFORMITY_ID);
  }

  private static function inState(string $state): ServiceRequest
  {
    $request = self::request();

    return match ($state) {
      'qualified' => $request->qualify('Repair approved', self::now()->modify('+1 hour')),
      'rejected' => $request->reject('Outside scope', self::now()->modify('+1 hour')),
      'cancelled' => $request->cancel('Duplicate request', self::now()->modify('+1 hour')),
      'converted' => $request->qualify('Repair approved', self::now()->modify('+1 hour'))->convert(self::INTERVENTION_ID, self::TASK_ID, self::now()->modify('+2 hours')),
      default => $request,
    };
  }

  private static function act(ServiceRequest $request, string $operation, DateTimeImmutable $now): ServiceRequest
  {
    return match ($operation) {
      'change' => $request->change(['title' => 'New title'], $now),
      'qualify' => $request->qualify('Repair approved', $now),
      'reject' => $request->reject('Outside scope', $now),
      'cancel' => $request->cancel('Duplicate request', $now),
      'convert' => $request->convert(self::INTERVENTION_ID, self::TASK_ID, $now),
      default => self::fail('Unsupported test operation.'),
    };
  }

  private static function assertRetainedTarget(ServiceRequest $original, ServiceRequest $changed): void
  {
    self::assertSame($original->id, $changed->id);
    self::assertSame($original->organizationId, $changed->organizationId);
    self::assertSame($original->equipmentId, $changed->equipmentId);
    self::assertSame($original->siteId, $changed->siteId);
    self::assertSame($original->targetSnapshot, $changed->targetSnapshot);
    self::assertSame($original->originInspectionId, $changed->originInspectionId);
    self::assertSame($original->originNonConformityId, $changed->originNonConformityId);
    self::assertSame($original->requestedAt, $changed->requestedAt);
  }
  // #endregion
}
