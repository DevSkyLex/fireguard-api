<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Messaging;

use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Messaging\Application\Contract\Subject\MessagingSubjectResolution;
use Messaging\Application\Port\Outbound\MessagingSubjectResolverPort;
use Messaging\Domain\ValueObject\MessagingSubjectType;

use function sprintf;

/**
 * Adapter EquipmentMessagingSubjectResolverAdapter.
 *
 * Implements Messaging's `MessagingSubjectResolverPort` for `equipment`
 * subjects — the cross-module tagged-iterator seam copied from
 * `intervention.resource_owner`.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentMessagingSubjectResolverAdapter implements MessagingSubjectResolverPort
{
  // #region Constants
  private const string REQUIRED_READ_PERMISSION = 'organization.equipment.read';
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
    return MessagingSubjectType::EQUIPMENT === $type;
  }

  public function resolve(string $organizationId, string $subjectId): MessagingSubjectResolution
  {
    $record = $this->entityManager->find(EquipmentRecord::class, $subjectId);

    $exists = $record instanceof EquipmentRecord
      && $record->organization?->id === $organizationId
      && 'published' === $record->recordStatus;

    return new MessagingSubjectResolution(
      exists: $exists,
      label: $exists ? self::label($record) : null,
      requiredReadPermission: self::REQUIRED_READ_PERMISSION,
    );
  }

  /**
   * Method label.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param EquipmentRecord $record the equipment record value
   *
   * @return string the display label result
   */
  private static function label(EquipmentRecord $record): string
  {
    return null !== $record->serialNumber && '' !== $record->serialNumber
      ? sprintf('%s (%s)', $record->type, $record->serialNumber)
      : $record->type;
  }
  // #endregion
}
