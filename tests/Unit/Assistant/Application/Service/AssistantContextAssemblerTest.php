<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Application\Service;

use Assistant\Application\Contract\Context\{AssistantContextBudget, AssistantContextFragment, AssistantContextScope};
use Assistant\Application\Port\Outbound\AssistantContextProviderPort;
use Assistant\Application\Service\AssistantContextAssembler;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\LoggerPort;

use function mb_strlen;
use function str_repeat;

/**
 * Test AssistantContextAssemblerTest.
 *
 * Covers the two hard rules from `AssistantContextProviderPort`'s docblock:
 * the assembler NEVER trusts a provider's self-reported length (hard
 * truncation), and a throwing/unsupported provider degrades to "contributed
 * nothing" without failing the whole assembly.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AssistantContextAssembler::class)]
final class AssistantContextAssemblerTest extends TestCase
{
  private const string ORG_ID = '018f0b68-6758-7a12-8a1d-3f0d97f65201';

  #[Test]
  public function testAssembleReturnsAnEmptyListWhenThereAreNoProviders(): void
  {
    $assembler = new AssistantContextAssembler([], $this->createStub(LoggerPort::class));

    self::assertSame([], $assembler->assemble(self::ORG_ID, self::scope()));
  }

  #[Test]
  public function testAssembleConsumesProvidersInTheOrderGiven(): void
  {
    $first = $this->fakeProvider('First block.');
    $second = $this->fakeProvider('Second block.');

    $assembler = new AssistantContextAssembler([$first, $second], $this->createStub(LoggerPort::class));

    self::assertSame(['First block.', 'Second block.'], $assembler->assemble(self::ORG_ID, self::scope()));
  }

  #[Test]
  public function testAssembleSkipsAProviderThatDoesNotSupportTheScope(): void
  {
    $unsupported = $this->fakeProvider('Should never appear.', supports: false);
    $supported = $this->fakeProvider('Should appear.');

    $assembler = new AssistantContextAssembler([$unsupported, $supported], $this->createStub(LoggerPort::class));

    self::assertSame(['Should appear.'], $assembler->assemble(self::ORG_ID, self::scope()));
  }

  #[Test]
  public function testAssembleSkipsAnEmptyFragment(): void
  {
    $empty = $this->fakeProvider('');
    $withContent = $this->fakeProvider('Non-empty.');

    $assembler = new AssistantContextAssembler([$empty, $withContent], $this->createStub(LoggerPort::class));

    self::assertSame(['Non-empty.'], $assembler->assemble(self::ORG_ID, self::scope()));
  }

  #[Test]
  public function testAssembleDegradesToNoContributionWhenAProviderThrowsAndLogsTheFailure(): void
  {
    $throwing = new class () implements AssistantContextProviderPort {
      public function supports(string $organizationId, AssistantContextScope $scope): bool
      {
        return true;
      }

      public function provide(string $organizationId, AssistantContextScope $scope, AssistantContextBudget $budget): AssistantContextFragment
      {
        throw new RuntimeException('boom');
      }
    };

    $wellBehaved = $this->fakeProvider('Still contributes.');

    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())->method('error');

    $assembler = new AssistantContextAssembler([$throwing, $wellBehaved], $logger);

    self::assertSame(['Still contributes.'], $assembler->assemble(self::ORG_ID, self::scope()));
  }

  #[Test]
  public function testAssembleHardTruncatesRegardlessOfWhatAProviderReturns(): void
  {
    // The provider ignores the budget hint entirely and returns far more
    // text than is left — the assembler must still cap the total.
    $chatty = $this->fakeProvider(str_repeat('x', 100));

    $assembler = new AssistantContextAssembler([$chatty], $this->createStub(LoggerPort::class), budgetCharacters: 10);

    $blocks = $assembler->assemble(self::ORG_ID, self::scope());

    self::assertCount(1, $blocks);
    self::assertSame(10, mb_strlen($blocks[0]));
  }

  #[Test]
  public function testAssembleStopsCallingFurtherProvidersOnceTheBudgetIsExhausted(): void
  {
    $first = $this->fakeProvider(str_repeat('x', 10));

    $second = $this->createMock(AssistantContextProviderPort::class);
    $second->expects(self::never())->method('supports');
    $second->expects(self::never())->method('provide');

    $assembler = new AssistantContextAssembler([$first, $second], $this->createStub(LoggerPort::class), budgetCharacters: 10);

    $blocks = $assembler->assemble(self::ORG_ID, self::scope());

    self::assertCount(1, $blocks);
  }

  #[Test]
  public function testAssembleShrinksTheBudgetHandedToLaterProvidersAsEarlierOnesConsumeIt(): void
  {
    $first = $this->fakeProvider(str_repeat('x', 6));

    $second = $this->createMock(AssistantContextProviderPort::class);
    $second->method('supports')->willReturn(true);
    $second->expects(self::once())
      ->method('provide')
      ->with(self::ORG_ID, self::anything(), self::callback(static fn (AssistantContextBudget $budget): bool => 4 === $budget->remainingCharacters))
      ->willReturn(AssistantContextFragment::withText('second', 'y'));

    $assembler = new AssistantContextAssembler([$first, $second], $this->createStub(LoggerPort::class), budgetCharacters: 10);

    $assembler->assemble(self::ORG_ID, self::scope());
  }

  private function fakeProvider(string $text, bool $supports = true): AssistantContextProviderPort
  {
    return new class ($text, $supports) implements AssistantContextProviderPort {
      public function __construct(
        private string $text,
        private bool $supports,
      ) {
      }

      public function supports(string $organizationId, AssistantContextScope $scope): bool
      {
        return $this->supports;
      }

      public function provide(string $organizationId, AssistantContextScope $scope, AssistantContextBudget $budget): AssistantContextFragment
      {
        return AssistantContextFragment::withText('fake', $this->text);
      }
    };
  }

  private static function scope(): AssistantContextScope
  {
    return new AssistantContextScope('user-1', 'thread-1');
  }
}
