<?php

declare(strict_types=1);

namespace Tests\Integration\Approval\Infrastructure\Persistence\Doctrine\Repository;

use Approval\Domain\Model\ApprovalRequest\ApprovalRequest;
use Approval\Domain\ValueObject\{ApprovalRequestId, ApprovalStatus};
use Approval\Infrastructure\Persistence\Doctrine\Record\ApprovalRequestRecord;
use Approval\Infrastructure\Persistence\Doctrine\Repository\ApprovalRequestRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_filter;
use function array_map;
use function array_values;
use function in_array;

/**
 * Test ApprovalRequestRepository.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ApprovalRequestRepository::class)]
final class ApprovalRequestRepositoryTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'aa000000-0000-4000-8000-000000000001';

  private const string OTHER_ORGANIZATION_ID = 'aa000000-0000-4000-8000-0000000000ff';

  private const string A1 = 'a1000000-0000-4000-8000-000000000001';

  private const string A2 = 'a2000000-0000-4000-8000-000000000002';

  private const string A3 = 'a3000000-0000-4000-8000-000000000003';

  private const string A4 = 'a4000000-0000-4000-8000-000000000004';

  private const string A5 = 'a5000000-0000-4000-8000-000000000005';

  private const string A6 = 'a6000000-0000-4000-8000-000000000006';

  private const string MISSING_ID = 'aaaaaaaa-0000-4000-8000-000000000000';

  private const string SAVE_NEW_ID = 'b1000000-0000-4000-8000-000000000001';

  private const string RESERVE_NEW_ID = 'b2000000-0000-4000-8000-000000000002';

  private const string RESERVE_CONFLICT_ID = 'b3000000-0000-4000-8000-000000000003';

  private const string MEMBER_ID = 'c1000000-0000-4000-8000-000000000001';

  private const string USER_ID = 'c2000000-0000-4000-8000-000000000002';

  private const string DECIDER_MEMBER_ID = 'c3000000-0000-4000-8000-000000000003';

  private const string DECIDER_USER_ID = 'c4000000-0000-4000-8000-000000000004';

  private const string SUBJECT_1 = 'd1000000-0000-4000-8000-000000000001';

  private const string SUBJECT_2 = 'd2000000-0000-4000-8000-000000000002';

  private const string SUBJECT_3 = 'd3000000-0000-4000-8000-000000000003';

  private const string SUBJECT_4 = 'd4000000-0000-4000-8000-000000000004';

  private const string SUBJECT_5 = 'd5000000-0000-4000-8000-000000000005';

  private const string SUBJECT_6 = 'd6000000-0000-4000-8000-000000000006';

  private const string SUBJECT_SAVE = 'd7000000-0000-4000-8000-000000000007';

  private const string SUBJECT_RESERVE = 'd8000000-0000-4000-8000-000000000008';

  private const string ACTION_INSPECTION_DELETE = 'inspection.delete';

  private const string ACTION_EQUIPMENT_DELETE = 'equipment.delete';

  private const string ACTION_EQUIPMENT_ARCHIVE = 'equipment.archive';

  private EntityManagerInterface $entityManager;

  private ApprovalRequestRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    /** @var ApprovalRequestRepository $repository */
    $repository = static::getContainer()->get(ApprovalRequestRepository::class);
    $this->repository = $repository;

    // A6 (created 01-06) > A4 (01-04) > A3 (01-03) > A2 (01-02) > A1 (01-01).
    $this->seedRecord(self::A1, self::ORGANIZATION_ID, self::ACTION_INSPECTION_DELETE, self::SUBJECT_1, ApprovalStatus::PENDING, '2026-01-01T00:00:00+00:00', '2026-02-01T00:00:00+00:00');
    $this->seedRecord(self::A2, self::ORGANIZATION_ID, self::ACTION_INSPECTION_DELETE, self::SUBJECT_2, ApprovalStatus::APPROVED, '2026-01-02T00:00:00+00:00', '2026-02-02T00:00:00+00:00');
    $this->seedRecord(self::A3, self::ORGANIZATION_ID, self::ACTION_EQUIPMENT_DELETE, self::SUBJECT_3, ApprovalStatus::PENDING, '2026-01-03T00:00:00+00:00', '2025-12-01T00:00:00+00:00');
    $this->seedRecord(self::A4, self::ORGANIZATION_ID, self::ACTION_INSPECTION_DELETE, self::SUBJECT_4, ApprovalStatus::PENDING, '2026-01-04T00:00:00+00:00', '2099-01-01T00:00:00+00:00');
    $this->seedRecord(self::A6, self::ORGANIZATION_ID, self::ACTION_EQUIPMENT_ARCHIVE, self::SUBJECT_6, ApprovalStatus::PENDING, '2026-01-06T00:00:00+00:00', '2025-11-01T00:00:00+00:00');
    // Another organization: must never leak into org-scoped reads.
    $this->seedRecord(self::A5, self::OTHER_ORGANIZATION_ID, self::ACTION_INSPECTION_DELETE, self::SUBJECT_5, ApprovalStatus::PENDING, '2026-01-05T00:00:00+00:00', '2025-10-01T00:00:00+00:00');

    $this->entityManager->flush();
    $this->entityManager->clear();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testReservePendingCreatesANewReservation(): void
  {
    $reservation = $this->repository->reservePending(
      self::RESERVE_NEW_ID,
      self::ORGANIZATION_ID,
      'equipment.transfer',
      self::SUBJECT_RESERVE,
      self::MEMBER_ID,
      self::USER_ID,
      ['scope' => 'reserve'],
      new DateTimeImmutable('2026-04-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-01-22T00:00:00+00:00'),
    );

    self::assertTrue($reservation->isNew);
    self::assertSame(self::RESERVE_NEW_ID, $reservation->id);

    $this->entityManager->clear();
    $stored = $this->repository->findById(ApprovalRequestId::fromString(self::RESERVE_NEW_ID));
    self::assertInstanceOf(ApprovalRequest::class, $stored);
    self::assertSame('equipment.transfer', $stored->actionType());
    self::assertSame(self::SUBJECT_RESERVE, $stored->subjectId());
    self::assertSame(ApprovalStatus::PENDING, $stored->status());
    self::assertSame(['scope' => 'reserve'], $stored->payload());
  }

  #[Test]
  public function testReservePendingReturnsTheExistingPendingIdOnConflict(): void
  {
    // A1 already holds the pending slot for (org, inspection.delete, subject 1):
    // the partial unique index makes this INSERT a no-op (ON CONFLICT DO NOTHING).
    $reservation = $this->repository->reservePending(
      self::RESERVE_CONFLICT_ID,
      self::ORGANIZATION_ID,
      self::ACTION_INSPECTION_DELETE,
      self::SUBJECT_1,
      self::MEMBER_ID,
      self::USER_ID,
      ['scope' => 'conflict'],
      new DateTimeImmutable('2026-04-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-01-22T00:00:00+00:00'),
    );

    self::assertFalse($reservation->isNew);
    self::assertSame(self::A1, $reservation->id);

    // The conflicting id was never inserted.
    $this->entityManager->clear();
    self::assertNull($this->repository->findById(ApprovalRequestId::fromString(self::RESERVE_CONFLICT_ID)));
  }

  #[Test]
  public function testSaveInsertsANewRequest(): void
  {
    $request = ApprovalRequest::create(
      ApprovalRequestId::fromString(self::SAVE_NEW_ID),
      self::ORGANIZATION_ID,
      'facility.transfer',
      self::SUBJECT_SAVE,
      self::MEMBER_ID,
      self::USER_ID,
      ['foo' => 'bar'],
      new DateTimeImmutable('2026-03-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-01-20T00:00:00+00:00'),
    );

    $this->repository->save($request);
    $this->entityManager->clear();

    $reloaded = $this->repository->findById(ApprovalRequestId::fromString(self::SAVE_NEW_ID));
    self::assertInstanceOf(ApprovalRequest::class, $reloaded);
    self::assertSame('facility.transfer', $reloaded->actionType());
    self::assertSame(['foo' => 'bar'], $reloaded->payload());
    self::assertSame(ApprovalStatus::PENDING, $reloaded->status());
    self::assertNull($reloaded->decisionByMemberId());
  }

  #[Test]
  public function testSaveUpdatesAnExistingRequest(): void
  {
    $request = $this->repository->findById(ApprovalRequestId::fromString(self::A1));
    self::assertInstanceOf(ApprovalRequest::class, $request);

    $request->approve(self::DECIDER_MEMBER_ID, self::DECIDER_USER_ID, 'Looks good', new DateTimeImmutable('2026-01-10T10:00:00+00:00'));
    $this->repository->save($request);
    $this->entityManager->clear();

    $reloaded = $this->repository->findById(ApprovalRequestId::fromString(self::A1));
    self::assertInstanceOf(ApprovalRequest::class, $reloaded);
    self::assertSame(ApprovalStatus::APPROVED, $reloaded->status());
    self::assertSame('Looks good', $reloaded->decisionNote());
    self::assertSame(self::DECIDER_MEMBER_ID, $reloaded->decisionByMemberId());
    self::assertSame(self::DECIDER_USER_ID, $reloaded->decisionByUserId());
    self::assertInstanceOf(DateTimeImmutable::class, $reloaded->decidedAt());
  }

  #[Test]
  public function testFindByIdReturnsTheRequestOrNullWhenAbsent(): void
  {
    $found = $this->repository->findById(ApprovalRequestId::fromString(self::A1));
    self::assertInstanceOf(ApprovalRequest::class, $found);
    self::assertSame(self::A1, (string) $found->id());
    self::assertSame(self::ORGANIZATION_ID, $found->organizationId());
    self::assertSame(self::ACTION_INSPECTION_DELETE, $found->actionType());
    self::assertSame(ApprovalStatus::PENDING, $found->status());

    self::assertNull($this->repository->findById(ApprovalRequestId::fromString(self::MISSING_ID)));
  }

  #[Test]
  public function testListByOrganizationReturnsOrgScopedResultsOrderedByCreatedAtDescending(): void
  {
    $result = $this->repository->listByOrganization(self::ORGANIZATION_ID, null, null, 50, 0);

    // A5 belongs to another organization and must be excluded.
    self::assertSame([self::A6, self::A4, self::A3, self::A2, self::A1], self::ids($result));
  }

  #[Test]
  public function testListByOrganizationFiltersByStatus(): void
  {
    $result = $this->repository->listByOrganization(self::ORGANIZATION_ID, ApprovalStatus::PENDING->value, null, 50, 0);

    self::assertSame([self::A6, self::A4, self::A3, self::A1], self::ids($result));
  }

  #[Test]
  public function testListByOrganizationFiltersByActionType(): void
  {
    $result = $this->repository->listByOrganization(self::ORGANIZATION_ID, null, self::ACTION_INSPECTION_DELETE, 50, 0);

    self::assertSame([self::A4, self::A2, self::A1], self::ids($result));
  }

  #[Test]
  public function testListByOrganizationFiltersByStatusAndActionType(): void
  {
    $result = $this->repository->listByOrganization(self::ORGANIZATION_ID, ApprovalStatus::PENDING->value, self::ACTION_INSPECTION_DELETE, 50, 0);

    self::assertSame([self::A4, self::A1], self::ids($result));
  }

  #[Test]
  public function testListByOrganizationPaginates(): void
  {
    $firstPage = $this->repository->listByOrganization(self::ORGANIZATION_ID, null, null, 2, 0);
    self::assertSame([self::A6, self::A4], self::ids($firstPage));

    $secondPage = $this->repository->listByOrganization(self::ORGANIZATION_ID, null, null, 2, 2);
    self::assertSame([self::A3, self::A2], self::ids($secondPage));

    $thirdPage = $this->repository->listByOrganization(self::ORGANIZATION_ID, null, null, 2, 4);
    self::assertSame([self::A1], self::ids($thirdPage));
  }

  #[Test]
  public function testCountByOrganizationCountsWithAndWithoutFilters(): void
  {
    self::assertSame(5, $this->repository->countByOrganization(self::ORGANIZATION_ID, null, null));
    self::assertSame(4, $this->repository->countByOrganization(self::ORGANIZATION_ID, ApprovalStatus::PENDING->value, null));
    self::assertSame(3, $this->repository->countByOrganization(self::ORGANIZATION_ID, null, self::ACTION_INSPECTION_DELETE));
    self::assertSame(2, $this->repository->countByOrganization(self::ORGANIZATION_ID, ApprovalStatus::PENDING->value, self::ACTION_INSPECTION_DELETE));
  }

  #[Test]
  public function testFindPendingExpiredBeforeReturnsExpiredPendingOrderedByExpiryAscending(): void
  {
    $now = new DateTimeImmutable('2026-01-15T00:00:00+00:00');
    $result = $this->repository->findPendingExpiredBefore($now, 100);

    // The sweep query is global (not org-scoped): isolate this test's fixtures.
    $ownIds = array_values(array_filter(
      self::ids($result),
      static fn (string $id): bool => in_array($id, [self::A1, self::A2, self::A3, self::A4, self::A6], true),
    ));

    // A6 (expires 2025-11-01) then A3 (2025-12-01), both pending and past due.
    // A1 (2026-02-01) and A4 (2099) are not yet due; A2 is already approved.
    self::assertSame([self::A6, self::A3], $ownIds);
  }

  /**
   * Method seedRecord.
   *
   * @param string $id the approval request identifier
   * @param string $organizationId the owning organization identifier
   * @param string $actionType the regulated action type
   * @param string $subjectId the acted-upon subject identifier
   * @param ApprovalStatus $status the current status
   * @param string $createdAt the ISO-8601 creation instant
   * @param string $expiresAt the ISO-8601 expiry instant
   */
  private function seedRecord(
    string $id,
    string $organizationId,
    string $actionType,
    string $subjectId,
    ApprovalStatus $status,
    string $createdAt,
    string $expiresAt,
  ): void {
    $record = new ApprovalRequestRecord();
    $record->id = $id;
    $record->organizationId = $organizationId;
    $record->actionType = $actionType;
    $record->subjectId = $subjectId;
    $record->status = $status->value;
    $record->requestedByMemberId = self::MEMBER_ID;
    $record->requestedByUserId = self::USER_ID;
    $record->payload = ['seed' => true];
    $record->expiresAt = new DateTimeImmutable($expiresAt);
    $record->createdAt = new DateTimeImmutable($createdAt);
    $record->updatedAt = $record->createdAt;
    $this->entityManager->persist($record);
  }

  /**
   * Method ids.
   *
   * @param list<ApprovalRequest> $requests the approval requests
   *
   * @return list<string> the identifiers, in order
   */
  private static function ids(array $requests): array
  {
    return array_map(static fn (ApprovalRequest $request): string => (string) $request->id(), $requests);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM approval_requests WHERE organization_id IN (:organizationId, :otherOrganizationId)',
      ['organizationId' => self::ORGANIZATION_ID, 'otherOrganizationId' => self::OTHER_ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}
