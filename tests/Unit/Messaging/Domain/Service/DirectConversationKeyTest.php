<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\Service;

use Messaging\Domain\Service\DirectConversationKey;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function mb_strlen;

/**
 * Test DirectConversationKeyTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DirectConversationKey::class)]
final class DirectConversationKeyTest extends TestCase
{
  private const string MEMBER_A = '550e8400-e29b-41d4-a716-446655440001';

  private const string MEMBER_B = '550e8400-e29b-41d4-a716-446655440002';

  #[Test]
  public function testForIsOrderIndependent(): void
  {
    $forward = DirectConversationKey::for(self::MEMBER_A, self::MEMBER_B);
    $backward = DirectConversationKey::for(self::MEMBER_B, self::MEMBER_A);

    self::assertSame($forward, $backward, 'A -> B and B -> A must derive the exact same pair key, otherwise two conversations would be created for the same pair.');
  }

  #[Test]
  public function testForIsDeterministic(): void
  {
    $first = DirectConversationKey::for(self::MEMBER_A, self::MEMBER_B);
    $second = DirectConversationKey::for(self::MEMBER_A, self::MEMBER_B);

    self::assertSame($first, $second);
  }

  #[Test]
  public function testForFitsTheSubjectIdColumnLength(): void
  {
    $key = DirectConversationKey::for(self::MEMBER_A, self::MEMBER_B);

    self::assertLessThanOrEqual(36, mb_strlen($key), 'The pair key must fit `messaging_conversations.subject_id` (length: 36).');
    self::assertSame(32, mb_strlen($key));
  }

  #[Test]
  public function testForDifferentPairsProduceDifferentKeys(): void
  {
    $pairOne = DirectConversationKey::for(self::MEMBER_A, self::MEMBER_B);
    $pairTwo = DirectConversationKey::for(self::MEMBER_A, '550e8400-e29b-41d4-a716-446655440003');

    self::assertNotSame($pairOne, $pairTwo);
  }
}
