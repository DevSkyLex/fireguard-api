<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Messaging;

use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, NonConformityRecord};
use Messaging\Application\Contract\Subject\MessagingSubjectResolution;
use Messaging\Application\Port\Outbound\MessagingSubjectResolverPort;
use Messaging\Domain\ValueObject\MessagingSubjectType;

use function mb_strimwidth;

/**
 * Adapter InspectionMessagingSubjectResolverAdapter.
 *
 * Implements Messaging's `MessagingSubjectResolverPort` for
 * `non_conformity` subjects — the cross-module tagged-iterator seam copied
 * from `intervention.resource_owner`. Named after the Inspection module
 * (the owning module), not the subject type, mirroring how
 * `Inspection\...\MaintenanceScheduleSynchronizerAdapter` is hosted under
 * the owning module rather than the consuming one.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionMessagingSubjectResolverAdapter implements MessagingSubjectResolverPort
{
  // #region Constants
  /**
   * Constant REQUIRED_READ_PERMISSION
   *
   * Permission required to resolve a non-conformity subject.
   *
   * @access private
   *
   * @var string
   */
  private const string REQUIRED_READ_PERMISSION = 'organization.inspection.read';

  /**
   * Constant LABEL_MAX_LENGTH
   *
   * Maximum width of the subject label returned to Messaging.
   *
   * @access private
   *
   * @var int
   */
  private const int LABEL_MAX_LENGTH = 80;
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
  /**
   * Method supports
   *
   * Handles non-conformity subject references.
   *
   * @access public
   *
   * @param MessagingSubjectType $type subject type to check
   *
   * @return bool whether this adapter supports the type
   */
  public function supports(MessagingSubjectType $type): bool
  {
    return MessagingSubjectType::NON_CONFORMITY === $type;
  }

  /**
   * Method resolve
   *
   * Resolves an organization's non-conformity and its required read permission.
   *
   * @access public
   *
   * @param string $organizationId organization scope
   * @param string $subjectId non-conformity identifier
   *
   * @return MessagingSubjectResolution existence, label and permission projection
   */
  public function resolve(string $organizationId, string $subjectId): MessagingSubjectResolution
  {
    $record = $this->entityManager->find(NonConformityRecord::class, $subjectId);

    $exists = $record instanceof NonConformityRecord
      && $record->inspection instanceof InspectionRecord
      && $record->inspection->organization?->id === $organizationId;

    return new MessagingSubjectResolution(
      exists: $exists,
      label: $exists ? mb_strimwidth($record->description, 0, self::LABEL_MAX_LENGTH, '…') : null,
      requiredReadPermission: self::REQUIRED_READ_PERMISSION,
    );
  }
  // #endregion
}
