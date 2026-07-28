<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Messaging\Infrastructure\Persistence\Doctrine\Repository\MessagingConversationFavoriteRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test MessagingConversationFavoriteRepositoryTest.
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
#[CoversClass(MessagingConversationFavoriteRepository::class)]
final class MessagingConversationFavoriteRepositoryTest extends TestCase
{
  #[Test]
  public function testFindFavoritedConversationIdsShortCircuitsOnAnEmptyIdList(): void
  {
    /** @var EntityManagerInterface&MockObject $entityManager */
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::never())->method('getConnection');

    $repository = new MessagingConversationFavoriteRepository($entityManager);

    self::assertSame([], $repository->findFavoritedConversationIds('member-1', []));
  }
}
