<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Application\Service;

use Assistant\Application\Contract\Context\{AssistantContextBudget, AssistantContextFragment, AssistantContextScope};
use Assistant\Application\Port\Outbound\AssistantContextProviderPort;
use Assistant\Application\Service\{AssistantContextAssembler, AssistantPromptBuilder};
use Assistant\Domain\Model\Message\AssistantMessage;
use Assistant\Domain\ValueObject\AssistantMessageId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\LoggerPort;

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

  private function assembler(): AssistantContextAssembler
  {
    return new AssistantContextAssembler([], $this->createStub(LoggerPort::class));
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
