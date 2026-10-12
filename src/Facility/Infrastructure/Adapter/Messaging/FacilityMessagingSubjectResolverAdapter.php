<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Messaging;

use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Messaging\Application\Contract\Subject\{MessagingSubjectDescriptor, MessagingSubjectResolution};
use Messaging\Application\Port\Outbound\MessagingSubjectResolverPort;

/**
 * Adapter FacilityMessagingSubjectResolverAdapter.
 *
 * Implements Messaging's `MessagingSubjectResolverPort` for `facility`
 * subjects — the cross-module tagged-iterator seam copied from
 * `intervention.resource_owner`.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityMessagingSubjectResolverAdapter implements MessagingSubjectResolverPort
{
  // #region Constants
  /**
   * Constant REQUIRED_READ_PERMISSION.
   *
   * Permission required to read facility details through Messaging.
   *
   * @access private
   */
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
  /**
   * Method descriptor.
   *
   * Publishes the stable subject metadata and required read permission.
   *
   * @access public
   *
   * @return MessagingSubjectDescriptor the owner-published subject descriptor
   */
  public function descriptor(): MessagingSubjectDescriptor
  {
    return new MessagingSubjectDescriptor('facility', 'Facility', 'facilities', self::REQUIRED_READ_PERMISSION);
  }

  /**
   * Method resolve.
   *
   * Resolves a published facility within the organization for messaging access checks.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   * @param string $subjectId the facility identifier
   *
   * @return MessagingSubjectResolution the existence, label, and required permission
   */
  public function resolve(string $organizationId, string $subjectId): MessagingSubjectResolution
  {
    $record = $this->entityManager->find(FacilityRecord::class, $subjectId);

    $exists = $record instanceof FacilityRecord
      && $record->organizationId === $organizationId
      && 'published' === $record->recordStatus;

    return new MessagingSubjectResolution(
      exists: $exists,
      label: $exists ? $record->name : null,
      requiredReadPermission: self::REQUIRED_READ_PERMISSION,
    );
  }
  // #endregion
}
