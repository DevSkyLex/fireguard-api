<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Messaging;

use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Messaging\Application\Contract\Subject\MessagingSubjectResolution;
use Messaging\Application\Port\Outbound\MessagingSubjectResolverPort;
use Messaging\Domain\ValueObject\MessagingSubjectType;

/**
 * Adapter FacilityMessagingSubjectResolverAdapter.
 *
 * Implements Messaging's `MessagingSubjectResolverPort` for `facility`
 * subjects — the cross-module tagged-iterator seam copied from
 * `intervention.resource_owner`.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityMessagingSubjectResolverAdapter implements MessagingSubjectResolverPort
{
  // #region Constants
  private const string REQUIRED_READ_PERMISSION = 'organization.facilities.read';
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
    return MessagingSubjectType::FACILITY === $type;
  }

  public function resolve(string $organizationId, string $subjectId): MessagingSubjectResolution
  {
    $record = $this->entityManager->find(FacilityRecord::class, $subjectId);

    $exists = $record instanceof FacilityRecord
      && $record->organization?->id === $organizationId
      && 'published' === $record->recordStatus;

    return new MessagingSubjectResolution(
      exists: $exists,
      label: $exists ? $record->name : null,
      requiredReadPermission: self::REQUIRED_READ_PERMISSION,
    );
  }
  // #endregion
}
