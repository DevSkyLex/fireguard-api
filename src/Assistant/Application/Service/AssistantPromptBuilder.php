<?php

declare(strict_types=1);

namespace Assistant\Application\Service;

use Assistant\Application\Contract\Context\AssistantContextScope;
use Assistant\Domain\Model\Message\AssistantMessage;

use function array_map;
use function array_merge;

/**
 * Service AssistantPromptBuilder.
 *
 * Assembles the chat message list sent to Ollama: a fixed system prompt,
 * then a seam for business-context blocks, then the thread's own transcript,
 * oldest first.
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
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AssistantPromptBuilder
{
  // #region Constants
  /**
   * Constant SYSTEM_PROMPT.
   *
   * @since 1.0.0
   *
   * @var string SYSTEM_PROMPT
   */
  private const string SYSTEM_PROMPT = 'You are the FireGuard assistant, helping an organization member with '
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
