<?php

declare(strict_types=1);

namespace Tests\Support\Messaging;

use Messaging\Application\Contract\Subject\{MessagingSubjectDescriptor, MessagingSubjectResolution};
use Messaging\Application\Port\Outbound\MessagingSubjectResolverPort;
use Messaging\Application\Service\MessagingSubjectResolverRegistry;

/**
 * Test support MessagingSubjectCatalog.
 *
 * Builds a catalog fixture through the same contributor seam as composition.
 *
 * @category Test Support
 */
final class MessagingSubjectCatalog
{
  // #region Methods
  /**
   * Method create.
   *
   * @return MessagingSubjectResolverRegistry the historical subject catalog fixture
   */
  public static function create(): MessagingSubjectResolverRegistry
  {
    $resolvers = [];
    foreach ([
      new MessagingSubjectDescriptor('facility', 'Facility', 'facilities', 'organization.facilities.read'),
      new MessagingSubjectDescriptor('equipment', 'Equipment', 'equipment', 'organization.equipment.read'),
      new MessagingSubjectDescriptor('intervention', 'Intervention', 'interventions', 'organization.interventions.read'),
      new MessagingSubjectDescriptor('non_conformity', 'Non-conformity', 'non-conformities', 'organization.inspection.read'),
    ] as $descriptor) {
      $resolvers[] = new readonly class ($descriptor) implements MessagingSubjectResolverPort {
        public function __construct(private MessagingSubjectDescriptor $subject)
        {
        }

        public function descriptor(): MessagingSubjectDescriptor
        {
          return $this->subject;
        }

        public function resolve(string $organizationId, string $subjectId): MessagingSubjectResolution
        {
          return new MessagingSubjectResolution(true, null, $this->subject->requiredReadPermission);
        }
      };
    }

    return new MessagingSubjectResolverRegistry($resolvers);
  }
  // #endregion
}
