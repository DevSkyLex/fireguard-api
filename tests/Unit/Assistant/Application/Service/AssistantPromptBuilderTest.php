<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Application\Service;

use Assistant\Application\Contract\Context\{AssistantContextBudget, AssistantContextFragment, AssistantContextScope};
use Assistant\Application\Port\Outbound\AssistantContextProviderPort;
use Assistant\Application\Service\{AssistantContextAssembler, AssistantPromptBuilder};
use Assistant\Domain\Model\Message\AssistantMessage;
use Assistant\Domain\ValueObject\AssistantMessageId;
use DateTimeImmutable;
use LengthException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\LoggerPort;

use function array_map;
use function array_slice;
use function array_sum;
use function mb_strlen;
use function sprintf;
use function str_repeat;

/**
 * Test AssistantPromptBuilderTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AssistantPromptBuilder::class)]
final class AssistantPromptBuilderTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  #[Test]
  public function testBuildStartsWithASystemPromptWhenTranscriptIsEmpty(): void
  {
    $builder = new AssistantPromptBuilder($this->assembler());

    $messages = $builder->build([], self::ORG_ID, self::scope(), false);

    self::assertCount(1, $messages);
    self::assertSame('system', $messages[0]['role']);
    self::assertNotSame('', $messages[0]['content']);
  }

  #[Test]
  public function testBuildAppendsTheTranscriptInOrderAfterTheSystemPrompt(): void
  {
    $builder = new AssistantPromptBuilder($this->assembler());
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $userMessage = AssistantMessage::askUser(
      id: AssistantMessageId::fromString('018f0b68-6758-7a12-8a1d-3f0d97f64c06'),
      threadId: 'thread-1',
      organizationId: 'org-1',
      body: 'How many extinguishers are overdue?',
      now: $now,
    );

    $messages = $builder->build([$userMessage], self::ORG_ID, self::scope(), false);

    self::assertCount(2, $messages);
    self::assertSame('system', $messages[0]['role']);
    self::assertSame('user', $messages[1]['role']);
    self::assertSame('How many extinguishers are overdue?', $messages[1]['content']);
  }

  #[Test]
  public function testBuildNeverCallsTheAssemblerWhenBusinessContextIsNotIncluded(): void
  {
    $providers = [$this->neverCalledProvider()];
    $assembler = new AssistantContextAssembler($providers, $this->createStub(LoggerPort::class));

    $builder = new AssistantPromptBuilder($assembler);

    $messages = $builder->build([], self::ORG_ID, self::scope(), false);

    self::assertCount(1, $messages);
  }

  #[Test]
  public function testBuildInsertsAssembledContextBlocksAsSystemMessagesWhenIncluded(): void
  {
    $provider = new class () implements AssistantContextProviderPort {
      public function supports(string $organizationId, AssistantContextScope $scope): bool
      {
        return true;
      }

      public function provide(
        string $organizationId,
        AssistantContextScope $scope,
        AssistantContextBudget $budget,
      ): AssistantContextFragment {
        return AssistantContextFragment::withText('fake', 'Compliance summary: all good.');
      }
    };

    $assembler = new AssistantContextAssembler([$provider], $this->createStub(LoggerPort::class));
    $builder = new AssistantPromptBuilder($assembler);

    $messages = $builder->build([], self::ORG_ID, self::scope(), true);

    self::assertCount(2, $messages);
    self::assertSame('system', $messages[0]['role']);
    self::assertSame('system', $messages[1]['role']);
    self::assertSame('Compliance summary: all good.', $messages[1]['content']);
  }

  /**
   * Method testBuildKeepsOnlyTheTwentyMostRecentMessagesInOrder.
   *
   * Verifies message-count bounds and chronological suffix selection.
   *
   * @access public
   * @since unreleased
   *
   * @return void no return value
   */
  #[Test]
  public function testBuildKeepsOnlyTheTwentyMostRecentMessagesInOrder(): void
  {
    $transcript = [];
    for ($index = 0; $index < 50; ++$index) {
      $transcript[] = $this->message('Message ' . $index, $index);
    }

    $messages = new AssistantPromptBuilder($this->assembler())->build($transcript, self::ORG_ID, self::scope(), false);

    self::assertCount(21, $messages);
    self::assertSame('Message 30', $messages[1]['content']);
    self::assertSame('Message 49', $messages[20]['content']);
    self::assertCount(50, $transcript);
  }

  /**
   * Method testBuildCapsUnicodeCharactersAndPreservesTheFullEightThousandCharacterQuestion.
   *
   * Verifies the Unicode character budget and preservation of the full latest question.
   *
   * @access public
   * @since unreleased
   *
   * @return void no return value
   */
  #[Test]
  public function testBuildCapsUnicodeCharactersAndPreservesTheFullEightThousandCharacterQuestion(): void
  {
    $transcript = [];
    for ($index = 0; $index < 4; ++$index) {
      $transcript[] = $this->message(str_repeat(['é', '漢', '🙂', 'Ω'][$index], 8000), $index);
    }

    $messages = new AssistantPromptBuilder($this->assembler())->build($transcript, self::ORG_ID, self::scope(), false);

    self::assertCount(4, $messages);
    self::assertSame(str_repeat('漢', 8000), $messages[1]['content']);
    self::assertSame(str_repeat('🙂', 8000), $messages[2]['content']);
    self::assertSame(str_repeat('Ω', 8000), $messages[3]['content']);
    self::assertSame(24000, array_sum(array_map(static fn (array $message): int => mb_strlen($message['content'], 'UTF-8'), array_slice($messages, 1))));
    self::assertSame(str_repeat('é', 8000), $transcript[0]->body());
  }

  /**
   * Method testBuildDropsAnOversizedEarlierReplyWithoutTruncatingTheNewestQuestion.
   *
   * Verifies that an oversized historical turn does not displace the latest question.
   *
   * @access public
   * @since unreleased
   *
   * @return void no return value
   */
  #[Test]
  public function testBuildDropsAnOversizedEarlierReplyWithoutTruncatingTheNewestQuestion(): void
  {
    $transcript = [$this->message('Earlier question', 0), $this->message(str_repeat('x', 30000), 1), $this->message('Current question', 2)];
    $messages = new AssistantPromptBuilder($this->assembler())->build($transcript, self::ORG_ID, self::scope(), false);

    self::assertCount(2, $messages);
    self::assertSame('Current question', $messages[1]['content']);
  }

  /**
   * Method testBuildRejectsAnOversizedInternalPromptInsteadOfSilentlyLosingItsContent.
   *
   * Verifies failure rather than silent truncation for an oversized internal prompt.
   *
   * @access public
   * @since unreleased
   *
   * @return void no return value
   */
  #[Test]
  public function testBuildRejectsAnOversizedInternalPromptInsteadOfSilentlyLosingItsContent(): void
  {
    $this->expectException(LengthException::class);
    new AssistantPromptBuilder($this->assembler())->build([$this->message(str_repeat('x', 24001), 0)], self::ORG_ID, self::scope(), false);
  }

  private function assembler(): AssistantContextAssembler
  {
    return new AssistantContextAssembler([], $this->createStub(LoggerPort::class));
  }

  /**
   * Method message.
   *
   * Creates a completed user message for prompt-budget scenarios.
   *
   * @access private
   * @since unreleased
   *
   * @param string $body the synthetic message content
   * @param int $index the unique message identifier suffix
   *
   * @return AssistantMessage the completed message
   */
  private function message(string $body, int $index): AssistantMessage
  {
    return AssistantMessage::askUser(
      AssistantMessageId::fromString(sprintf('018f0b68-6758-7a12-8a1d-%012d', $index + 1)),
      'thread-1',
      self::ORG_ID,
      $body,
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }

  private function neverCalledProvider(): AssistantContextProviderPort
  {
    $provider = $this->createMock(AssistantContextProviderPort::class);
    $provider->expects(self::never())->method('supports');
    $provider->expects(self::never())->method('provide');

    return $provider;
  }

  private static function scope(): AssistantContextScope
  {
    return new AssistantContextScope('user-1', 'thread-1');
  }
}
