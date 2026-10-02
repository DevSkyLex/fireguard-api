<?php

declare(strict_types=1);

namespace Assistant\Application\Service;

use Assistant\Application\Contract\Context\AssistantContextScope;
use Assistant\Domain\Model\Message\AssistantMessage;
use LengthException;

use function array_map;
use function array_merge;
use function array_reverse;
use function array_slice;
use function mb_strlen;

/**
 * Service AssistantPromptBuilder.
 *
 * Assembles the chat message list sent to Ollama: a fixed system prompt,
 * then business-context blocks, then a bounded recent transcript, oldest first.
 *
 * **L2.2 seam (now wired).** {@see self::buildContextBlocks()} delegates to
 * {@see AssistantContextAssembler} — a collection of `assistant.context_provider`
 * tagged-iterator provider services contributing organization-scoped
 * business context (compliance summary, open non-conformities, upcoming
 * maintenance due dates at launch) — but ONLY when
 * `$includeBusinessContext` is true, mirroring the organization's own
 * `settings.assistant.includeBusinessContext` opt-in
 * (`Organization\Domain\ValueObject\OrganizationAssistantSettings`). The
 * caller ({@see \Assistant\Application\UseCase\Command\Message\GenerateAssistantReply\GenerateAssistantReplyHandler})
 * resolves that flag through `Assistant\Application\Port\Outbound\Organization\AssistantOrganizationSettingsPort`
 * before calling {@see self::build()}.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AssistantPromptBuilder
{
  /**
   * Constant MAX_TRANSCRIPT_MESSAGES.
   *
   * Caps both repository hydration and the transcript sent to the generation client.
   *
   * @access public
   * @since unreleased
   *
   * @var int MAX_TRANSCRIPT_MESSAGES
   */
  public const int MAX_TRANSCRIPT_MESSAGES = 20;

  /**
   * Constant MAX_TRANSCRIPT_CHARACTERS.
   *
   * Limits transcript bodies independently of the existing business-context budget.
   * The current HTTP question limit is smaller, so the newest accepted prompt fits in full.
   *
   * @access public
   * @since unreleased
   *
   * @var int MAX_TRANSCRIPT_CHARACTERS
   */
  public const int MAX_TRANSCRIPT_CHARACTERS = 24000;

  // #region Constants
  /**
   * Constant SYSTEM_PROMPT.
   *
   * @since 1.0.0
   *
   * @var string SYSTEM_PROMPT
   */
  private const string SYSTEM_PROMPT = 'You are the Fireguard assistant, helping an organization member with '
    . 'fire-safety compliance questions about their facilities, equipment, and interventions. '
    . 'Answer concisely and only from information you are actually given; say so plainly when you do not know.';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param AssistantContextAssembler $contextAssembler the business-context assembler
   */
  public function __construct(
    private readonly AssistantContextAssembler $contextAssembler,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method build.
   *
   * @since 1.0.0
   *
   * @param list<AssistantMessage> $transcript the thread's persisted messages,
   *                                           oldest first, expected to already exclude the still-generating reply
   * @param string $organizationId the owning organization identifier
   * @param AssistantContextScope $scope who is asking, and for which thread
   * @param bool $includeBusinessContext whether the organization has opted into business-context injection
   *
   * @return list<array{role: string, content: string}> the assembled chat messages, oldest first
   */
  public function build(array $transcript, string $organizationId, AssistantContextScope $scope, bool $includeBusinessContext): array
  {
    $transcript = $this->boundedTranscript($transcript);
    $messages = [['role' => 'system', 'content' => self::SYSTEM_PROMPT]];

    foreach ($this->buildContextBlocks($organizationId, $scope, $includeBusinessContext) as $contextBlock) {
      $messages[] = ['role' => 'system', 'content' => $contextBlock];
    }

    return array_merge($messages, array_map(
      static fn (AssistantMessage $entry): array => ['role' => $entry->role()->value, 'content' => $entry->body()],
      $transcript,
    ));
  }

  /**
   * Method boundedTranscript.
   *
   * Keeps a recent contiguous suffix of complete message bodies without changing persisted history.
   * An oversized newest prompt fails instead of being silently truncated or replaced by older turns.
   *
   * @access private
   * @since unreleased
   *
   * @param list<AssistantMessage> $transcript the completed transcript, oldest first
   *
   * @return list<AssistantMessage> the bounded chronological suffix
   *
   * @throws LengthException when the newest prompt alone exceeds the transcript budget
   */
  private function boundedTranscript(array $transcript): array
  {
    $selected = [];
    $remaining = self::MAX_TRANSCRIPT_CHARACTERS;

    foreach (array_reverse(array_slice($transcript, -self::MAX_TRANSCRIPT_MESSAGES)) as $entry) {
      $length = mb_strlen($entry->body(), 'UTF-8');
      if ($length > $remaining) {
        if ([] === $selected) {
          throw new LengthException('Assistant question exceeds the transcript budget.');
        }

        break;
      }
      $selected[] = $entry;
      $remaining -= $length;
    }

    return array_reverse($selected);
  }

  /**
   * Method buildContextBlocks.
   *
   * L2.2 seam — see this class's docblock. Returns `[]` without ever calling
   * the assembler when `$includeBusinessContext` is false (the organization
   * has not opted in), keeping the opt-out path free of any cross-module
   * calls.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param AssistantContextScope $scope who is asking, and for which thread
   * @param bool $includeBusinessContext whether the organization has opted into business-context injection
   *
   * @return list<string> business-context blocks to insert between the system prompt and the transcript
   */
  private function buildContextBlocks(string $organizationId, AssistantContextScope $scope, bool $includeBusinessContext): array
  {
    if (!$includeBusinessContext) {
      return [];
    }

    return $this->contextAssembler->assemble($organizationId, $scope);
  }
  // #endregion
}
