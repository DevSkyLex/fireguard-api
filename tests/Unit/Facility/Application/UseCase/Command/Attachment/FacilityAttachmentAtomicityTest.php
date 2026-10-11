<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\Attachment;

use Facility\Application\Port\Outbound\Internal\{FacilityAttachmentRepositoryPort, FacilityRepositoryPort};
use Facility\Application\UseCase\Command\Attachment\AddFacilityAttachment\{AddFacilityAttachmentCommand, AddFacilityAttachmentHandler};
use Facility\Domain\Exception\{FacilityAccessDeniedException, FacilityNotFoundException};
use Facility\Domain\Model\Attachment\FacilityAttachment;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityAttachmentId, FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\Transaction\{CommitOutcomeUnknownException, TransactionAlreadyActiveException};
use Shared\Application\Port\Outbound\{FileCleanupPort, FileStoragePort, RootTransactionManagerPort, UuidGeneratorPort};
use Throwable;

/** Regression tests for upload ownership, replay and final-commit failure semantics. */
final class FacilityAttachmentAtomicityTest extends TestCase
{
  private const string ORGANIZATION = 'dc110000-0000-4000-8000-000000000001';

  private const string FACILITY = 'dc110000-0000-4000-8000-000000000002';

  private const string ATTACHMENT = 'dc110000-0000-4000-8000-000000000003';

  private const string ATTEMPT = 'dc110000-0000-4000-8000-000000000004';

  #[Test]
  public function enclosingTransactionsAreRejectedBeforeStorageOrPersistence(): void
  {
    $transactions = $this->createMock(RootTransactionManagerPort::class);
    $transactions->expects(self::once())->method('assertCanStart')->willThrowException(new TransactionAlreadyActiveException());
    $transactions->expects(self::never())->method('transactional');
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::never())->method('write');
    $storage->expects(self::never())->method('delete');
    $attachments = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachments->expects(self::never())->method('saveIfAbsent');
    $this->expectException(TransactionAlreadyActiveException::class);
    $this->handler($transactions, $storage, $attachments)($this->command());
  }

  #[Test]
  public function aLateOperationFailureCompensatesOnlyTheAttemptPath(): void
  {
    $failure = new RuntimeException('revision write failed');
    $transactions = $this->createStub(RootTransactionManagerPort::class);
    $transactions->method('transactional')->willReturnCallback(static function (callable $operation) use ($failure): never {
      $operation();

      throw $failure;
    });
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::once())->method('write')->with($this->path(), '%PDF');
    $storage->expects(self::once())->method('delete')->with($this->path());
    $this->expectExceptionObject($failure);
    $this->handler($transactions, $storage, $this->attachments())($this->command());
  }

  #[Test]
  public function aFailedCompensationPreservesTheOriginalSqlFailure(): void
  {
    $failure = new RuntimeException('database failed');
    $attachments = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachments->method('findById')->willReturn(null);
    $attachments->expects(self::once())->method('saveIfAbsent')->willThrowException($failure);
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::once())->method('delete')->willThrowException(new RuntimeException('storage failed'));
    $this->expectExceptionObject($failure);
    $this->handler($this->transactions(), $storage, $attachments)($this->command());
  }

  #[Test]
  public function anUncertainCommitRetainsBytesForPersistenceReconciliation(): void
  {
    $transactions = $this->createStub(RootTransactionManagerPort::class);
    $transactions->method('transactional')->willReturnCallback(static function (callable $operation): never {
      $operation();

      throw new CommitOutcomeUnknownException(new RuntimeException('connection lost during commit'));
    });
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::once())->method('write')->with($this->path(), '%PDF');
    $storage->expects(self::never())->method('delete');
    $this->expectException(CommitOutcomeUnknownException::class);
    $this->handler($transactions, $storage, $this->attachments())($this->command());
  }

  #[Test]
  public function replayReturnsWinningMetadataWithoutWritingOrDeletingBytes(): void
  {
    $winner = $this->winner();
    $attachments = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachments->method('findById')->willReturn($winner);
    $attachments->expects(self::never())->method('saveIfAbsent');
    $attachments->expects(self::never())->method('countByFacilityId');
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::never())->method('write');
    $storage->expects(self::never())->method('delete');
    $result = $this->handler($this->transactions(), $storage, $attachments)($this->command());
    self::assertSame('winning.pdf', $result->fileName);
    self::assertSame(self::ATTACHMENT, $result->attachmentId);
  }

  #[Test]
  public function aLostInsertRequestsCleanupOfItsOwnPathAndRetainsTheWinner(): void
  {
    $attachments = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachments->method('findById')->willReturnOnConsecutiveCalls(null, $this->winner());
    $attachments->expects(self::once())->method('saveIfAbsent')->willReturn(false);
    $cleanup = $this->createMock(FileCleanupPort::class);
    $cleanup->expects(self::once())->method('request')->with($this->path());
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::once())->method('write')->with($this->path(), '%PDF');
    $storage->expects(self::never())->method('delete');
    $result = $this->handler($this->transactions(), $storage, $attachments, $cleanup)($this->command());
    self::assertSame('winning.pdf', $result->fileName);
  }

  /**
   * Method authorizationPrecedesReplayAndStorage
   *
   * @param class-string<Throwable> $exceptionClass expected denial contract
   */
  #[Test]
  #[DataProvider('authorizationDenials')]
  public function authorizationPrecedesReplayAndStorage(OrganizationAccessDecision $decision, string $exceptionClass, string $message): void
  {
    $attachments = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachments->expects(self::once())->method('lock');
    $attachments->expects(self::never())->method('findById');
    $attachments->expects(self::never())->method('countByFacilityId');
    $attachments->expects(self::never())->method('saveIfAbsent');
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::never())->method('write');
    $storage->expects(self::never())->method('delete');
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('resolveAccess')->with('writer', self::ORGANIZATION, 'organization.facilities.write')->willReturn($decision);
    $this->expectException($exceptionClass);
    $this->expectExceptionMessage($message);
    $this->handler($this->transactions(), $storage, $attachments, authorization: $authorization)(new AddFacilityAttachmentCommand(self::ORGANIZATION, self::FACILITY, 'new.pdf', '%PDF', 'application/pdf', 4, attachmentId: self::ATTACHMENT, userId: 'writer'));
  }

  /**
   * Method authorizationDenials
   *
   * @return iterable<string, array{OrganizationAccessDecision, class-string<Throwable>, string}>
   */
  public static function authorizationDenials(): iterable
  {
    yield 'outside scope' => [OrganizationAccessDecision::OUTSIDE_SCOPE, FacilityNotFoundException::class, 'Facility not found.'];
    yield 'missing permission' => [OrganizationAccessDecision::MISSING_PERMISSION, FacilityAccessDeniedException::class, 'Missing organization.facilities.write permission.'];
  }

  private function handler(RootTransactionManagerPort $transactions, FileStoragePort $storage, FacilityAttachmentRepositoryPort $attachments, ?FileCleanupPort $cleanup = null, ?OrganizationAuthorizationPort $authorization = null): AddFacilityAttachmentHandler
  {
    $facilities = $this->createStub(FacilityRepositoryPort::class);
    $facilities->method('findById')->willReturn(Facility::create(
      FacilityId::fromString(self::FACILITY),
      FacilityOrganizationId::fromString(self::ORGANIZATION),
      FacilityType::SITE,
      new FacilityName('Site'),
    ));
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn(self::ATTEMPT);

    return new AddFacilityAttachmentHandler($facilities, $attachments, $storage, $ids, $transactions, $cleanup ?? $this->createStub(FileCleanupPort::class), $authorization ?? $this->createStub(OrganizationAuthorizationPort::class));
  }

  private function transactions(): RootTransactionManagerPort
  {
    $transactions = $this->createStub(RootTransactionManagerPort::class);
    $transactions->method('transactional')->willReturnCallback(static fn (callable $operation): mixed => $operation());

    return $transactions;
  }

  private function attachments(): FacilityAttachmentRepositoryPort
  {
    $attachments = $this->createStub(FacilityAttachmentRepositoryPort::class);
    $attachments->method('saveIfAbsent')->willReturn(true);

    return $attachments;
  }

  private function command(): AddFacilityAttachmentCommand
  {
    return new AddFacilityAttachmentCommand(self::ORGANIZATION, self::FACILITY, 'new.pdf', '%PDF', 'application/pdf', 4, attachmentId: self::ATTACHMENT);
  }

  private function winner(): FacilityAttachment
  {
    return FacilityAttachment::create(FacilityAttachmentId::fromString(self::ATTACHMENT), FacilityId::fromString(self::FACILITY), 'winning.pdf', 'facility/winning.pdf', 'application/pdf', 4);
  }

  private function path(): string
  {
    return 'facility/' . self::FACILITY . '/attachments/' . self::ATTEMPT . '_new.pdf';
  }
}
