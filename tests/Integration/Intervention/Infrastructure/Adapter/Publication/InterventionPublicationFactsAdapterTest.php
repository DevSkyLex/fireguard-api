<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\Adapter\Publication;

use Customer\Infrastructure\Persistence\Doctrine\Record\CustomerRecord;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Intervention\Application\Contract\Publication\InterventionFactsScopeTooLarge;
use Intervention\Application\Port\Inbound\InterventionPublicationFactsPort;
use Intervention\Infrastructure\Adapter\Publication\InterventionPublicationFactsAdapter;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionRecord, InterventionWorkItemRecord, PublicationRecord};
use InvalidArgumentException;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;
use function json_encode;
use function range;
use function serialize;
use function sprintf;
use function str_repeat;

use const JSON_THROW_ON_ERROR;

/**
 * Test InterventionPublicationFactsAdapterTest.
 *
 * Exercises published dossier reads against PostgreSQL with independent live and frozen identities.
 *
 * @category Adapter Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionPublicationFactsAdapter::class)]
final class InterventionPublicationFactsAdapterTest extends KernelTestCase
{
  private EntityManagerInterface $entityManager;

  private OrganizationRecord $organization;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $manager */
    $manager = self::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $manager;
    $this->organization = $this->organization(1);
    $this->entityManager->flush();
  }

  public function testEconomicDatesUseCompletedPublicationWithInclusiveStartAndExclusiveEnd(): void
  {
    $start = $this->intervention(100, $this->snapshot(100));
    $end = $this->intervention(101, $this->snapshot(101));
    $before = $this->intervention(102, $this->snapshot(102));
    $newest = $this->intervention(103, $this->snapshot(103));
    $this->publication(200, $start, '2026-10-01T00:00:00Z');
    $this->publication(201, $end, '2026-11-01T00:00:00Z');
    $this->publication(202, $before, '2026-09-30T23:59:59Z');
    $this->publication(203, $newest, '2026-09-01T00:00:00Z');
    $this->publication(204, $newest, '2026-10-31T23:59:59Z', revision: 4);
    $planned = $this->intervention(104, null, 'planned');
    $planned->plannedStartAt = new DateTimeImmutable('2026-10-10T10:00:00Z');
    $created = $this->intervention(105, null, 'draft');
    $created->plannedStartAt = null;
    $created->createdAt = new DateTimeImmutable('2026-10-11T10:00:00Z');
    $impostor = $this->intervention(106, null, 'draft');
    $impostor->createdAt = new DateTimeImmutable('2026-10-12T10:00:00Z');
    $impostor->plannedStartAt = new DateTimeImmutable('2026-12-01T10:00:00Z');
    $this->entityManager->flush();
    $from = new DateTimeImmutable('2026-10-01T00:00:00Z');
    $to = new DateTimeImmutable('2026-11-01T00:00:00Z');
    $result = $this->port()->economicWindow($this->organization->id, $from, $to);
    self::assertSame(4, $result->totalItems);
    self::assertEqualsCanonicalizing([$start->id, $newest->id, $planned->id, $created->id], array_map(static fn ($context): string => $context->id, $result->items));
    self::assertSame(self::id(204), $this->port()->published($this->organization->id, $newest->id)?->publicationId);
    $page = $this->port()->economicPage($this->organization->id, from: $from, to: $to);
    self::assertSame(4, $page->totalItems);
    self::assertSame(serialize($result->items), serialize($page->items));
  }

  public function testEconomicWindowKeepsExactTotalWhenItsSourceLimitIsReached(): void
  {
    for ($offset = 0; $offset < 5; ++$offset) {
      $record = $this->intervention(100 + $offset, $this->snapshot(100 + $offset));
      $this->publication(200 + $offset, $record, '2026-10-01T10:00:00Z');
    }
    $foreign = $this->organization(2);
    $other = $this->intervention(110, $this->snapshot(110), organization: $foreign);
    $this->publication(210, $other, '2026-10-01T10:00:00Z');
    $this->entityManager->flush();
    $result = $this->port()->economicWindow($this->organization->id, new DateTimeImmutable('2026-10-01T00:00:00Z'), new DateTimeImmutable('2026-11-01T00:00:00Z'), limit: 2);
    self::assertSame(5, $result->totalItems);
    self::assertCount(2, $result->items);
    self::assertSame([self::id(100), self::id(101)], array_map(static fn ($context): string => $context->id, $result->items));
  }

  public function testFinancialIdentityUnionPreservesScopedExactCountsAndPagesBeforeSearchAndDates(): void
  {
    $first = $this->intervention(100, null, 'draft');
    $second = $this->intervention(101, null, 'draft');
    $first->name = $second->name = 'Direct financial asset';
    $first->plannedStartAt = $second->plannedStartAt = null;
    $first->createdAt = new DateTimeImmutable('2026-10-07T12:00:00Z');
    $second->createdAt = new DateTimeImmutable('2026-10-06T12:00:00Z');
    $foreign = $this->organization(2);
    $other = $this->intervention(102, null, 'draft', $foreign);
    $this->entityManager->flush();
    $ids = [$first->id, $second->id, $first->id, $other->id];
    $port = $this->port();
    foreach ([1 => $first->id, 2 => $second->id] as $page => $expected) {
      $result = $port->economicPage($this->organization->id, page: $page, itemsPerPage: 1, search: 'financial', from: new DateTimeImmutable('2026-10-01T00:00:00Z'), to: new DateTimeImmutable('2026-11-01T00:00:00Z'), equipmentId: self::id(999), financialInterventionIds: $ids);
      self::assertSame(2, $result->totalItems);
      self::assertCount(1, $result->items);
      self::assertSame($expected, $result->items[0]->id);
    }
    $emptyPage = $port->economicPage($this->organization->id, page: 3, itemsPerPage: 1, equipmentId: self::id(999), financialInterventionIds: $ids);
    self::assertSame(2, $emptyPage->totalItems);
    self::assertSame([], $emptyPage->items);
    self::assertSame(0, $port->economicPage($this->organization->id, search: 'absent', equipmentId: self::id(999), financialInterventionIds: $ids)->totalItems);
    self::assertSame(0, $port->economicPage($this->organization->id, from: new DateTimeImmutable('2026-09-01T00:00:00Z'), to: new DateTimeImmutable('2026-10-01T00:00:00Z'), equipmentId: self::id(999), financialInterventionIds: $ids)->totalItems);
  }

  public function testFinancialIdentityUnionRefusesOversizedInput(): void
  {
    $this->expectException(InterventionFactsScopeTooLarge::class);
    $this->expectExceptionMessage('exceeds 10000');
    $this->port()->economicPage($this->organization->id, financialInterventionIds: array_map(self::id(...), range(1, 10001)));
  }

  public function testCapturedPublicationIdentityAndOffsetDateTakePriorityOverLaterCompletionRows(): void
  {
    $snapshot = $this->snapshot(100);
    $snapshot['publicationId'] = self::id(200);
    $snapshot['publishedAt'] = '2026-10-01T00:30:00+02:00';
    $record = $this->intervention(100, $snapshot);
    $this->publication(200, $record, '2026-09-30T22:30:00Z');
    $this->publication(201, $record, '2026-10-20T10:00:00Z', revision: 4);
    $this->entityManager->flush();
    $port = $this->port();
    $facts = $port->published($this->organization->id, $record->id);
    self::assertNotNull($facts);
    self::assertSame(self::id(200), $facts->publicationId);
    self::assertSame('2026-10-01T00:30:00+02:00', $facts->publishedAt?->format('c'));
    $september = $port->economicWindow($this->organization->id, new DateTimeImmutable('2026-09-30T00:00:00Z'), new DateTimeImmutable('2026-10-01T00:00:00Z'));
    self::assertSame(1, $september->totalItems);
    self::assertSame($record->id, $september->items[0]->id);
    $october = $port->economicWindow($this->organization->id, new DateTimeImmutable('2026-10-01T00:00:00Z'), new DateTimeImmutable('2026-11-01T00:00:00Z'));
    self::assertSame(0, $october->totalItems);
    self::assertSame([], $october->items);
  }

  public function testLiteralSearchDoesNotInterpretPercentUnderscoreOrEscapeCharacters(): void
  {
    $snapshot = $this->snapshot(100);
    $snapshot['name'] = 'Literal 100%_alarm!';
    $captured = $this->intervention(100, $snapshot);
    $decoy = $this->intervention(101, $this->snapshot(101));
    $decoy->name = 'Literal 100AAalarm!';
    $live = $this->intervention(102, null, 'in_progress');
    $live->name = 'Another %_ live alarm!';
    $this->entityManager->flush();
    $published = $this->port()->publishedPage($this->organization->id, search: '%_');
    self::assertSame(1, $published->totalItems);
    self::assertSame($captured->id, $published->items[0]->id);
    $economic = $this->port()->economicPage($this->organization->id, search: '%_');
    self::assertSame(2, $economic->totalItems);
    self::assertEqualsCanonicalizing([$captured->id, $live->id], array_map(static fn ($context): string => $context->id, $economic->items));
    self::assertSame(1, $this->port()->publishedPage($this->organization->id, search: 'alarm!')->totalItems);
  }

  public function testAccentedSearchAcceptsOneHundredAndSixtyCharacters(): void
  {
    $search = str_repeat('é', 160);
    $snapshot = $this->snapshot(100);
    $snapshot['name'] = $search;
    $captured = $this->intervention(100, $snapshot);
    $this->entityManager->flush();

    $published = $this->port()->publishedPage($this->organization->id, search: $search);
    $economic = $this->port()->economicPage($this->organization->id, search: $search);
    self::assertSame(1, $published->totalItems);
    self::assertSame($captured->id, $published->items[0]->id);
    self::assertSame(1, $economic->totalItems);
    self::assertSame($captured->id, $economic->items[0]->id);
  }

  public function testPublishedBatchRejectsMoreThanOneHundredUniqueSources(): void
  {
    $this->expectException(InterventionFactsScopeTooLarge::class);
    $this->port()->publishedBatch($this->organization->id, array_map(self::id(...), range(100, 200)));
  }

  public function testCombinedSourcePageRefusesAnOversizedTaskPayloadInsteadOfTruncatingIt(): void
  {
    for ($offset = 0; $offset < 3; ++$offset) {
      $workItems = array_map(static fn (int $number): array => ['id' => self::id($number + 7000 * $offset), 'action' => 'repair', 'status' => 'completed'], range(10000, 16999));
      $record = $this->intervention(100 + $offset, $this->snapshot(100 + $offset, $workItems));
      $this->publication(200 + $offset, $record, '2026-10-01T10:00:00Z');
    }
    $this->entityManager->flush();
    $this->expectException(InterventionFactsScopeTooLarge::class);
    $this->port()->economicWindow($this->organization->id, new DateTimeImmutable('2026-10-01T00:00:00Z'), new DateTimeImmutable('2026-11-01T00:00:00Z'), limit: 3);
  }

  public function testPublicationBatchRequiresNarrowingWhileAnIndividualDossierRemainsReadable(): void
  {
    $ids = [];
    for ($offset = 0; $offset < 2; ++$offset) {
      $workItems = array_map(static fn (int $number): array => ['id' => self::id($number + 5001 * $offset), 'action' => 'repair', 'status' => 'completed'], range(10000, 15000));
      $record = $this->intervention(100 + $offset, $this->snapshot(100 + $offset, $workItems));
      $this->publication(200 + $offset, $record, '2026-10-01T10:00:00Z');
      $ids[] = $record->id;
    }
    $this->entityManager->flush();
    $port = $this->port();
    $page = $port->publishedPage($this->organization->id, itemsPerPage: 1);
    self::assertSame(2, $page->totalItems);
    self::assertCount(5001, $page->items[0]->workItems);
    $this->expectException(InterventionFactsScopeTooLarge::class);
    $port->publishedBatch($this->organization->id, $ids);
  }

  #[DataProvider('invalidPagination')]
  public function testSourcePagesRejectInvalidOrUnboundedPagination(int $page, int $size): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->port()->publishedPage($this->organization->id, $page, $size);
  }

  /**
   * Defines invalid source pagination without creating database-size fixtures.
   *
   * @since 1.0.0
   *
   * @return iterable<string,array{int,int}>
   */
  public static function invalidPagination(): iterable
  {
    yield 'zero page' => [0, 50];
    yield 'zero size' => [1, 0];
    yield 'over maximum' => [1, 101];
    yield 'unbounded offset' => [1000001, 50];
  }

  public function testAllocationFiltersUseEachCapturedTaskSiteCustomerAndExactEquipmentTarget(): void
  {
    $first = $this->snapshotWork(400, self::id(302), self::id(301), self::id(300));
    $second = $this->snapshotWork(401, self::id(312), self::id(311), self::id(310));
    $second['target'] = json_encode(['equipmentId' => self::id(312), 'notes' => self::id(302)], JSON_THROW_ON_ERROR);
    $record = $this->intervention(100, $this->snapshot(100, [$first, $second], self::id(321), self::id(320)));
    $decoy = $this->snapshotWork(402, self::id(332), self::id(331), self::id(330));
    $decoy['target'] = json_encode(['equipmentId' => self::id(332), 'notes' => self::id(312)], JSON_THROW_ON_ERROR);
    $other = $this->intervention(101, $this->snapshot(101, [$decoy], self::id(331), self::id(330)));
    $this->publication(200, $record, '2026-10-01T10:00:00Z');
    $this->publication(201, $other, '2026-10-01T10:00:00Z');
    $this->entityManager->flush();
    $port = $this->port();
    foreach ([$port->economicPage($this->organization->id, siteId: self::id(311)), $port->economicPage($this->organization->id, customerId: self::id(310)), $port->economicPage($this->organization->id, equipmentId: self::id(312)), $port->economicPage($this->organization->id, siteId: self::id(321)), $port->economicPage($this->organization->id, customerId: self::id(320))] as $result) {
      self::assertSame(1, $result->totalItems);
      self::assertSame($record->id, $result->items[0]->id);
    }
    self::assertSame(0, $port->economicPage($this->organization->id, siteId: self::id(311), customerId: self::id(330), equipmentId: self::id(312))->totalItems);
    $context = $port->economicContext($this->organization->id, $record->id);
    self::assertNotNull($context);
    self::assertSame([self::id(302), self::id(312)], array_map(static fn ($work): ?string => $work->equipmentId, $context->workItems));
    self::assertSame(['id' => self::id(311), 'name' => 'Original site'], $context->workItems[1]->site);
    self::assertSame(['id' => self::id(310), 'name' => 'Original customer'], $context->workItems[1]->customer);
  }

  public function testLiveAllocationSupportsHistoricalJsonAndDoesNotResolveForeignEquipment(): void
  {
    [$site, $customer, $equipment] = $this->identities(300);
    $foreign = $this->organization(2);
    [, , $foreignEquipment] = $this->identities(310, $foreign);
    $record = $this->intervention(100, null, 'in_progress');
    $record->siteId = $site->id;
    $first = $this->workItem(400, $record, '/api/equipment/' . $equipment->id);
    $second = $this->workItem(401, $record, json_encode(['equipmentId' => $equipment->id], JSON_THROW_ON_ERROR));
    $third = $this->workItem(402, $record, '/api/equipment/' . $foreignEquipment->id);
    $fourth = $this->workItem(403, $record, json_encode(['notes' => $equipment->id], JSON_THROW_ON_ERROR));
    $this->entityManager->flush();
    $context = $this->port()->economicContext($this->organization->id, $record->id);
    self::assertNotNull($context);
    self::assertSame('live', $context->snapshotState);
    self::assertSame(['id' => $site->id, 'name' => $site->name], $context->site);
    self::assertSame(['id' => $customer->id, 'name' => $customer->name], $context->customer);
    self::assertCount(4, $context->workItems);
    self::assertSame([$first->id, $second->id, $third->id, $fourth->id], array_map(static fn ($work): string => $work->id, $context->workItems));
    self::assertSame($equipment->id, $context->workItems[0]->equipmentIdentity?->id);
    self::assertSame($equipment->id, $context->workItems[1]->equipmentIdentity?->id);
    self::assertNull($context->workItems[2]->equipmentIdentity);
    self::assertNull($context->workItems[2]->site);
    self::assertNull($context->workItems[2]->customer);
    self::assertNull($context->workItems[3]->equipmentId);
    self::assertFalse($context->identityComplete);
    $result = $this->port()->economicPage($this->organization->id, siteId: $site->id, customerId: $customer->id, equipmentId: $equipment->id);
    self::assertSame(1, $result->totalItems);
    self::assertSame($record->id, $result->items[0]->id);
  }

  public function testPublishedReadsNeverReturnForeignMissingOrUnpublishedWork(): void
  {
    $foreign = $this->organization(2);
    $first = $this->intervention(100, $this->snapshot(100));
    $second = $this->intervention(101, $this->snapshot(101));
    $other = $this->intervention(102, $this->snapshot(102), organization: $foreign);
    $draft = $this->intervention(103, $this->snapshot(103), 'draft');
    $this->publication(200, $first, '2026-10-01T10:00:00Z');
    $this->publication(201, $second, '2026-10-01T10:00:00Z');
    $this->publication(202, $other, '2026-10-01T10:00:00Z');
    $this->publication(203, $draft, '2026-10-01T10:00:00Z');
    $this->entityManager->flush();
    $port = $this->port();
    self::assertNull($port->published($this->organization->id, $other->id));
    self::assertNull($port->published($this->organization->id, $draft->id));
    self::assertNull($port->published($this->organization->id, self::id(999)));
    self::assertNull($port->economicContext($this->organization->id, $other->id));
    self::assertNull($port->economicContext($this->organization->id, self::id(999)));
    $facts = $port->publishedBatch($this->organization->id, [$second->id, $other->id, $first->id, $first->id, $draft->id, self::id(999)]);
    self::assertSame([$first->id, $second->id], array_map(static fn ($fact): string => $fact->id, $facts));
    self::assertSame([], $port->publishedBatch($this->organization->id, []));
  }

  public function testPagingHasAnExactCountIncludesMissingSnapshotsAndDoesNotDuplicatePublications(): void
  {
    $ids = [];
    for ($offset = 0; $offset < 5; ++$offset) {
      $record = $this->intervention(100 + $offset, 2 === $offset ? null : $this->snapshot(100 + $offset));
      $ids[] = $record->id;
      $this->publication(200 + $offset, $record, '2026-10-01T10:00:00Z');
      if (0 === $offset) {
        $this->publication(210, $record, '2026-10-02T10:00:00Z', revision: 4);
      }
    }
    $this->intervention(105, null, 'draft');
    $foreign = $this->organization(2);
    $this->intervention(106, null, organization: $foreign);
    $this->entityManager->flush();
    $port = $this->port();
    $readIds = [];
    for ($page = 1; $page <= 3; ++$page) {
      $result = $port->publishedPage($this->organization->id, $page, 2);
      self::assertSame(5, $result->totalItems);
      self::assertSame($page, $result->page);
      self::assertSame(2, $result->itemsPerPage);
      self::assertCount(3 === $page ? 1 : 2, $result->items);
      foreach ($result->items as $fact) {
        $readIds[] = $fact->id;
      }
    }
    self::assertSame($ids, $readIds);
    self::assertSame([], $port->publishedPage($this->organization->id, 4, 2)->items);
    self::assertSame('snapshot_missing', $port->published($this->organization->id, $ids[2])?->snapshotState);
    self::assertSame(self::id(210), $port->published($this->organization->id, $ids[0])?->publicationId);
  }

  public function testMissingDossiersNeverInventHistoryFromRichLiveRecords(): void
  {
    [$site, $customer, $equipment] = $this->identities(300);
    $record = $this->intervention(100, null);
    $record->siteId = $site->id;
    $this->workItem(400, $record, '/api/equipment/' . $equipment->id);
    $this->publication(200, $record, '2026-10-01T10:00:00Z');
    $this->entityManager->flush();
    $port = $this->port();
    $fact = $port->published($this->organization->id, $record->id);
    self::assertNotNull($fact);
    self::assertSame('snapshot_missing', $fact->snapshotState);
    self::assertNull($fact->snapshotVersion);
    self::assertFalse($fact->identityComplete);
    self::assertNull($fact->site);
    self::assertNull($fact->customer);
    self::assertNull($fact->dossier);
    self::assertSame([], $fact->workItems);
    $context = $port->economicContext($this->organization->id, $record->id);
    self::assertNotNull($context);
    self::assertSame('snapshot_missing', $context->snapshotState);
    self::assertNull($context->site);
    self::assertNull($context->customer);
    self::assertSame([], $context->workItems);
    self::assertSame(0, $port->economicPage($this->organization->id, siteId: $site->id)->totalItems);
    self::assertSame(0, $port->economicPage($this->organization->id, customerId: $customer->id)->totalItems);
    self::assertSame(0, $port->economicPage($this->organization->id, equipmentId: $equipment->id)->totalItems);
  }

  public function testVersionOneRetainsKnownTargetsWithoutInventingFullAssetIdentityOrValidation(): void
  {
    [$site, $customer, $equipment] = $this->identities(300);
    $work = $this->snapshotWork(400, $equipment->id);
    unset($work['equipmentIdentity'], $work['site'], $work['customer']);
    $work['executionResult'] = ['state' => 'staged', 'outcome' => 'successful'];
    $snapshot = $this->snapshot(100, [$work], $site->id, $customer->id);
    $snapshot['version'] = 1;
    $snapshot['customer'] = ['id' => $customer->id, 'name' => 'Original customer', 'email' => 'private@example.test', 'contacts' => [['name' => 'Private contact']]];
    unset($snapshot['number'], $snapshot['name'], $snapshot['type'], $snapshot['createdAt'], $snapshot['plannedStartAt'], $snapshot['dueAt']);
    $record = $this->intervention(100, $snapshot);
    $this->workItem(401, $record, '/api/equipment/' . self::id(999));
    $this->publication(200, $record, '2026-10-01T10:00:00Z');
    $this->entityManager->flush();
    $fact = $this->port()->published($this->organization->id, $record->id);
    self::assertNotNull($fact);
    self::assertSame('available', $fact->snapshotState);
    self::assertSame(1, $fact->snapshotVersion);
    self::assertFalse($fact->identityComplete);
    self::assertSame(['id' => $site->id, 'name' => 'Original site'], $fact->site);
    self::assertSame(['id' => $customer->id, 'name' => 'Original customer'], $fact->customer);
    self::assertSame(['id' => $customer->id, 'name' => 'Original customer'], $fact->dossier['customer'] ?? null);
    self::assertCount(1, $fact->workItems);
    self::assertSame(self::id(400), $fact->workItems[0]->id);
    self::assertSame($equipment->id, $fact->workItems[0]->equipmentId);
    self::assertNull($fact->workItems[0]->equipmentIdentity);
    self::assertFalse($fact->workItems[0]->validated);
  }

  public function testVersionTwoIdentitiesAndWorkRemainFrozenAfterTheLiveParcIsRenamed(): void
  {
    [$site, $customer, $equipment] = $this->identities(300);
    $work = $this->snapshotWork(400, $equipment->id, $site->id, $customer->id);
    $snapshot = $this->snapshot(100, [$work], $site->id, $customer->id);
    $record = $this->intervention(100, $snapshot);
    $record->siteId = $site->id;
    $task = $this->workItem(400, $record, '/api/equipment/' . $equipment->id);
    $this->publication(200, $record, '2026-10-01T10:00:00Z');
    $this->entityManager->flush();
    $port = $this->port();
    $before = $port->published($this->organization->id, $record->id);
    $contextBefore = $port->economicContext($this->organization->id, $record->id);
    self::assertNotNull($before);
    self::assertTrue($before->identityComplete);
    self::assertSame('Original intervention', $before->name);
    self::assertSame(40, $before->number);
    self::assertSame(4, $before->revision);
    self::assertSame('2026-09-30T07:00:00+00:00', $before->createdAt->format('c'));
    self::assertCount(1, $before->workItems);
    self::assertTrue($before->workItems[0]->validated);
    self::assertNotNull($before->workItems[0]->equipmentIdentity);
    self::assertSame('Original equipment', $before->workItems[0]->equipmentIdentity->name);
    self::assertSame('ASSET-300', $before->workItems[0]->equipmentIdentity->assetReference);
    self::assertSame('SERIAL-300', $before->workItems[0]->equipmentIdentity->serialNumber);
    $site->name = 'Renamed site';
    $customer->name = 'Renamed customer';
    $equipment->name = 'Renamed equipment';
    $equipment->assetCode = 'NEW-REFERENCE';
    $equipment->serialNumber = 'NEW-SERIAL';
    $equipment->facilityId = null;
    $record->name = 'Renamed intervention';
    $record->siteId = null;
    $task->target = '/api/equipment/' . self::id(999);
    $this->entityManager->flush();
    self::assertSame(serialize($before), serialize($port->published($this->organization->id, $record->id)));
    self::assertSame(serialize($contextBefore), serialize($port->economicContext($this->organization->id, $record->id)));
    self::assertSame($snapshot, $port->published($this->organization->id, $record->id)?->dossier);
  }

  public function testLegacyClosedInspectionEvidenceDoesNotInventResultsOrValidateLiveCompletedTasks(): void
  {
    $work = $this->snapshotWork(400, self::id(302));
    $work['action'] = 'inspection';
    $work['executionResult'] = null;
    $work['resultResource'] = '/api/inspections/' . self::id(500);
    unset($work['equipmentIdentity'], $work['site'], $work['customer']);
    $skipped = $work;
    $skipped['id'] = self::id(401);
    $skipped['status'] = 'skipped';
    $invalid = $work;
    $invalid['id'] = self::id(402);
    $invalid['resultResource'] = '/api/inspections/' . self::id(500) . '/attachments';
    $snapshot = $this->snapshot(100, [$work, $skipped, $invalid]);
    $snapshot['version'] = 1;
    $published = $this->intervention(100, $snapshot);
    $this->publication(200, $published, '2026-10-01T10:00:00Z');
    $live = $this->intervention(101, null, 'in_progress');
    $task = $this->workItem(403, $live, '/api/equipment/' . self::id(302));
    $task->action = 'inspection';
    $task->resultResource = '/api/inspections/' . self::id(500);
    $this->entityManager->flush();
    $facts = $this->port()->published($this->organization->id, $published->id);
    self::assertNotNull($facts);
    self::assertCount(3, $facts->workItems);
    self::assertTrue($facts->workItems[0]->validated);
    self::assertSame('published_inspection', $facts->workItems[0]->validationSource);
    self::assertNull($facts->workItems[0]->executionResult);
    self::assertNull($facts->workItems[0]->equipmentIdentity);
    self::assertFalse($facts->identityComplete);
    self::assertFalse($facts->workItems[1]->validated);
    self::assertNull($facts->workItems[1]->validationSource);
    self::assertFalse($facts->workItems[2]->validated);
    self::assertNull($facts->workItems[2]->validationSource);
    $context = $this->port()->economicContext($this->organization->id, $live->id);
    self::assertNotNull($context);
    self::assertCount(1, $context->workItems);
    self::assertFalse($context->workItems[0]->validated);
    self::assertNull($context->workItems[0]->validationSource);
    self::assertNull($context->workItems[0]->executionResult);
  }

  /**
   * Creates an organization whose records cannot overlap the shared template fixtures.
   *
   * @since 1.0.0
   */
  private function organization(int $number): OrganizationRecord
  {
    $record = new OrganizationRecord();
    $record->id = self::id($number);
    $record->name = 'Publication fact organization ' . $number;
    $record->slug = 'publication-fact-organization-' . $number;
    $record->ownerUserId = self::id(9000 + $number);
    $record->createdByUserId = $record->ownerUserId;
    $record->createdAt = $record->updatedAt = new DateTimeImmutable('2026-01-01T10:00:00Z');
    $record->status = 'active';
    $record->isActive = true;
    $this->entityManager->persist($record);

    return $record;
  }

  /**
   * Creates mutable source identities independently of the immutable publication JSON.
   *
   * @since 1.0.0
   *
   * @return array{FacilityRecord, CustomerRecord, EquipmentRecord}
   */
  private function identities(int $number, ?OrganizationRecord $organization = null): array
  {
    $organization ??= $this->organization;
    $now = new DateTimeImmutable('2026-01-01T10:00:00Z');
    $customer = new CustomerRecord();
    $customer->id = self::id($number);
    $customer->organizationId = $organization->id;
    $customer->name = 'Original customer';
    $customer->code = 'CUS-' . $number;
    $customer->createdAt = $customer->updatedAt = $now;
    $this->entityManager->persist($customer);
    $site = new FacilityRecord();
    $site->id = self::id($number + 1);
    $site->organization = $organization;
    $site->type = 'site';
    $site->name = 'Original site';
    $site->code = 'SITE-' . $number;
    $site->customerId = $customer->id;
    $site->createdAt = $site->updatedAt = $now;
    $this->entityManager->persist($site);
    $equipment = new EquipmentRecord();
    $equipment->id = self::id($number + 2);
    $equipment->organization = $organization;
    $equipment->facilityId = $site->id;
    $equipment->type = 'fire_extinguisher';
    $equipment->name = 'Original equipment';
    $equipment->assetCode = 'ASSET-' . $number;
    $equipment->brand = 'Original brand';
    $equipment->model = 'Original model';
    $equipment->serialNumber = 'SERIAL-' . $number;
    $equipment->status = 'installed';
    $equipment->createdAt = $equipment->updatedAt = $now;
    $this->entityManager->persist($equipment);

    return [$site, $customer, $equipment];
  }

  /**
   * Creates an intervention with deliberately unrelated creation and planning dates.
   *
   * @since 1.0.0
   *
   * @param array<string,mixed>|null $snapshot independent immutable dossier
   */
  private function intervention(int $number, ?array $snapshot, string $status = 'published', ?OrganizationRecord $organization = null): InterventionRecord
  {
    $record = new InterventionRecord();
    $record->id = self::id($number);
    $record->organization = $organization ?? $this->organization;
    $record->number = $number;
    $record->name = 'Live intervention ' . $number;
    $record->type = 'corrective_maintenance';
    $record->status = $status;
    $record->closureSnapshot = $snapshot;
    $record->revision = 4;
    $record->createdAt = new DateTimeImmutable('2026-01-01T10:00:00Z');
    $record->updatedAt = new DateTimeImmutable('2026-12-01T10:00:00Z');
    $record->plannedStartAt = new DateTimeImmutable('2026-02-01T10:00:00Z');
    $record->dueAt = new DateTimeImmutable('2026-03-01T10:00:00Z');
    $this->entityManager->persist($record);

    return $record;
  }

  /**
   * Creates the durable completion instant used to select published prestations.
   *
   * @since 1.0.0
   */
  private function publication(int $number, InterventionRecord $intervention, string $completedAt, string $status = 'completed', int $revision = 3): PublicationRecord
  {
    $record = new PublicationRecord();
    $record->id = self::id($number);
    $record->intervention = $intervention;
    $record->interventionRevision = $revision;
    $record->status = $status;
    $record->createdAt = new DateTimeImmutable('2026-01-01T10:00:00Z');
    $record->completedAt = new DateTimeImmutable($completedAt);
    $this->entityManager->persist($record);

    return $record;
  }

  /**
   * Creates a live task; historical dossiers must not accidentally read its mutable values.
   *
   * @since 1.0.0
   */
  private function workItem(int $number, InterventionRecord $intervention, ?string $target): InterventionWorkItemRecord
  {
    $record = new InterventionWorkItemRecord();
    $record->id = self::id($number);
    $record->intervention = $intervention;
    $record->action = 'repair';
    $record->target = $target;
    $record->status = 'completed';
    $record->createdAt = $record->updatedAt = new DateTimeImmutable('2026-09-01T10:00:00Z');
    $this->entityManager->persist($record);

    return $record;
  }

  /**
   * Generates deterministic identifiers confined to this fixture's UUID prefix.
   *
   * @since 1.0.0
   */
  private static function id(int $number): string
  {
    return sprintf('ea328400-e29b-41d4-a716-%012d', $number);
  }

  /**
   * Resolves the public port, verifying its main-database runtime wiring.
   *
   * @since 1.0.0
   */
  private function port(): InterventionPublicationFactsPort
  {
    $port = self::getContainer()->get(InterventionPublicationFactsPort::class);
    self::assertInstanceOf(InterventionPublicationFactsPort::class, $port);

    return $port;
  }

  /**
   * Builds an independently declared immutable operational dossier.
   *
   * @since 1.0.0
   *
   * @param list<array<string,mixed>> $workItems frozen original tasks
   *
   * @return array<string,mixed>
   */
  private function snapshot(int $number, array $workItems = [], ?string $siteId = null, ?string $customerId = null): array
  {
    return ['version' => 2, 'capturedAt' => '2026-10-01T10:00:00+00:00', 'interventionId' => self::id($number), 'revision' => 4, 'number' => 40, 'name' => 'Original intervention', 'type' => 'corrective_maintenance', 'createdAt' => '2026-09-30T07:00:00+00:00', 'plannedStartAt' => '2026-09-30T08:00:00+00:00', 'dueAt' => '2026-09-30T17:00:00+00:00', 'site' => null === $siteId ? null : ['id' => $siteId, 'name' => 'Original site'], 'customer' => null === $customerId ? null : ['id' => $customerId, 'name' => 'Original customer'], 'workItems' => $workItems, 'attachments' => [], 'report' => ['number' => 40, 'name' => 'Original intervention', 'type' => 'corrective_maintenance', 'plannedStartAt' => '2026-09-30T08:00:00+00:00', 'dueAt' => '2026-09-30T17:00:00+00:00']];
  }

  /**
   * Builds a captured task whose identities cannot be reconstructed from its current target.
   *
   * @since 1.0.0
   *
   * @return array<string,mixed>
   */
  private function snapshotWork(int $number, string $equipmentId, ?string $siteId = null, ?string $customerId = null): array
  {
    $site = null === $siteId ? null : ['id' => $siteId, 'name' => 'Original site'];
    $customer = null === $customerId ? null : ['id' => $customerId, 'name' => 'Original customer'];

    return ['id' => self::id($number), 'action' => 'repair', 'target' => '/api/equipment/' . $equipmentId, 'resultResource' => null, 'status' => 'completed', 'site' => $site, 'customer' => $customer, 'equipmentIdentity' => ['id' => $equipmentId, 'name' => 'Original equipment', 'assetReference' => 'ASSET-300', 'type' => 'fire_extinguisher', 'brand' => 'Original brand', 'model' => 'Original model', 'serialNumber' => 'SERIAL-300', 'facilityId' => $siteId, 'site' => $site, 'customer' => $customer], 'executionResult' => ['state' => 'validated', 'outcome' => 'successful', 'equipmentId' => $equipmentId, 'performedAt' => '2026-09-30T09:00:00+00:00', 'workPerformed' => 'Original repair'], 'spentMinutes' => 45, 'evidenceCount' => 1, 'createdAt' => '2026-09-30T08:00:00+00:00', 'updatedAt' => '2026-09-30T09:00:00+00:00'];
  }
}
