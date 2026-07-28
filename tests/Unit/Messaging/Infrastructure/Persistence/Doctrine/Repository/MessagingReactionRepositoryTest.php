<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Messaging\Infrastructure\Persistence\Doctrine\Repository\MessagingReactionRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test MessagingReactionRepositoryTest.
 *
 * Only the short-circuit is a unit concern: an empty id list must never
 * reach the database, because an `IN ()` with no values is invalid SQL.
 * Every query-executing path is exercised for real by the integration
 * suite.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingReactionRepository::class)]
final class MessagingReactionRepositoryTest extends TestCase
{
  #[Test]
  public function testFindByMessageIdsShortCircuitsOnAnEmptyIdList(): void
  {
    /** @var EntityManagerInterface&MockObject $entityManager */
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::never())->method('createQueryBuilder');

    $repository = new MessagingReactionRepository($entityManager);

    self::assertSame([], $repository->findByMessageIds([]));
  }
}
