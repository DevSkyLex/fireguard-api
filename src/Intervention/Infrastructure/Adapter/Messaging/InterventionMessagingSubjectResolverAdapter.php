<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Messaging;

use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Messaging\Application\Contract\Subject\MessagingSubjectResolution;
use Messaging\Application\Port\Outbound\MessagingSubjectResolverPort;
use Messaging\Domain\ValueObject\MessagingSubjectType;

/**
 * Adapter InterventionMessagingSubjectResolverAdapter.
 *
 * Implements Messaging's `MessagingSubjectResolverPort` for `intervention`
 * subjects — the cross-module tagged-iterator seam copied from
 * `intervention.resource_owner`. Unlike Facility/Equipment, an intervention
 * has no draft/published record split of its own (it IS the workspace), so
 * every existing intervention in the organization is a valid conversation
 * subject regardless of its workflow status.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionMessagingSubjectResolverAdapter implements MessagingSubjectResolverPort
{
  // #region Constants
  private const string REQUIRED_READ_PERMISSION = 'organization.interventions.read';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
  }
  // #endregion

  // #region Methods
  public function supports(MessagingSubjectType $type): bool
  {
    return MessagingSubjectType::INTERVENTION === $type;
  }

  public function resolve(string $organizationId, string $subjectId): MessagingSubjectResolution
  {
    $record = $this->entityManager->find(InterventionRecord::class, $subjectId);

    $exists = $record instanceof InterventionRecord && $record->organization?->id === $organizationId;

    return new MessagingSubjectResolution(
      exists: $exists,
      label: $exists ? $record->name : null,
      requiredReadPermission: self::REQUIRED_READ_PERMISSION,
    );
  }
  // #endregion
}
