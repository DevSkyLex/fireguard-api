<?php

declare(strict_types=1);

namespace Assistant\Application\Service;

use Assistant\Application\Contract\Context\{AssistantContextBudget, AssistantContextFragment, AssistantContextScope};
use Assistant\Application\Port\Outbound\AssistantContextProviderPort;
use Shared\Application\Port\Outbound\LoggerPort;
use Throwable;

use function iterator_to_array;
use function max;
use function mb_strlen;
use function mb_substr;
use function trim;

/**
 * Service AssistantContextAssembler.
 *
 * Assembles the business-context blocks injected into the assistant's
 * prompt (see `AssistantPromptBuilder`'s "L2.2 seam"), by fanning out to
 * every tagged `assistant.context_provider` adapter
 * ({@see AssistantContextProviderPort}), mirroring
 * `Notification\Application\Service\InboxAggregator`. The assembler never
 * knows any concrete provider: adding a context source requires zero edits
 * here, only a new tagged adapter in the owning module.
 *
 * **Ordering.** `$providers` is consumed in the EXACT order the DI
 * container hands it (`!tagged_iterator assistant.context_provider`,
 * ordered by each adapter's explicit `priority` tag attribute — higher
 * priority first). The assembler itself never reorders — this keeps the
 * ordering rule visible in one place per provider (its own `config/modules/
 * <module>.yaml` tag) and keeps this class trivially unit-testable: a test
 * simply constructs it with an array literal already in the desired order.
 *
 * **Budget enforcement (the crux — never trust a provider).** Each provider
 * is handed the REMAINING budget as a soft hint, but this class ALWAYS
 * hard-truncates whatever text a provider returns to what is actually left,
 * regardless of what the provider reports or how much it ignores the hint.
 * One chatty provider can therefore never blow the model's context window
 * or silently push the real conversation out of the prompt — later
 * providers simply receive a smaller (possibly zero) budget and stop being
 * called once the budget is exhausted.
 *
 * **Resilience.** A throwing, timing-out, or otherwise misbehaving provider
 * degrades to "contributed nothing" — it never fails the question — and the
 * failure is logged at `error` level so degradation stays visible instead of
 * silent.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssistantContextAssembler
{
  // #region Constants
  /**
   * Constant DEFAULT_BUDGET_CHARACTERS.
   *
   * The total character budget shared across every provider for one
   * assembled prompt. Operator-tunable via the constructor, never a
   * tenant-controlled value.
   *
   * @since 1.0.0
   *
   * @var int DEFAULT_BUDGET_CHARACTERS
   */
  private const int DEFAULT_BUDGET_CHARACTERS = 4000;
  // #endregion

  // #region Properties
  /**
   * Property providers.
   *
   * @since 1.0.0
   *
   * @var list<AssistantContextProviderPort>
   */
  private array $providers;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param iterable<AssistantContextProviderPort> $providers the tagged context providers, already priority-ordered
   * @param LoggerPort $logger the logger port
   * @param int $budgetCharacters the total character budget shared across every provider
   */
  public function __construct(
    iterable $providers,
    private LoggerPort $logger,
    private int $budgetCharacters = self::DEFAULT_BUDGET_CHARACTERS,
  ) {
    $this->providers = iterator_to_array($providers, false);
  }
  // #endregion

  // #region Methods
  /**
   * Method assemble.
   *
   * Fetches, truncates and orders every provider's contribution into a list
   * of text blocks ready to be inserted as `system` messages between the
   * fixed system prompt and the thread's transcript
   * ({@see \Assistant\Application\Service\AssistantPromptBuilder::build()}).
   *
   * @since 1.0.0
   *
   * @param string $organizationId the requesting organization identifier
   * @param AssistantContextScope $scope who is asking, and for which thread
   *
   * @return list<string> the assembled, budget-truncated context blocks, in priority order
   */
  public function assemble(string $organizationId, AssistantContextScope $scope): array
  {
    $blocks = [];
    $remaining = max(0, $this->budgetCharacters);

    foreach ($this->providers as $provider) {
      if ($remaining <= 0) {
        break;
      }

      $fragment = $this->fetchFromProvider($provider, $organizationId, $scope, $remaining);

      if (null === $fragment || $fragment->isEmpty()) {
        continue;
      }

      $truncated = $this->truncate($fragment->text, $remaining);

      if ('' === $truncated) {
        continue;
      }

      $blocks[] = $truncated;
      $remaining -= mb_strlen($truncated);
    }

    return $blocks;
  }

  /**
   * Method fetchFromProvider.
   *
   * Calls one provider defensively: `supports()` false, an empty fragment,
   * or ANY throwable all degrade to "no contribution" — a failing or slow
   * provider never fails the question.
   *
   * @since 1.0.0
   *
   * @param AssistantContextProviderPort $provider the context provider
   * @param string $organizationId the requesting organization identifier
   * @param AssistantContextScope $scope who is asking, and for which thread
   * @param int $remaining the character budget still available
   *
   * @return ?AssistantContextFragment the provider's fragment, or null on refusal/failure
   */
  private function fetchFromProvider(
    AssistantContextProviderPort $provider,
    string $organizationId,
    AssistantContextScope $scope,
    int $remaining,
  ): ?AssistantContextFragment {
    try {
      if (!$provider->supports($organizationId, $scope)) {
        return null;
      }

      return $provider->provide($organizationId, $scope, new AssistantContextBudget($remaining));
    } catch (Throwable $exception) {
      $this->logger->error('Assistant context provider failed; degrading to no contribution.', [
        'provider' => $provider::class,
        'organizationId' => $organizationId,
        'error' => $exception->getMessage(),
      ]);

      return null;
    }
  }

  /**
   * Method truncate.
   *
   * The hard budget enforcement point — see this class's docblock. Never
   * trusts the provider's own text length.
   *
   * @since 1.0.0
   *
   * @param string $text the provider's rendered text
   * @param int $remaining the character budget still available
   *
   * @return string the trimmed, hard-truncated text — possibly empty
   */
  private function truncate(string $text, int $remaining): string
  {
    $trimmed = trim($text);

    if ('' === $trimmed) {
      return '';
    }

    if (mb_strlen($trimmed) <= $remaining) {
      return $trimmed;
    }

    return trim(mb_substr($trimmed, 0, $remaining));
  }
  // #endregion
}
