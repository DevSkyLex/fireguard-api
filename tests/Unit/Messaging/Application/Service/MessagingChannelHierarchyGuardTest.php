<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\Service;

use Messaging\Application\Port\Outbound\MessagingConversationRepositoryPort;
use Messaging\Application\Service\MessagingChannelHierarchyGuard;
use Messaging\Domain\Exception\{MessagingConflictException, MessagingValidationException};
use Messaging\Domain\Model\Conversation\Conversation;
use Messaging\Domain\ValueObject\{ChannelName, ConversationId, MessagingSubjectType};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MessagingChannelHierarchyGuardTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingChannelHierarchyGuard::class)]
final class MessagingChannelHierarchyGuardTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string OTHER_ORG_ID = 'org-2';

  private const string CHILD_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string PARENT_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string GRANDPARENT_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string GREATGRANDPARENT_ID = '550e8400-e29b-41d4-a716-446655440004';

  private const string ROOT_ID = '550e8400-e29b-41d4-a716-446655440005';

  #[Test]
  public function testAssertValidParentRejectsAChannelAsItsOwnParent(): void
  {
    $child = $this->channel(self::CHILD_ID, self::ORG_ID);

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $guard = new MessagingChannelHierarchyGuard($conversations);

    $this->expectException(MessagingConflictException::class);

    $guard->assertValidParent($child, self::CHILD_ID);
  }

  #[Test]
  public function testAssertValidParentRejectsAMissingParent(): void
  {
    $child = $this->channel(self::CHILD_ID, self::ORG_ID);

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn(null);

    $guard = new MessagingChannelHierarchyGuard($conversations);

    $this->expectException(MessagingValidationException::class);

    $guard->assertValidParent($child, self::PARENT_ID);
  }

  #[Test]
  public function testAssertValidParentRejectsANonChannelParent(): void
  {
    $child = $this->channel(self::CHILD_ID, self::ORG_ID);
    $subjectThread = Conversation::create(
      ConversationId::fromString(self::PARENT_ID),
      self::ORG_ID,
      MessagingSubjectType::FACILITY,
      'facility-1',
    );

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($subjectThread);

    $guard = new MessagingChannelHierarchyGuard($conversations);

    $this->expectException(MessagingValidationException::class);

    $guard->assertValidParent($child, self::PARENT_ID);
  }

  #[Test]
  public function testAssertValidParentRejectsAParentFromADifferentOrganization(): void
  {
    $child = $this->channel(self::CHILD_ID, self::ORG_ID);
    $parent = $this->channel(self::PARENT_ID, self::OTHER_ORG_ID);

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($parent);

    $guard = new MessagingChannelHierarchyGuard($conversations);

    $this->expectException(MessagingValidationException::class);

    $guard->assertValidParent($child, self::PARENT_ID);
  }

  #[Test]
  public function testAssertValidParentAcceptsATopLevelChannelAsParent(): void
  {
    $child = $this->channel(self::CHILD_ID, self::ORG_ID);
    $parent = $this->channel(self::PARENT_ID, self::ORG_ID);

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturn($parent);

    $guard = new MessagingChannelHierarchyGuard($conversations);

    $result = $guard->assertValidParent($child, self::PARENT_ID);

    self::assertSame(self::PARENT_ID, (string) $result->id());
  }

  #[Test]
  public function testAssertValidParentAcceptsAOneLevelDeepParentGrandchildRemainsWithinTheLimit(): void
  {
    $child = $this->channel(self::CHILD_ID, self::ORG_ID);

    $parent = $this->channel(self::PARENT_ID, self::ORG_ID);
    $parent->setParent(self::GRANDPARENT_ID);

    $grandparent = $this->channel(self::GRANDPARENT_ID, self::ORG_ID);

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturnMap([
      [self::PARENT_ID, $parent],
      [self::GRANDPARENT_ID, $grandparent],
    ]);

    $guard = new MessagingChannelHierarchyGuard($conversations);

    $result = $guard->assertValidParent($child, self::PARENT_ID);

    self::assertSame(self::PARENT_ID, (string) $result->id());
  }

  #[Test]
  public function testAssertValidParentRejectsWhenTheResultingDepthWouldExceedTheMaximum(): void
  {
    $child = $this->channel(self::CHILD_ID, self::ORG_ID);

    $parent = $this->channel(self::PARENT_ID, self::ORG_ID);
    $parent->setParent(self::GRANDPARENT_ID);

    $grandparent = $this->channel(self::GRANDPARENT_ID, self::ORG_ID);
    $grandparent->setParent(self::GREATGRANDPARENT_ID);

    $greatGrandparent = $this->channel(self::GREATGRANDPARENT_ID, self::ORG_ID);

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturnMap([
      [self::PARENT_ID, $parent],
      [self::GRANDPARENT_ID, $grandparent],
      [self::GREATGRANDPARENT_ID, $greatGrandparent],
    ]);

    $guard = new MessagingChannelHierarchyGuard($conversations);

    $this->expectException(MessagingConflictException::class);

    $guard->assertValidParent($child, self::PARENT_ID);
  }

  #[Test]
  public function testAssertValidParentRejectsAMultiHopCycle(): void
  {
    // The child ($grandparent here) is already an ANCESTOR of the candidate
    // parent — attaching child->parent would loop: child -> parent ->
    // grandparent(=child).
    $child = $this->channel(self::GRANDPARENT_ID, self::ORG_ID);

    $parent = $this->channel(self::PARENT_ID, self::ORG_ID);
    $parent->setParent(self::GRANDPARENT_ID);

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturnMap([
      [self::PARENT_ID, $parent],
    ]);

    $guard = new MessagingChannelHierarchyGuard($conversations);

    $this->expectException(MessagingConflictException::class);

    $guard->assertValidParent($child, self::PARENT_ID);
  }

  #[Test]
  public function testAssertValidParentStopsWalkingOnADanglingAncestorReference(): void
  {
    $child = $this->channel(self::CHILD_ID, self::ORG_ID);

    $parent = $this->channel(self::PARENT_ID, self::ORG_ID);
    $parent->setParent(self::GRANDPARENT_ID);

    // The grandparent row is gone: the walk must stop rather than loop.
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturnMap([
      [self::PARENT_ID, $parent],
      [self::GRANDPARENT_ID, null],
    ]);

    $guard = new MessagingChannelHierarchyGuard($conversations);

    self::assertSame($parent, $guard->assertValidParent($child, self::PARENT_ID));
  }

  #[Test]
  public function testAssertValidParentRejectsAnAncestorChainDeeperThanTheMaximum(): void
  {
    $child = $this->channel(self::CHILD_ID, self::ORG_ID);

    $parent = $this->channel(self::PARENT_ID, self::ORG_ID);
    $parent->setParent(self::GRANDPARENT_ID);

    $grandparent = $this->channel(self::GRANDPARENT_ID, self::ORG_ID);
    $grandparent->setParent(self::GREATGRANDPARENT_ID);

    $greatGrandparent = $this->channel(self::GREATGRANDPARENT_ID, self::ORG_ID);
    $greatGrandparent->setParent(self::ROOT_ID);

    $root = $this->channel(self::ROOT_ID, self::ORG_ID);

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findAggregateById')->willReturnMap([
      [self::PARENT_ID, $parent],
      [self::GRANDPARENT_ID, $grandparent],
      [self::GREATGRANDPARENT_ID, $greatGrandparent],
      [self::ROOT_ID, $root],
    ]);

    $guard = new MessagingChannelHierarchyGuard($conversations);

    $this->expectException(MessagingConflictException::class);

    $guard->assertValidParent($child, self::PARENT_ID);
  }

  private function channel(string $id, string $organizationId): Conversation
  {
    return Conversation::createChannel(
      ConversationId::fromString($id),
      $organizationId,
      new ChannelName('Channel'),
      'creator-1',
    );
  }
}
