<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Seed;

use User\Application\Contract\Seed\SeedCatalog as UserFixtures;

use function sprintf;

/**
 * Class SeedCatalog
 *
 * Publishes deterministic demonstration identifiers and metadata required by other seed owners.
 *
 * @category Contract
 */
final class SeedCatalog
{
  public const string ORGANIZATION_REFERENCE = 'organization-seed';

  public const string OWNER_MEMBER_REFERENCE = 'organization-seed-owner-member';

  public const string INSPECTOR_MEMBER_REFERENCE = 'organization-seed-inspector-member';

  public const string SAFETY_MANAGER_MEMBER_REFERENCE = 'organization-seed-safety-manager-member';

  public const string PARIS_TECHNICIAN_MEMBER_REFERENCE = 'organization-seed-paris-technician-member';

  public const string FIELD_TECHNICIAN_MEMBER_REFERENCE = 'organization-seed-field-technician-member';

  public const string REGIONAL_COORDINATOR_MEMBER_REFERENCE = 'organization-seed-regional-coordinator-member';

  public const string EXTERNAL_AUDITOR_MEMBER_REFERENCE = 'organization-seed-external-auditor-member';

  public const string WAREHOUSE_LEAD_MEMBER_REFERENCE = 'organization-seed-warehouse-lead-member';

  public const string ORGANIZATION_ID = '11111111-1111-4111-8111-111111111111';

  public const int BULK_MEMBER_COUNT = UserFixtures::BULK_STAFF_COUNT;

  public static function bulkMemberReference(int $index): string
  {
    return sprintf('organization-seed-bulk-member-%02d', $index);
  }
}
