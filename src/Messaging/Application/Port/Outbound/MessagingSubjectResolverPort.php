<?php

declare(strict_types=1);

namespace Messaging\Application\Port\Outbound;

use Messaging\Application\Contract\Subject\MessagingSubjectResolution;
use Messaging\Domain\ValueObject\MessagingSubjectType;

/**
 * Port MessagingSubjectResolverPort.
 *
 * Cross-module seam tagged `messaging.subject_resolver` — a copy of the
 * `intervention.resource_owner` pattern. Each provider module (Facility,
 * Equipment, Intervention, Inspection) hosts one adapter under its own
 * `Infrastructure/Adapter/Messaging/`, registered with this tag in its own
 * `config/modules/<module>.yaml`. Messaging consumes every tagged adapter
 * through `MessagingSubjectResolverRegistry` (`!tagged_iterator
 * messaging.subject_resolver`), never depending on a provider module's
 * Domain or Infrastructure directly.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MessagingSubjectResolverPort
{
  // #region Methods
  /**
   * Method supports.
   *
   * @since 1.0.0
   *
   * @param MessagingSubjectType $type the subject type value
   *
   * @return bool true when this adapter resolves the given subject type
   */
  public function supports(MessagingSubjectType $type): bool;

  /**
   * Method resolve.
   *
   * Resolves whether the subject exists in the given organization, its
   * display label, and the permission required to read it. Implementations
   * must never throw: an unknown or foreign-organization subject resolves
   * to `exists: false`.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the requesting organization identifier
   * @param string $subjectId the subject identifier
   *
   * @return MessagingSubjectResolution the resolution result
   */
  public function resolve(string $organizationId, string $subjectId): MessagingSubjectResolution;
  // #endregion
}
