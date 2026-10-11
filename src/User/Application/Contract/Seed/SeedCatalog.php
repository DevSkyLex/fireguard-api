<?php

declare(strict_types=1);

namespace User\Application\Contract\Seed;

use Shared\Application\Contract\Seed\SeedUuid;

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
  public const string NOVA_OWNER_REFERENCE = 'user-seed-nova-owner';

  public const string VIGILANCE_OWNER_REFERENCE = 'user-seed-vigilance-owner';

  public const string SAFEGUARD_OWNER_REFERENCE = 'user-seed-safeguard-owner';

  public const string PREVENTION_OWNER_REFERENCE = 'user-seed-prevention-owner';

  public const array STAFF_SEEDS = [
    [
      'reference' => 'user-seed-safety-manager',
      'id' => '1a1891d0-f1a4-4392-8cf5-a6b2604873fc',
      'username' => 'marie_lefevre',
      'email' => 'marie.lefevre@fireguard.local',
      'firstName' => 'Marie',
      'lastName' => 'Lefèvre',
      'status' => 'active',
      'emailVerified' => true,
      'admin' => true,
    ],
    [
      'reference' => 'user-seed-technician-paris',
      'id' => '8a181f64-6590-4365-94c4-14e313badf80',
      'username' => 'thomas_girard',
      'email' => 'thomas.girard@fireguard.local',
      'firstName' => 'Thomas',
      'lastName' => 'Girard',
      'status' => 'active',
      'emailVerified' => true,
      'admin' => false,
    ],
    [
      'reference' => 'user-seed-technician-field',
      'id' => 'd2bbb340-23ce-46e5-be65-7e534970811e',
      'username' => 'sofia_moreau',
      'email' => 'sofia.moreau@fireguard.local',
      'firstName' => 'Sofia',
      'lastName' => 'Moreau',
      'status' => 'active',
      'emailVerified' => true,
      'admin' => false,
    ],
    [
      'reference' => 'user-seed-regional-coordinator',
      'id' => '4a15e7c4-c5ff-4683-8df5-2b7c375cdada',
      'username' => 'karim_benali',
      'email' => 'karim.benali@fireguard.local',
      'firstName' => 'Karim',
      'lastName' => 'Benali',
      'status' => 'active',
      'emailVerified' => true,
      'admin' => false,
    ],
    [
      'reference' => 'user-seed-external-auditor',
      'id' => '287c2f89-c67c-4c2b-8a40-36f46d6e5f5b',
      'username' => 'elena_rossi',
      'email' => 'elena.rossi@safecheck.example',
      'firstName' => 'Elena',
      'lastName' => 'Rossi',
      'status' => 'active',
      'emailVerified' => true,
      'admin' => false,
    ],
    [
      'reference' => 'user-seed-warehouse-lead',
      'id' => 'fd5ca358-2664-4c16-a55e-91d7b13fca87',
      'username' => 'julien_mercier',
      'email' => 'julien.mercier@fireguard.local',
      'firstName' => 'Julien',
      'lastName' => 'Mercier',
      'status' => 'active',
      'emailVerified' => true,
      'admin' => false,
    ],
    [
      'reference' => 'user-seed-departed-technician',
      'id' => '8e334c8d-ab1c-4929-8515-cc750abcc390',
      'username' => 'lucas_petit',
      'email' => 'lucas.petit@fireguard.local',
      'firstName' => 'Lucas',
      'lastName' => 'Petit',
      'status' => 'inactive',
      'emailVerified' => true,
      'admin' => false,
    ],
    [
      'reference' => 'user-seed-new-joiner',
      'id' => '21434c1d-0e91-4c89-a3bf-8f67b2d61f9d',
      'username' => 'nadia_haddad',
      'email' => 'nadia.haddad@fireguard.local',
      'firstName' => 'Nadia',
      'lastName' => 'Haddad',
      'status' => 'pending_verification',
      'emailVerified' => false,
      'admin' => false,
    ],
    [
      'reference' => 'user-seed-locked-account',
      'id' => '9b60e414-c5d3-437b-b394-44446a482e1c',
      'username' => 'victor_blanc',
      'email' => 'victor.blanc@fireguard.local',
      'firstName' => 'Victor',
      'lastName' => 'Blanc',
      'status' => 'locked',
      'emailVerified' => true,
      'admin' => false,
    ],
  ];

  public const int BULK_STAFF_COUNT = 40;

  public static function bulkStaffId(int $index): string
  {
    return SeedUuid::from(sprintf('user-bulk-staff:%d', $index));
  }
}
