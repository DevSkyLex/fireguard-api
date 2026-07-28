<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Intervention\Domain\ValueObject\InterventionAttachmentId;
use Intervention\Infrastructure\Persistence\Doctrine\Repository\InterventionAttachmentRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

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

    new InterventionAttachmentRepository($entityManager)
      ->delete(InterventionAttachmentId::fromString(self::ATTACHMENT_ID));
  }
  // #endregion
}
