<?php

declare(strict_types=1);

namespace Tests\Integration\Approval\Infrastructure\Persistence\Doctrine\Mapper;

use Approval\Domain\Model\ApprovalRequest\ApprovalRequest;
use Approval\Domain\ValueObject\{ApprovalRequestId, ApprovalStatus};
use Approval\Infrastructure\Persistence\Doctrine\Mapper\ApprovalRequestMapper;
use Approval\Infrastructure\Persistence\Doctrine\Record\ApprovalRequestRecord;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test ApprovalRequestMapper.
 *
 * @category Mapper Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ApprovalRequestMapper::class)]
final class ApprovalRequestMapperTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'a1b2c3d4-0000-4000-8000-000000000001';

  private const string REQUEST_ID_FULL = 'a1b2c3d4-0000-4000-8000-000000000010';

  private const string REQUEST_ID_MINIMAL = 'a1b2c3d4-0000-4000-8000-000000000011';

  private const string REQUEST_ID_ROUNDTRIP = 'a1b2c3d4-0000-4000-8000-000000000012';

  private EntityManagerInterface $entityManager;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    // `organization_id` is a plain column with no foreign key, so no parent
    // entity graph is required before an ApprovalRequestRecord can be persisted.
    $this->cleanup();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testToDomainMapsEveryFieldFromAReloadedRecord(): void
  {
    /** @var array<string, mixed> $payload */
    $payload = ['action' => 'equipment.delete', 'reason' => 'obsolete', 'nested' => ['flag' => true]];

    $record = new ApprovalRequestRecord();
    $record->id = self::REQUEST_ID_FULL;
    $record->organizationId = self::ORGANIZATION_ID;
    $record->actionType = 'equipment.delete';
    $record->subjectId = 'b2c3d4e5-0000-4000-8000-000000000020';
    $record->status = ApprovalStatus::APPROVED->value;
    $record->requestedByMemberId = 'b2c3d4e5-0000-4000-8000-000000000030';
    $record->requestedByUserId = 'b2c3d4e5-0000-4000-8000-000000000040';
    $record->decisionByMemberId = 'b2c3d4e5-0000-4000-8000-000000000031';
    $record->decisionByUserId = 'b2c3d4e5-0000-4000-8000-000000000041';
    $record->decisionNote = 'Approved after review.';
    $record->payload = $payload;
    $record->expiresAt = new DateTimeImmutable('2026-03-10 12:00:00');
    $record->createdAt = new DateTimeImmutable('2026-03-01 08:00:00');
    $record->updatedAt = new DateTimeImmutable('2026-03-02 09:00:00');
    $record->decidedAt = new DateTimeImmutable('2026-03-02 09:00:00');
    $record->executedAt = new DateTimeImmutable('2026-03-02 09:05:00');
    $record->executionError = 'transient upstream failure';
    $this->entityManager->persist($record);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $reloaded = $this->entityManager->find(ApprovalRequestRecord::class, self::REQUEST_ID_FULL);
    self::assertInstanceOf(ApprovalRequestRecord::class, $reloaded);

    $domain = ApprovalRequestMapper::toDomain($reloaded);

    self::assertSame(self::REQUEST_ID_FULL, (string) $domain->id());
    self::assertSame(self::ORGANIZATION_ID, $domain->organizationId());
    self::assertSame('equipment.delete', $domain->actionType());
    self::assertSame('b2c3d4e5-0000-4000-8000-000000000020', $domain->subjectId());
    self::assertSame(ApprovalStatus::APPROVED, $domain->status());
    self::assertFalse($domain->isPending());
    self::assertSame('b2c3d4e5-0000-4000-8000-000000000030', $domain->requestedByMemberId());
    self::assertSame('b2c3d4e5-0000-4000-8000-000000000040', $domain->requestedByUserId());
    self::assertSame('b2c3d4e5-0000-4000-8000-000000000031', $domain->decisionByMemberId());
    self::assertSame('b2c3d4e5-0000-4000-8000-000000000041', $domain->decisionByUserId());
    self::assertSame('Approved after review.', $domain->decisionNote());
    self::assertSame($payload, $domain->payload());
    self::assertSame('2026-03-10 12:00:00', $domain->expiresAt()->format('Y-m-d H:i:s'));
    self::assertSame('2026-03-01 08:00:00', $domain->createdAt()->format('Y-m-d H:i:s'));
    self::assertSame('2026-03-02 09:00:00', $domain->updatedAt()->format('Y-m-d H:i:s'));
    self::assertNotNull($domain->decidedAt());
    self::assertSame('2026-03-02 09:00:00', $domain->decidedAt()->format('Y-m-d H:i:s'));
    self::assertNotNull($domain->executedAt());
    self::assertSame('2026-03-02 09:05:00', $domain->executedAt()->format('Y-m-d H:i:s'));
    self::assertSame('transient upstream failure', $domain->executionError());
  }

  #[Test]
  public function testToDomainPreservesNullOptionalFieldsAndEmptyPayload(): void
  {
    $record = new ApprovalRequestRecord();
    $record->id = self::REQUEST_ID_MINIMAL;
    $record->organizationId = self::ORGANIZATION_ID;
    $record->actionType = 'inspection.cancel';
    $record->subjectId = 'b2c3d4e5-0000-4000-8000-000000000021';
    $record->status = ApprovalStatus::PENDING->value;
    $record->requestedByMemberId = 'b2c3d4e5-0000-4000-8000-000000000032';
    $record->requestedByUserId = 'b2c3d4e5-0000-4000-8000-000000000042';
    $record->payload = [];
    $record->expiresAt = new DateTimeImmutable('2026-04-01 00:00:00');
    $record->createdAt = new DateTimeImmutable('2026-03-15 10:00:00');
    $record->updatedAt = new DateTimeImmutable('2026-03-15 10:00:00');
    $this->entityManager->persist($record);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $reloaded = $this->entityManager->find(ApprovalRequestRecord::class, self::REQUEST_ID_MINIMAL);
    self::assertInstanceOf(ApprovalRequestRecord::class, $reloaded);

    $domain = ApprovalRequestMapper::toDomain($reloaded);

    self::assertSame(ApprovalStatus::PENDING, $domain->status());
    self::assertTrue($domain->isPending());
    self::assertNull($domain->decisionByMemberId());
    self::assertNull($domain->decisionByUserId());
    self::assertNull($domain->decisionNote());
    self::assertNull($domain->decidedAt());
    self::assertNull($domain->executedAt());
    self::assertNull($domain->executionError());
    self::assertSame([], $domain->payload());
  }

  #[Test]
  public function testToRecordCopiesEveryFieldOntoRecordAndPersists(): void
  {
    /** @var array<string, mixed> $payload */
    $payload = ['action' => 'billing.refund', 'amountCents' => 4200];

    $request = ApprovalRequest::reconstitute(
      id: ApprovalRequestId::fromString(self::REQUEST_ID_ROUNDTRIP),
      organizationId: self::ORGANIZATION_ID,
      actionType: 'billing.refund',
      subjectId: 'b2c3d4e5-0000-4000-8000-000000000022',
      status: ApprovalStatus::REJECTED,
      requestedByMemberId: 'b2c3d4e5-0000-4000-8000-000000000033',
      requestedByUserId: 'b2c3d4e5-0000-4000-8000-000000000043',
      decisionByMemberId: 'b2c3d4e5-0000-4000-8000-000000000034',
      decisionByUserId: 'b2c3d4e5-0000-4000-8000-000000000044',
      decisionNote: 'Rejected: policy violation.',
      payload: $payload,
      expiresAt: new DateTimeImmutable('2026-05-10 12:00:00'),
      createdAt: new DateTimeImmutable('2026-05-01 08:00:00'),
      updatedAt: new DateTimeImmutable('2026-05-02 09:00:00'),
      decidedAt: new DateTimeImmutable('2026-05-02 09:00:00'),
      executedAt: null,
      executionError: null,
    );

    $record = new ApprovalRequestRecord();
    ApprovalRequestMapper::toRecord($request, $record);

    // Every field must be copied onto the record verbatim, in-memory.
    self::assertSame(self::REQUEST_ID_ROUNDTRIP, $record->id);
    self::assertSame(self::ORGANIZATION_ID, $record->organizationId);
    self::assertSame('billing.refund', $record->actionType);
    self::assertSame('b2c3d4e5-0000-4000-8000-000000000022', $record->subjectId);
    self::assertSame('rejected', $record->status);
    self::assertSame('b2c3d4e5-0000-4000-8000-000000000033', $record->requestedByMemberId);
    self::assertSame('b2c3d4e5-0000-4000-8000-000000000043', $record->requestedByUserId);
    self::assertSame('b2c3d4e5-0000-4000-8000-000000000034', $record->decisionByMemberId);
    self::assertSame('b2c3d4e5-0000-4000-8000-000000000044', $record->decisionByUserId);
    self::assertSame('Rejected: policy violation.', $record->decisionNote);
    self::assertSame($payload, $record->payload);
    self::assertNull($record->executedAt);
    self::assertNull($record->executionError);

    // The produced record must survive a real database round-trip unchanged.
    $this->entityManager->persist($record);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $reloaded = $this->entityManager->find(ApprovalRequestRecord::class, self::REQUEST_ID_ROUNDTRIP);
    self::assertInstanceOf(ApprovalRequestRecord::class, $reloaded);
    self::assertSame('rejected', $reloaded->status);
    self::assertSame('billing.refund', $reloaded->actionType);
    self::assertSame($payload, $reloaded->payload);
    self::assertSame('Rejected: policy violation.', $reloaded->decisionNote);
    self::assertSame('2026-05-10 12:00:00', $reloaded->expiresAt->format('Y-m-d H:i:s'));
    self::assertSame('2026-05-01 08:00:00', $reloaded->createdAt->format('Y-m-d H:i:s'));
    self::assertSame('2026-05-02 09:00:00', $reloaded->updatedAt->format('Y-m-d H:i:s'));
    self::assertNotNull($reloaded->decidedAt);
    self::assertSame('2026-05-02 09:00:00', $reloaded->decidedAt->format('Y-m-d H:i:s'));
    self::assertNull($reloaded->executedAt);
    self::assertNull($reloaded->executionError);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM approval_requests WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}
