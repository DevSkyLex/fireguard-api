<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Intervention\Domain\ValueObject\InterventionAttachmentId;
use Intervention\Infrastructure\Persistence\Doctrine\Repository\InterventionAttachmentRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test InterventionAttachmentRepositoryTest.
 *
 * Deleting an attachment is replayed by the media processor and by
 * at-least-once messaging, so a delete of an already-removed row must be a
 * silent no-op rather than an ORM error.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionAttachmentRepository::class)]
final class InterventionAttachmentRepositoryTest extends TestCase
{
  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655446003';

  // #region Methods
  #[Test]
  public function testDeleteIsANoOpWhenTheRecordIsAlreadyGone(): void
  {
    $records = $this->createStub(EntityRepository::class);
    $records->method('find')->willReturn(null);

    /** @var EntityManagerInterface&MockObject $entityManager */
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('getRepository')->willReturn($records);
    $entityManager->expects(self::never())->method('remove');
    $entityManager->expects(self::never())->method('flush');

    new InterventionAttachmentRepository($entityManager, $this->createStub(\Shared\Application\Port\Outbound\RootTransactionManagerPort::class))
      ->delete(InterventionAttachmentId::fromString(self::ATTACHMENT_ID));
  }

  #[Test]
  public function preservesCommitUncertaintyWithoutASecondPersistenceReset(): void
  {
    $failure = new \Shared\Application\Contract\Transaction\CommitOutcomeUnknownException(new RuntimeException('Commit lost.'));
    $transactions = $this->createStub(\Shared\Application\Port\Outbound\RootTransactionManagerPort::class);
    $transactions->method('transactional')->willThrowException($failure);
    $manager = $this->createMock(EntityManagerInterface::class);
    $manager->method('getRepository')->willReturn($this->createStub(EntityRepository::class));
    $manager->expects(self::never())->method('clear');
    $this->expectExceptionObject($failure);
    new InterventionAttachmentRepository($manager, $transactions)->withUploadLock('parent', self::ATTACHMENT_ID, static fn (): null => null);
  }
  // #endregion
}
