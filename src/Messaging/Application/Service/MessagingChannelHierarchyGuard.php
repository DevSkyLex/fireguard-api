<?php

declare(strict_types=1);

namespace Messaging\Application\Service;

use Messaging\Application\Port\Outbound\MessagingConversationRepositoryPort;
use Messaging\Domain\Exception\{MessagingConflictException, MessagingValidationException};
use Messaging\Domain\Model\Conversation\Conversation;
use Messaging\Domain\ValueObject\MessagingSubjectType;

use function sprintf;

/**
 * Service MessagingChannelHierarchyGuard.
 *
 * Validates a candidate parent for `SetChannelParentHandler` (L2.6) BEFORE
 * `Conversation::setParent()` is ever called — the aggregate itself trusts
 * whatever id it is given, exactly like `bindTeam()`/`rename()` trust their
 * caller. Four rules, all enforced here:
 *
 * 1. A channel cannot be its own parent (the trivial 1-hop cycle).
 * 2. The candidate parent must exist and must itself be a
 *    `subjectType=CHANNEL` conversation — a subject thread or a direct
 *    conversation can never participate in the hierarchy, on EITHER side
 *    (this guard is only ever reached from `ChannelResource`'s own
 *    `{id}` — already guaranteed to be a channel by
 *    `SetChannelParentHandler` — so only the PARENT side needs checking
 *    here).
 * 3. The candidate parent must belong to the SAME organization as the
 *    child — nesting across organizations would be a tenant-boundary leak
 *    disguised as a UI convenience.
 * 4. Attaching the child under the candidate parent must not create a
 *    multi-hop cycle (the child already being an ancestor of the candidate
 *    parent) and must not push the child deeper than {@see self::MAX_ANCESTORS}
 *    ancestors.
 *
 * Rules 1 and 4 are STATE-DEPENDENT (whether they fire depends on the
 * CURRENT shape of the hierarchy) and therefore throw
 * {@see MessagingConflictException} (`409`, see `MessagingExceptionMapperTrait`).
 * Rules 2 and 3 reject the input itself regardless of state and throw
 * {@see MessagingValidationException} (`422`).
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingChannelHierarchyGuard
{
  // #region Constants
  /**
   * The maximum number of ancestors a channel may have: `0` for a
   * root/top-level channel, up to `MAX_ANCESTORS` for the deepest nested
   * child — i.e. a hierarchy of `MAX_ANCESTORS + 1` levels total.
   *
   * Chosen as `2` (three levels total: grandparent → parent → child). The
   * shipped mockup only ever nests one level deep ("Bâtiment Nord" →
   * "Extincteurs — RDC" / "Extincteurs — Étage 2" / "RIA & détection"), so
   * `2` gives one full level of headroom (e.g. a site → building →
   * discipline-specific channel) without inviting an unbounded tree: each
   * write walks the candidate parent's ENTIRE ancestor chain
   * ({@see self::assertValidParent()}), so the bound also keeps that walk to
   * a handful of round trips, and keeps any future tree-picker UI shallow
   * enough to render without a collapsible infinite-nesting widget. Revisit
   * upward only if a real product need for deeper nesting appears — widening
   * is additive (no migration), narrowing would need to handle existing
   * deeper data first.
   */
  private const int MAX_ANCESTORS = 2;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   */
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method assertValidParent.
   *
   * Validates `$parentConversationId` as a new parent for `$child` and
   * returns the loaded parent aggregate. Throws
   * {@see MessagingConflictException} or {@see MessagingValidationException}
   * on any violation — see the four rules on the class docblock.
   *
   * @since 1.0.0
   *
   * @param Conversation $child the channel being re-parented (already known to be `subjectType=CHANNEL`)
   * @param string $parentConversationId the candidate parent (conversation) identifier
   *
   * @return Conversation the validated parent aggregate
   */
  public function assertValidParent(Conversation $child, string $parentConversationId): Conversation
  {
    $childId = (string) $child->id();

    if ($parentConversationId === $childId) {
      throw new MessagingConflictException('A channel cannot be its own parent.');
    }

    $parent = $this->conversations->findAggregateById($parentConversationId);
    if (null === $parent || MessagingSubjectType::CHANNEL !== $parent->subjectType()) {
      throw new MessagingValidationException('The parent must be an existing channel.');
    }

    if ($parent->organizationId() !== $child->organizationId()) {
      throw new MessagingValidationException('A channel parent must belong to the same organization.');
    }

    // Walk the candidate parent's ancestor chain: detect a multi-hop cycle
    // (the child already being one of the parent's ancestors) and compute
    // the parent's own depth, bounded by MAX_ANCESTORS at every step so a
    // corrupted chain can never loop unboundedly.
    $ancestorDepthOfParent = 0;
    $current = $parent;

    while (null !== $current->parentConversationId()) {
      $nextId = $current->parentConversationId();
      if ($nextId === $childId) {
        throw new MessagingConflictException('Setting this parent would create a cycle in the channel hierarchy.');
      }

      $next = $this->conversations->findAggregateById($nextId);
      if (null === $next) {
        // A dangling reference should not happen (the self-FK is
        // ON DELETE SET NULL), but treat it as "no further ancestors"
        // rather than looping forever.
        break;
      }

      $current = $next;
      ++$ancestorDepthOfParent;

      if ($ancestorDepthOfParent > self::MAX_ANCESTORS) {
        throw new MessagingConflictException(sprintf('A channel hierarchy cannot exceed %d levels of nesting.', self::MAX_ANCESTORS + 1));
      }
    }

    if ($ancestorDepthOfParent + 1 > self::MAX_ANCESTORS) {
      throw new MessagingConflictException(sprintf('A channel hierarchy cannot exceed %d levels of nesting.', self::MAX_ANCESTORS + 1));
    }

    return $parent;
  }
  // #endregion
}
