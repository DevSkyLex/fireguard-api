<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\DataFixtures;

use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\{Fixture, FixtureGroupInterface};
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Equipment\Infrastructure\DataFixtures\EquipmentFixtures;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Facility\Infrastructure\DataFixtures\FacilityFixtures;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Intervention\Domain\ValueObject\InterventionStatus;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionActivityRecord, InterventionAttachmentRecord, InterventionChangeRecord, InterventionLabelRecord, InterventionNumberCounterRecord, InterventionRecord, InterventionRecurrenceRecord, InterventionRecurrenceRunRecord, InterventionTemplateItemRecord, InterventionTemplateRecord, InterventionWorkItemRecord, PublicationRecord};
use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationRecord};
use Shared\Infrastructure\DataFixtures\{SeedTimeline, SeedUuid};

use function array_values;
use function count;
use function explode;
use function sprintf;

/**
 * DataFixtures InterventionFixtures.
 *
 * Seeds the intervention workspace end to end: labels, reusable templates,
 * twelve interventions spanning **every** {@see InterventionStatus} case with
 * their work items, proposed/applied changes, publication attempts, activity
 * feed and attachments, plus the recurrence schedules that materialize new
 * drafts.
 *
 * Nothing else seeds this module, so without these rows the intervention
 * board, the "my interventions" list, the activity feed, the publication
 * history and the recurrence screen all render empty — and the calendar feed
 * loses one of its three sources (`CalendarFeedAggregator`).
 *
 * Two consistency rules the data respects, because the runtime enforces them
 * and a contradictory seed would be a landmine:
 *
 * - every seeded status is reachable from `draft` through
 *   {@see \Intervention\Domain\Service\InterventionTransitionPolicy}, and the
 *   activity feed replays exactly that path;
 * - proposed changes exist only where field work is active (`in_progress`,
 *   `changes_requested`), per
 *   {@see \Intervention\Domain\Service\InterventionChangePolicy}; a published
 *   intervention carries `applied` ones instead.
 *
 * **On the shape of the seed tables.** Everything below is a flat list of
 * scalar-only rows joined on `interventionNumber` rather than one nested
 * structure per intervention. That is deliberate: PHPStan widens a constant
 * array as soon as its shape nests, and every offset read then degrades to a
 * union of every value type in the table. Flat rows keep the shapes exact and
 * the analysis honest.
 *
 * @category DataFixtures
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
  // #region Constants
  public const string PUBLISHED_INTERVENTION_REFERENCE = 'intervention-seed-published';

  public const string IN_PROGRESS_INTERVENTION_REFERENCE = 'intervention-seed-in-progress';

  public const string SUBMITTED_INTERVENTION_REFERENCE = 'intervention-seed-submitted';

  public const string CHANGES_REQUESTED_INTERVENTION_REFERENCE = 'intervention-seed-changes-requested';

  public const string DRAFT_INTERVENTION_REFERENCE = 'intervention-seed-draft';

  public const string MONTHLY_TEMPLATE_REFERENCE = 'intervention-seed-monthly-template';

  public const string REGIONAL_AUDIT_TEMPLATE_REFERENCE = 'intervention-seed-regional-audit-template';

  public const string SITE_OPENING_TEMPLATE_REFERENCE = 'intervention-seed-site-opening-template';

  public const string LABEL_REGULATORY_REFERENCE = 'intervention-seed-label-regulatory';

  public const string LABEL_FIELD_WORK_REFERENCE = 'intervention-seed-label-field-work';

  public const string LABEL_QUICK_WIN_REFERENCE = 'intervention-seed-label-quick-win';

  public const string LABEL_VENDOR_REFERENCE = 'intervention-seed-label-vendor';

  public const string LABEL_BLOCKED_REFERENCE = 'intervention-seed-label-blocked';

  /**
   * Constant LABEL_SEEDS.
   *
   * Organization-scoped `{name, color}` tags assigned on top of the workflow
   * status.
   *
   * @since 1.0.0
   *
   * @var list<array{reference: string, id: string, name: string, color: string}>
   */
  public const array LABEL_SEEDS = [
    ['reference' => self::LABEL_REGULATORY_REFERENCE, 'id' => 'be17737a-1c21-4430-9c9b-118a402ed21a', 'name' => 'regulatory', 'color' => '#dc2626'],
    ['reference' => self::LABEL_FIELD_WORK_REFERENCE, 'id' => '30605399-0a10-43fc-a5b6-75358adcb2a7', 'name' => 'field-work', 'color' => '#2563eb'],
    ['reference' => self::LABEL_QUICK_WIN_REFERENCE, 'id' => 'df719d26-4507-478e-892b-d07b92d9c117', 'name' => 'quick-win', 'color' => '#16a34a'],
    ['reference' => self::LABEL_VENDOR_REFERENCE, 'id' => '34a0dadf-db12-49dc-8ba3-e512c05eb169', 'name' => 'vendor', 'color' => '#c026d3'],
    ['reference' => self::LABEL_BLOCKED_REFERENCE, 'id' => 'db9fb62e-4a9e-43fe-860d-d6e1f1c08609', 'name' => 'blocked', 'color' => '#ea580c'],
  ];

  /**
   * Constant TEMPLATE_SEEDS.
   *
   * Reusable blueprints instantiated into a draft by the workflow gateway,
   * and the backing schedule of the recurrences below. `siteReference` and
   * `responsibleReference` are the defaults copied onto each instantiation;
   * `labelReferences` is a comma-separated list, kept as a string so the row
   * stays scalar (see the class docblock).
   *
   * @since 1.0.0
   *
   * @var list<array{
   *   reference: string,
   *   id: string,
   *   name: string,
   *   description: string,
   *   type: string,
   *   priority: string,
   *   siteReference: string,
   *   responsibleReference: string,
   *   duration: string,
   *   labelReferences: string,
   *   createdAt: string
   * }>
   */
  public const array TEMPLATE_SEEDS = [
    [
      'reference' => self::MONTHLY_TEMPLATE_REFERENCE,
      'id' => '2d1ba0e5-8476-44a0-a5c7-327fa509be3e',
      'name' => 'Monthly extinguisher round',
      'description' => 'Walk every floor, check each extinguisher and log the result.',
      'type' => 'inspection_campaign',
      'priority' => 'normal',
      'siteReference' => FacilityFixtures::SITE_REFERENCE,
      'responsibleReference' => OrganizationFixtures::PARIS_TECHNICIAN_MEMBER_REFERENCE,
      'duration' => 'P7D',
      'labelReferences' => self::LABEL_REGULATORY_REFERENCE . ',' . self::LABEL_FIELD_WORK_REFERENCE,
      'createdAt' => '2026-02-10T09:00:00+00:00',
    ],
    [
      'reference' => self::REGIONAL_AUDIT_TEMPLATE_REFERENCE,
      'id' => 'aaf165bf-0dcf-443d-b225-656c99b437f1',
      'name' => 'Regional site audit',
      'description' => 'Quarterly regulatory sweep of a regional site.',
      'type' => 'inspection_campaign',
      'priority' => 'high',
      'siteReference' => FacilityFixtures::LYON_SITE_REFERENCE,
      'responsibleReference' => OrganizationFixtures::REGIONAL_COORDINATOR_MEMBER_REFERENCE,
      'duration' => 'P14D',
      'labelReferences' => self::LABEL_REGULATORY_REFERENCE,
      'createdAt' => '2026-02-11T09:00:00+00:00',
    ],
    [
      'reference' => self::SITE_OPENING_TEMPLATE_REFERENCE,
      'id' => '025a0948-527a-4612-9134-1304d317cb20',
      'name' => 'New site opening',
      'description' => 'Register the facility tree and commission its first assets.',
      'type' => 'site_setup',
      'priority' => 'high',
      'siteReference' => '',
      'responsibleReference' => OrganizationFixtures::SAFETY_MANAGER_MEMBER_REFERENCE,
      'duration' => 'P30D',
      'labelReferences' => self::LABEL_FIELD_WORK_REFERENCE,
      'createdAt' => '2026-02-12T09:00:00+00:00',
    ],
  ];

  /**
   * Constant TEMPLATE_ITEM_SEEDS.
   *
   * Planned items copied into a draft, in `position` order, when a template
   * is instantiated. `required` is spelled as a string so the row stays
   * homogeneous.
   *
   * @since 1.0.0
   *
   * @var list<array{templateReference: string, action: string, target: string, required: string}>
   */
  public const array TEMPLATE_ITEM_SEEDS = [
    ['templateReference' => self::MONTHLY_TEMPLATE_REFERENCE, 'action' => 'inspection', 'target' => 'Ground floor extinguishers', 'required' => 'yes'],
    ['templateReference' => self::MONTHLY_TEMPLATE_REFERENCE, 'action' => 'inspection', 'target' => 'Upper floor extinguishers', 'required' => 'yes'],
    ['templateReference' => self::MONTHLY_TEMPLATE_REFERENCE, 'action' => 'inventory', 'target' => 'Record missing or expired units', 'required' => 'no'],
    ['templateReference' => self::REGIONAL_AUDIT_TEMPLATE_REFERENCE, 'action' => 'inspection', 'target' => 'Alarm and detection loop', 'required' => 'yes'],
    ['templateReference' => self::REGIONAL_AUDIT_TEMPLATE_REFERENCE, 'action' => 'inspection', 'target' => 'Sprinkler coverage', 'required' => 'yes'],
    ['templateReference' => self::REGIONAL_AUDIT_TEMPLATE_REFERENCE, 'action' => 'inspection', 'target' => 'Emergency lighting autonomy', 'required' => 'yes'],
    ['templateReference' => self::REGIONAL_AUDIT_TEMPLATE_REFERENCE, 'action' => 'inventory', 'target' => 'Reconcile the asset register', 'required' => 'no'],
    ['templateReference' => self::SITE_OPENING_TEMPLATE_REFERENCE, 'action' => 'site_setup', 'target' => 'Declare buildings, floors and zones', 'required' => 'yes'],
    ['templateReference' => self::SITE_OPENING_TEMPLATE_REFERENCE, 'action' => 'inventory', 'target' => 'Register the delivered equipment', 'required' => 'yes'],
    ['templateReference' => self::SITE_OPENING_TEMPLATE_REFERENCE, 'action' => 'inspection', 'target' => 'Commissioning inspection', 'required' => 'yes'],
  ];

  /**
   * Constant INTERVENTION_SEEDS.
   *
   * The twelve seeded interventions, ordered by number, covering every
   * status, every type and every priority.
   *
   * `statusPath` is the comma-separated transition chain from `draft` to the
   * seeded status; the activity feed is replayed from it, so a seed that
   * skipped a legal transition would show up as a gap in the feed. `revision`
   * counts the mutations the runtime would have applied, and matters because
   * a publication is keyed on `(intervention, revision)`.
   *
   * Empty strings stand in for "no value" on the nullable columns, so every
   * row stays a flat map of strings and ints.
   *
   * @since 1.0.0
   *
   * @var list<array{
   *   reference: string,
   *   id: string,
   *   number: int,
   *   type: string,
   *   name: string,
   *   description: string,
   *   status: string,
   *   priority: string,
   *   siteReference: string,
   *   responsibleReference: string,
   *   participantReferences: string,
   *   labelReferences: string,
   *   statusPath: string,
   *   reviewNote: string,
   *   revision: int,
   *   createdAt: string,
   *   plannedStartOffsetDays: int,
   *   dueOffsetDays: int
   * }>
   */
  public const array INTERVENTION_SEEDS = [
    [
      'reference' => self::PUBLISHED_INTERVENTION_REFERENCE,
      'id' => '55bf2608-95c7-406b-98c5-a28e38b9ed8c',
      'number' => 1,
      'type' => 'inspection_campaign',
      'name' => 'Paris HQ Q1 inspection campaign',
      'description' => 'Full sweep of the Paris headquarters ahead of the Q1 compliance report.',
      'status' => 'published',
      'priority' => 'normal',
      'siteReference' => FacilityFixtures::SITE_REFERENCE,
      'responsibleReference' => OrganizationFixtures::OWNER_MEMBER_REFERENCE,
      'participantReferences' => OrganizationFixtures::SAFETY_MANAGER_MEMBER_REFERENCE . ',' . OrganizationFixtures::PARIS_TECHNICIAN_MEMBER_REFERENCE,
      'labelReferences' => self::LABEL_REGULATORY_REFERENCE,
      'statusPath' => 'planned,in_progress,submitted,published',
      'reviewNote' => '',
      'revision' => 6,
      'createdAt' => '2026-02-16T08:00:00+00:00',
      'plannedStartOffsetDays' => -58,
      'dueOffsetDays' => -44,
    ],
    [
      'reference' => 'intervention-seed-lyon-setup',
      'id' => 'ab5af3ae-c80d-42ba-ba7d-3fc35fce7f0f',
      'number' => 2,
      'type' => 'site_setup',
      'name' => 'Lyon hub initial site setup',
      'description' => 'Declare the Lyon facility tree and commission the delivered equipment.',
      'status' => 'published',
      'priority' => 'high',
      'siteReference' => FacilityFixtures::LYON_SITE_REFERENCE,
      'responsibleReference' => OrganizationFixtures::REGIONAL_COORDINATOR_MEMBER_REFERENCE,
      'participantReferences' => OrganizationFixtures::FIELD_TECHNICIAN_MEMBER_REFERENCE . ',' . OrganizationFixtures::WAREHOUSE_LEAD_MEMBER_REFERENCE,
      'labelReferences' => self::LABEL_FIELD_WORK_REFERENCE,
      'statusPath' => 'planned,in_progress,submitted,published',
      'reviewNote' => '',
      'revision' => 5,
      'createdAt' => '2026-02-18T08:00:00+00:00',
      'plannedStartOffsetDays' => -55,
      'dueOffsetDays' => -35,
    ],
    [
      'reference' => self::SUBMITTED_INTERVENTION_REFERENCE,
      'id' => '6a439bd2-6a60-4877-b08f-b69d7887b8cc',
      'number' => 3,
      'type' => 'inventory',
      'name' => 'Marseille depot annual inventory',
      'description' => 'Annual reconciliation of the Marseille asset register.',
      'status' => 'submitted',
      'priority' => 'normal',
      'siteReference' => FacilityFixtures::MARSEILLE_SITE_REFERENCE,
      'responsibleReference' => OrganizationFixtures::WAREHOUSE_LEAD_MEMBER_REFERENCE,
      'participantReferences' => OrganizationFixtures::FIELD_TECHNICIAN_MEMBER_REFERENCE,
      'labelReferences' => self::LABEL_FIELD_WORK_REFERENCE,
      'statusPath' => 'planned,in_progress,submitted',
      'reviewNote' => '',
      'revision' => 4,
      'createdAt' => '2026-03-09T08:00:00+00:00',
      'plannedStartOffsetDays' => -21,
      'dueOffsetDays' => -3,
    ],
    [
      'reference' => self::IN_PROGRESS_INTERVENTION_REFERENCE,
      'id' => '575f0ca6-11d9-46a2-8988-93d67c10784a',
      'number' => 4,
      'type' => 'inspection_campaign',
      'name' => 'Floor 2 detector replacement campaign',
      'description' => 'Replace the drifting gas and heat detectors flagged by the April audit.',
      'status' => 'in_progress',
      'priority' => 'urgent',
      'siteReference' => FacilityFixtures::FLOOR_TWO_REFERENCE,
      'responsibleReference' => OrganizationFixtures::PARIS_TECHNICIAN_MEMBER_REFERENCE,
      'participantReferences' => OrganizationFixtures::SAFETY_MANAGER_MEMBER_REFERENCE . ',' . OrganizationFixtures::FIELD_TECHNICIAN_MEMBER_REFERENCE,
      'labelReferences' => self::LABEL_REGULATORY_REFERENCE . ',' . self::LABEL_VENDOR_REFERENCE,
      'statusPath' => 'planned,in_progress',
      'reviewNote' => '',
      'revision' => 3,
      'createdAt' => '2026-04-05T08:00:00+00:00',
      'plannedStartOffsetDays' => -5,
      'dueOffsetDays' => 9,
    ],
    [
      'reference' => 'intervention-seed-bordeaux-setup',
      'id' => '9e0c2154-fe30-45a5-8b4e-53d6e8cc4822',
      'number' => 5,
      'type' => 'site_setup',
      'name' => 'Bordeaux training centre setup',
      'description' => 'Commission the burn room and its detection equipment.',
      'status' => 'in_progress',
      'priority' => 'normal',
      'siteReference' => FacilityFixtures::BORDEAUX_SITE_REFERENCE,
      'responsibleReference' => OrganizationFixtures::REGIONAL_COORDINATOR_MEMBER_REFERENCE,
      'participantReferences' => OrganizationFixtures::WAREHOUSE_LEAD_MEMBER_REFERENCE,
      'labelReferences' => self::LABEL_FIELD_WORK_REFERENCE,
      'statusPath' => 'planned,in_progress',
      'reviewNote' => '',
      'revision' => 3,
      'createdAt' => '2026-04-01T08:00:00+00:00',
      'plannedStartOffsetDays' => -9,
      'dueOffsetDays' => 12,
    ],
    [
      'reference' => 'intervention-seed-lille-inventory',
      'id' => 'f2f8ac47-bed2-44e7-a066-01c6984a9ac3',
      'number' => 6,
      'type' => 'inventory',
      'name' => 'Lille depot equipment inventory',
      'description' => 'Scheduled reconciliation of the Lille dispatch zone.',
      'status' => 'planned',
      'priority' => 'low',
      'siteReference' => FacilityFixtures::LILLE_SITE_REFERENCE,
      'responsibleReference' => OrganizationFixtures::WAREHOUSE_LEAD_MEMBER_REFERENCE,
      'participantReferences' => OrganizationFixtures::REGIONAL_COORDINATOR_MEMBER_REFERENCE,
      'labelReferences' => '',
      'statusPath' => 'planned',
      'reviewNote' => '',
      'revision' => 2,
      'createdAt' => '2026-04-10T08:00:00+00:00',
      'plannedStartOffsetDays' => 6,
      'dueOffsetDays' => 20,
    ],
    [
      'reference' => 'intervention-seed-suppression-audit',
      'id' => 'af25cd9f-3496-4df4-adee-5dd6030b35f2',
      'number' => 7,
      'type' => 'inspection_campaign',
      'name' => 'Server room suppression audit',
      'description' => 'External audit of the server room suppression system.',
      'status' => 'planned',
      'priority' => 'high',
      'siteReference' => FacilityFixtures::AREA_REFERENCE,
      'responsibleReference' => OrganizationFixtures::SAFETY_MANAGER_MEMBER_REFERENCE,
      'participantReferences' => OrganizationFixtures::INSPECTOR_MEMBER_REFERENCE . ',' . OrganizationFixtures::EXTERNAL_AUDITOR_MEMBER_REFERENCE,
      'labelReferences' => self::LABEL_REGULATORY_REFERENCE . ',' . self::LABEL_VENDOR_REFERENCE,
      'statusPath' => 'planned',
      'reviewNote' => '',
      'revision' => 2,
      'createdAt' => '2026-04-11T08:00:00+00:00',
      'plannedStartOffsetDays' => 2,
      'dueOffsetDays' => 16,
    ],
    [
      'reference' => self::CHANGES_REQUESTED_INTERVENTION_REFERENCE,
      'id' => '1f95018c-77a3-425b-94a7-ff8625bb8e67',
      'number' => 8,
      'type' => 'site_setup',
      'name' => 'Zone B alarm panel remediation',
      'description' => 'Fix the Zone 2 supervision loss found during the March audit.',
      'status' => 'changes_requested',
      'priority' => 'urgent',
      'siteReference' => FacilityFixtures::ZONE_B_REFERENCE,
      'responsibleReference' => OrganizationFixtures::PARIS_TECHNICIAN_MEMBER_REFERENCE,
      'participantReferences' => OrganizationFixtures::SAFETY_MANAGER_MEMBER_REFERENCE,
      'labelReferences' => self::LABEL_BLOCKED_REFERENCE,
      'statusPath' => 'planned,in_progress,submitted,changes_requested',
      'reviewNote' => 'The end-of-line resistor replacement is not evidenced; attach the vendor report before resubmitting.',
      'revision' => 5,
      'createdAt' => '2026-03-13T08:00:00+00:00',
      'plannedStartOffsetDays' => -30,
      'dueOffsetDays' => 4,
    ],
    [
      'reference' => 'intervention-seed-fire-door-survey',
      'id' => '1da818d6-9b59-4c26-9028-d6e832d666d5',
      'number' => 9,
      'type' => 'inspection_campaign',
      'name' => 'Storage room fire door survey',
      'description' => 'Survey the storage room fire door flagged as under maintenance.',
      'status' => 'changes_requested',
      'priority' => 'normal',
      'siteReference' => FacilityFixtures::STORAGE_ROOM_REFERENCE,
      'responsibleReference' => OrganizationFixtures::FIELD_TECHNICIAN_MEMBER_REFERENCE,
      'participantReferences' => OrganizationFixtures::PARIS_TECHNICIAN_MEMBER_REFERENCE,
      'labelReferences' => self::LABEL_QUICK_WIN_REFERENCE,
      'statusPath' => 'planned,in_progress,submitted,changes_requested',
      'reviewNote' => 'Photographs of the closer are missing.',
      'revision' => 4,
      'createdAt' => '2026-03-27T08:00:00+00:00',
      'plannedStartOffsetDays' => -16,
      'dueOffsetDays' => 7,
    ],
    [
      'reference' => 'intervention-seed-annex-decommissioning',
      'id' => '9a87fe9c-d559-4c87-8233-976001c2920e',
      'number' => 10,
      'type' => 'site_setup',
      'name' => 'Old annex decommissioning',
      'description' => 'Retire the annex and its remaining equipment before demolition.',
      'status' => 'abandoned',
      'priority' => 'low',
      'siteReference' => FacilityFixtures::ARCHIVED_ANNEX_REFERENCE,
      'responsibleReference' => OrganizationFixtures::SAFETY_MANAGER_MEMBER_REFERENCE,
      'participantReferences' => '',
      'labelReferences' => self::LABEL_BLOCKED_REFERENCE,
      'statusPath' => 'planned,abandoned',
      'reviewNote' => '',
      'revision' => 3,
      'createdAt' => '2026-03-02T08:00:00+00:00',
      'plannedStartOffsetDays' => -41,
      'dueOffsetDays' => -27,
    ],
    [
      'reference' => self::DRAFT_INTERVENTION_REFERENCE,
      'id' => '60b5dada-43d6-45a0-928d-d2fb25a27443',
      'number' => 11,
      'type' => 'inventory',
      'name' => 'Q2 extinguisher recharge round',
      'description' => 'Draft plan for the Q2 recharge round, org-wide and not yet scoped to a site.',
      'status' => 'draft',
      'priority' => 'normal',
      // Deliberately site-less: `site_id` is nullable and an org-wide
      // intervention is the case that exercises it.
      'siteReference' => '',
      'responsibleReference' => OrganizationFixtures::PARIS_TECHNICIAN_MEMBER_REFERENCE,
      'participantReferences' => OrganizationFixtures::FIELD_TECHNICIAN_MEMBER_REFERENCE,
      'labelReferences' => self::LABEL_QUICK_WIN_REFERENCE,
      'statusPath' => '',
      'reviewNote' => '',
      'revision' => 1,
      'createdAt' => '2026-04-15T08:00:00+00:00',
      'plannedStartOffsetDays' => 11,
      'dueOffsetDays' => 25,
    ],
    [
      'reference' => 'intervention-seed-cold-store-rollout',
      'id' => '9b9a8f5d-e020-4313-bd57-a264e8696591',
      'number' => 12,
      'type' => 'site_setup',
      'name' => 'Marseille cold store gas detector rollout',
      'description' => 'Draft scope for adding gas detection to the cold store.',
      'status' => 'draft',
      'priority' => 'high',
      'siteReference' => 'facility-seed-marseille-cold-store',
      'responsibleReference' => OrganizationFixtures::REGIONAL_COORDINATOR_MEMBER_REFERENCE,
      'participantReferences' => OrganizationFixtures::WAREHOUSE_LEAD_MEMBER_REFERENCE,
      'labelReferences' => self::LABEL_VENDOR_REFERENCE,
      'statusPath' => '',
      'reviewNote' => '',
      'revision' => 1,
      'createdAt' => '2026-04-17T08:00:00+00:00',
      'plannedStartOffsetDays' => 15,
      'dueOffsetDays' => 40,
    ],
  ];

  /**
   * Constant WORK_ITEM_SEEDS.
   *
   * The unit of field work. Statuses are kept coherent with the owning
   * intervention: everything is `planned` on a draft, mixed while work is
   * active, and terminal (`completed`/`skipped`) once submitted, published or
   * abandoned — a `skipped` item always carries its reason, as the gateway
   * requires.
   *
   * @since 1.0.0
   *
   * @var list<array{
   *   interventionNumber: string,
   *   action: string,
   *   target: string,
   *   equipmentReference: string,
   *   status: string,
   *   source: string,
   *   required: string,
   *   assigneeReference: string,
   *   skipReason: string
   * }>
   */
  public const array WORK_ITEM_SEEDS = [
    ['interventionNumber' => '1', 'action' => 'inspection', 'target' => 'Zone A extinguishers', 'equipmentReference' => EquipmentFixtures::EXTINGUISHER_REFERENCE, 'status' => 'completed', 'source' => 'planned', 'required' => 'yes', 'assigneeReference' => OrganizationFixtures::PARIS_TECHNICIAN_MEMBER_REFERENCE, 'skipReason' => ''],
    ['interventionNumber' => '1', 'action' => 'inspection', 'target' => 'Server room detection', 'equipmentReference' => EquipmentFixtures::DETECTOR_REFERENCE, 'status' => 'completed', 'source' => 'planned', 'required' => 'yes', 'assigneeReference' => OrganizationFixtures::SAFETY_MANAGER_MEMBER_REFERENCE, 'skipReason' => ''],
    ['interventionNumber' => '1', 'action' => 'inventory', 'target' => 'Reconcile the Paris asset register', 'equipmentReference' => '', 'status' => 'completed', 'source' => 'discovered', 'required' => 'no', 'assigneeReference' => OrganizationFixtures::OWNER_MEMBER_REFERENCE, 'skipReason' => ''],
    ['interventionNumber' => '2', 'action' => 'site_setup', 'target' => 'Declare the warehouse and loading bay', 'equipmentReference' => '', 'status' => 'completed', 'source' => 'planned', 'required' => 'yes', 'assigneeReference' => OrganizationFixtures::REGIONAL_COORDINATOR_MEMBER_REFERENCE, 'skipReason' => ''],
    ['interventionNumber' => '2', 'action' => 'inventory', 'target' => 'Register the delivered equipment', 'equipmentReference' => '', 'status' => 'completed', 'source' => 'planned', 'required' => 'yes', 'assigneeReference' => OrganizationFixtures::WAREHOUSE_LEAD_MEMBER_REFERENCE, 'skipReason' => ''],
    ['interventionNumber' => '2', 'action' => 'inspection', 'target' => 'Commissioning inspection', 'equipmentReference' => '', 'status' => 'completed', 'source' => 'planned', 'required' => 'yes', 'assigneeReference' => OrganizationFixtures::FIELD_TECHNICIAN_MEMBER_REFERENCE, 'skipReason' => ''],
    ['interventionNumber' => '2', 'action' => 'inspection', 'target' => 'Fire door survey', 'equipmentReference' => '', 'status' => 'skipped', 'source' => 'discovered', 'required' => 'no', 'assigneeReference' => OrganizationFixtures::FIELD_TECHNICIAN_MEMBER_REFERENCE, 'skipReason' => 'Door supplier had not finished the installation.'],
    ['interventionNumber' => '3', 'action' => 'inventory', 'target' => 'Warehouse aisles A to D', 'equipmentReference' => '', 'status' => 'completed', 'source' => 'planned', 'required' => 'yes', 'assigneeReference' => OrganizationFixtures::WAREHOUSE_LEAD_MEMBER_REFERENCE, 'skipReason' => ''],
    ['interventionNumber' => '3', 'action' => 'inventory', 'target' => 'Cold store', 'equipmentReference' => '', 'status' => 'completed', 'source' => 'planned', 'required' => 'yes', 'assigneeReference' => OrganizationFixtures::FIELD_TECHNICIAN_MEMBER_REFERENCE, 'skipReason' => ''],
    ['interventionNumber' => '3', 'action' => 'inspection', 'target' => 'Spot-check the flagged units', 'equipmentReference' => '', 'status' => 'skipped', 'source' => 'discovered', 'required' => 'no', 'assigneeReference' => OrganizationFixtures::FIELD_TECHNICIAN_MEMBER_REFERENCE, 'skipReason' => 'Rescheduled with the site manager.'],
    ['interventionNumber' => '4', 'action' => 'inspection', 'target' => 'Gas detector calibration', 'equipmentReference' => EquipmentFixtures::FLOOR_TWO_GAS_DETECTOR_REFERENCE, 'status' => 'completed', 'source' => 'planned', 'required' => 'yes', 'assigneeReference' => OrganizationFixtures::PARIS_TECHNICIAN_MEMBER_REFERENCE, 'skipReason' => ''],
    ['interventionNumber' => '4', 'action' => 'inspection', 'target' => 'Heat detector response test', 'equipmentReference' => EquipmentFixtures::HEAT_DETECTOR_REFERENCE, 'status' => 'in_progress', 'source' => 'planned', 'required' => 'yes', 'assigneeReference' => OrganizationFixtures::FIELD_TECHNICIAN_MEMBER_REFERENCE, 'skipReason' => ''],
    ['interventionNumber' => '4', 'action' => 'inventory', 'target' => 'Order the replacement sensors', 'equipmentReference' => '', 'status' => 'planned', 'source' => 'discovered', 'required' => 'yes', 'assigneeReference' => OrganizationFixtures::SAFETY_MANAGER_MEMBER_REFERENCE, 'skipReason' => ''],
    ['interventionNumber' => '5', 'action' => 'site_setup', 'target' => 'Declare the training block and burn room', 'equipmentReference' => '', 'status' => 'completed', 'source' => 'planned', 'required' => 'yes', 'assigneeReference' => OrganizationFixtures::REGIONAL_COORDINATOR_MEMBER_REFERENCE, 'skipReason' => ''],
    ['interventionNumber' => '5', 'action' => 'inventory', 'target' => 'Register the emergency lighting', 'equipmentReference' => '', 'status' => 'in_progress', 'source' => 'planned', 'required' => 'yes', 'assigneeReference' => OrganizationFixtures::WAREHOUSE_LEAD_MEMBER_REFERENCE, 'skipReason' => ''],
    ['interventionNumber' => '5', 'action' => 'inspection', 'target' => 'Commissioning inspection', 'equipmentReference' => '', 'status' => 'planned', 'source' => 'planned', 'required' => 'yes', 'assigneeReference' => OrganizationFixtures::REGIONAL_COORDINATOR_MEMBER_REFERENCE, 'skipReason' => ''],
    ['interventionNumber' => '6', 'action' => 'inventory', 'target' => 'Depot racks', 'equipmentReference' => '', 'status' => 'planned', 'source' => 'planned', 'required' => 'yes', 'assigneeReference' => OrganizationFixtures::WAREHOUSE_LEAD_MEMBER_REFERENCE, 'skipReason' => ''],
    ['interventionNumber' => '6', 'action' => 'inventory', 'target' => 'Dispatch zone', 'equipmentReference' => '', 'status' => 'planned', 'source' => 'planned', 'required' => 'yes', 'assigneeReference' => OrganizationFixtures::REGIONAL_COORDINATOR_MEMBER_REFERENCE, 'skipReason' => ''],
    ['interventionNumber' => '7', 'action' => 'inspection', 'target' => 'Suppression system discharge test', 'equipmentReference' => '', 'status' => 'planned', 'source' => 'planned', 'required' => 'yes', 'assigneeReference' => OrganizationFixtures::EXTERNAL_AUDITOR_MEMBER_REFERENCE, 'skipReason' => ''],
    ['interventionNumber' => '7', 'action' => 'inspection', 'target' => 'Rack row A gas detection', 'equipmentReference' => '', 'status' => 'planned', 'source' => 'planned', 'required' => 'yes', 'assigneeReference' => OrganizationFixtures::INSPECTOR_MEMBER_REFERENCE, 'skipReason' => ''],
    ['interventionNumber' => '8', 'action' => 'inspection', 'target' => 'Zone 2 loop supervision', 'equipmentReference' => EquipmentFixtures::ALARM_PANEL_REFERENCE, 'status' => 'completed', 'source' => 'planned', 'required' => 'yes', 'assigneeReference' => OrganizationFixtures::PARIS_TECHNICIAN_MEMBER_REFERENCE, 'skipReason' => ''],
    ['interventionNumber' => '8', 'action' => 'site_setup', 'target' => 'Replace the end-of-line resistor', 'equipmentReference' => EquipmentFixtures::ALARM_PANEL_REFERENCE, 'status' => 'in_progress', 'source' => 'planned', 'required' => 'yes', 'assigneeReference' => OrganizationFixtures::PARIS_TECHNICIAN_MEMBER_REFERENCE, 'skipReason' => ''],
    ['interventionNumber' => '8', 'action' => 'inspection', 'target' => 'Remote annunciator re-test', 'equipmentReference' => '', 'status' => 'planned', 'source' => 'discovered', 'required' => 'yes', 'assigneeReference' => OrganizationFixtures::SAFETY_MANAGER_MEMBER_REFERENCE, 'skipReason' => ''],
    ['interventionNumber' => '9', 'action' => 'inspection', 'target' => 'Fire door closer tension', 'equipmentReference' => EquipmentFixtures::BUILDING_FIRE_DOOR_REFERENCE, 'status' => 'completed', 'source' => 'planned', 'required' => 'yes', 'assigneeReference' => OrganizationFixtures::FIELD_TECHNICIAN_MEMBER_REFERENCE, 'skipReason' => ''],
    ['interventionNumber' => '9', 'action' => 'inspection', 'target' => 'Seal and hinge condition', 'equipmentReference' => '', 'status' => 'planned', 'source' => 'planned', 'required' => 'no', 'assigneeReference' => OrganizationFixtures::PARIS_TECHNICIAN_MEMBER_REFERENCE, 'skipReason' => ''],
    ['interventionNumber' => '10', 'action' => 'inventory', 'target' => 'List the remaining assets', 'equipmentReference' => '', 'status' => 'skipped', 'source' => 'planned', 'required' => 'yes', 'assigneeReference' => OrganizationFixtures::SAFETY_MANAGER_MEMBER_REFERENCE, 'skipReason' => 'Demolition brought forward; handled by the contractor.'],
    ['interventionNumber' => '10', 'action' => 'site_setup', 'target' => 'Archive the annex facility', 'equipmentReference' => '', 'status' => 'completed', 'source' => 'planned', 'required' => 'yes', 'assigneeReference' => OrganizationFixtures::SAFETY_MANAGER_MEMBER_REFERENCE, 'skipReason' => ''],
    ['interventionNumber' => '11', 'action' => 'inventory', 'target' => 'List the units due for recharge', 'equipmentReference' => '', 'status' => 'planned', 'source' => 'planned', 'required' => 'yes', 'assigneeReference' => OrganizationFixtures::PARIS_TECHNICIAN_MEMBER_REFERENCE, 'skipReason' => ''],
    ['interventionNumber' => '12', 'action' => 'site_setup', 'target' => 'Position the detector heads', 'equipmentReference' => '', 'status' => 'planned', 'source' => 'planned', 'required' => 'yes', 'assigneeReference' => OrganizationFixtures::REGIONAL_COORDINATOR_MEMBER_REFERENCE, 'skipReason' => ''],
    ['interventionNumber' => '12', 'action' => 'inventory', 'target' => 'Register the new detectors', 'equipmentReference' => '', 'status' => 'planned', 'source' => 'planned', 'required' => 'yes', 'assigneeReference' => OrganizationFixtures::WAREHOUSE_LEAD_MEMBER_REFERENCE, 'skipReason' => ''],
  ];

  /**
   * Constant CHANGE_SEEDS.
   *
   * Proposed edits to a business resource, applied to the real record when
   * the intervention publishes. The patch is spelled as a single
   * `field`/`value` pair, which is all the seeded scenarios need and keeps
   * the row scalar.
   *
   * @since 1.0.0
   *
   * @var list<array{interventionNumber: int, equipmentReference: string, patchField: string, patchValue: string, status: string}>
   */
  public const array CHANGE_SEEDS = [
    ['interventionNumber' => 1, 'equipmentReference' => EquipmentFixtures::EXTINGUISHER_REFERENCE, 'patchField' => 'locationLabel', 'patchValue' => 'Zone A - Corridor (relocated)', 'status' => 'applied'],
    ['interventionNumber' => 4, 'equipmentReference' => EquipmentFixtures::FLOOR_TWO_GAS_DETECTOR_REFERENCE, 'patchField' => 'status', 'patchValue' => 'under_maintenance', 'status' => 'proposed'],
    ['interventionNumber' => 4, 'equipmentReference' => EquipmentFixtures::HEAT_DETECTOR_REFERENCE, 'patchField' => 'locationLabel', 'patchValue' => 'Storage Room - Ceiling (repositioned)', 'status' => 'proposed'],
    ['interventionNumber' => 5, 'equipmentReference' => 'equipment-seed-child-bod-burn', 'patchField' => 'status', 'patchValue' => 'decommissioned', 'status' => 'proposed'],
    ['interventionNumber' => 8, 'equipmentReference' => EquipmentFixtures::ALARM_PANEL_REFERENCE, 'patchField' => 'status', 'patchValue' => 'under_maintenance', 'status' => 'proposed'],
    ['interventionNumber' => 8, 'equipmentReference' => EquipmentFixtures::ALARM_PANEL_REFERENCE, 'patchField' => 'model', 'patchValue' => 'NFS2-3030 (rev B)', 'status' => 'rejected'],
    ['interventionNumber' => 9, 'equipmentReference' => EquipmentFixtures::BUILDING_FIRE_DOOR_REFERENCE, 'patchField' => 'locationLabel', 'patchValue' => 'Main Building - Stairwell A (level 0)', 'status' => 'proposed'],
  ];

  /**
   * Constant PUBLICATION_SEEDS.
   *
   * One publication attempt per row, keyed on the intervention revision it
   * was requested at. The failed attempt on #8 is what left that intervention
   * in `changes_requested`.
   *
   * @since 1.0.0
   *
   * @var list<array{interventionNumber: int, status: string, revision: int, error: string}>
   */
  public const array PUBLICATION_SEEDS = [
    ['interventionNumber' => 1, 'status' => 'completed', 'revision' => 5, 'error' => ''],
    ['interventionNumber' => 2, 'status' => 'completed', 'revision' => 4, 'error' => ''],
    ['interventionNumber' => 3, 'status' => 'pending', 'revision' => 4, 'error' => ''],
    ['interventionNumber' => 8, 'status' => 'failed', 'revision' => 4, 'error' => 'The alarm panel was modified concurrently; re-propose the change.'],
  ];

  /**
   * Constant COMMENT_SEEDS.
   *
   * Member-authored entries of the activity feed. `dayOffset` is relative to
   * "now" so the feed always reads as recent.
   *
   * @since 1.0.0
   *
   * @var list<array{interventionNumber: int, authorReference: string, body: string, dayOffset: int}>
   */
  public const array COMMENT_SEEDS = [
    ['interventionNumber' => 1, 'authorReference' => OrganizationFixtures::PARIS_TECHNICIAN_MEMBER_REFERENCE, 'body' => 'Zone A done, two extinguishers had to be re-signed.', 'dayOffset' => -52],
    ['interventionNumber' => 1, 'authorReference' => OrganizationFixtures::OWNER_MEMBER_REFERENCE, 'body' => 'Report attached, submitting for publication.', 'dayOffset' => -45],
    ['interventionNumber' => 2, 'authorReference' => OrganizationFixtures::WAREHOUSE_LEAD_MEMBER_REFERENCE, 'body' => 'All pallets unpacked, serials recorded.', 'dayOffset' => -48],
    ['interventionNumber' => 3, 'authorReference' => OrganizationFixtures::WAREHOUSE_LEAD_MEMBER_REFERENCE, 'body' => 'Count closed, three units unaccounted for and flagged in the notes.', 'dayOffset' => -4],
    ['interventionNumber' => 4, 'authorReference' => OrganizationFixtures::PARIS_TECHNICIAN_MEMBER_REFERENCE, 'body' => 'Channel A is 12% off, well outside tolerance.', 'dayOffset' => -4],
    ['interventionNumber' => 4, 'authorReference' => OrganizationFixtures::SAFETY_MANAGER_MEMBER_REFERENCE, 'body' => 'Vendor quoted 10 working days for the replacement sensors.', 'dayOffset' => -2],
    ['interventionNumber' => 7, 'authorReference' => OrganizationFixtures::SAFETY_MANAGER_MEMBER_REFERENCE, 'body' => 'Auditor confirmed, access badges requested for the day.', 'dayOffset' => -1],
    ['interventionNumber' => 8, 'authorReference' => OrganizationFixtures::SAFETY_MANAGER_MEMBER_REFERENCE, 'body' => 'Rejecting the model rename, that is a different cabinet.', 'dayOffset' => -12],
    ['interventionNumber' => 8, 'authorReference' => OrganizationFixtures::PARIS_TECHNICIAN_MEMBER_REFERENCE, 'body' => 'Understood, chasing the vendor report now.', 'dayOffset' => -10],
    ['interventionNumber' => 9, 'authorReference' => OrganizationFixtures::PARIS_TECHNICIAN_MEMBER_REFERENCE, 'body' => 'Taking the photos on the next round.', 'dayOffset' => -6],
    ['interventionNumber' => 10, 'authorReference' => OrganizationFixtures::SAFETY_MANAGER_MEMBER_REFERENCE, 'body' => 'Abandoning: the demolition contractor took the remaining scope.', 'dayOffset' => -28],
  ];

  /**
   * Constant ATTACHMENT_SEEDS.
   *
   * Evidence uploaded against an intervention.
   *
   * @since 1.0.0
   *
   * @var list<array{interventionNumber: int, fileName: string, mimeType: string, size: int, label: string}>
   */
  public const array ATTACHMENT_SEEDS = [
    ['interventionNumber' => 1, 'fileName' => 'q1-campaign-report.pdf', 'mimeType' => 'application/pdf', 'size' => 894976, 'label' => 'Campaign report'],
    ['interventionNumber' => 3, 'fileName' => 'marseille-inventory-2026.csv', 'mimeType' => 'text/csv', 'size' => 45056, 'label' => 'Inventory export'],
    ['interventionNumber' => 4, 'fileName' => 'calibration-readings.csv', 'mimeType' => 'text/csv', 'size' => 12288, 'label' => 'Calibration readings'],
    ['interventionNumber' => 4, 'fileName' => 'detector-photo.jpg', 'mimeType' => 'image/jpeg', 'size' => 1310720, 'label' => 'Detector in place'],
    ['interventionNumber' => 8, 'fileName' => 'zone-b-panel-log.txt', 'mimeType' => 'text/plain', 'size' => 6144, 'label' => 'Panel event log extract'],
  ];

  /**
   * Constant RECURRENCE_SEEDS.
   *
   * Schedules the sweep materializes into fresh drafts.
   * `nextOccurrenceOffsetDays` is relative to "now" so one recurrence always
   * sits inside its lead-time window and the "due to materialize" list is
   * never empty.
   *
   * @since 1.0.0
   *
   * @var list<array{
   *   id: string,
   *   templateReference: string,
   *   name: string,
   *   siteReference: string,
   *   responsibleReference: string,
   *   frequency: string,
   *   interval: int,
   *   anchorOffsetDays: int,
   *   leadTimeDays: int,
   *   nextOccurrenceOffsetDays: int,
   *   lastMaterializedOffsetDays: int,
   *   isActive: string
   * }>
   */
  public const array RECURRENCE_SEEDS = [
    [
      'id' => 'f7a9ec11-6955-4c5d-8bb2-f10b0561beb3',
      'templateReference' => self::MONTHLY_TEMPLATE_REFERENCE,
      'name' => 'Monthly extinguisher round — Paris',
      'siteReference' => FacilityFixtures::SITE_REFERENCE,
      'responsibleReference' => OrganizationFixtures::PARIS_TECHNICIAN_MEMBER_REFERENCE,
      'frequency' => 'monthly',
      'interval' => 1,
      'anchorOffsetDays' => -90,
      'leadTimeDays' => 7,
      'nextOccurrenceOffsetDays' => 4,
      'lastMaterializedOffsetDays' => -26,
      'isActive' => 'yes',
    ],
    [
      'id' => '2ebb4d6f-1005-40b7-82ce-5c30fee367a8',
      'templateReference' => self::REGIONAL_AUDIT_TEMPLATE_REFERENCE,
      'name' => 'Quarterly regional audit — Lyon',
      'siteReference' => FacilityFixtures::LYON_SITE_REFERENCE,
      'responsibleReference' => OrganizationFixtures::REGIONAL_COORDINATOR_MEMBER_REFERENCE,
      'frequency' => 'quarterly',
      'interval' => 1,
      'anchorOffsetDays' => -120,
      'leadTimeDays' => 14,
      'nextOccurrenceOffsetDays' => 32,
      'lastMaterializedOffsetDays' => -30,
      'isActive' => 'yes',
    ],
    [
      'id' => 'bb29ef5d-b50f-4b8c-9763-5133593ec2b6',
      'templateReference' => self::SITE_OPENING_TEMPLATE_REFERENCE,
      'name' => 'Annual inventory — all sites',
      'siteReference' => '',
      'responsibleReference' => OrganizationFixtures::SAFETY_MANAGER_MEMBER_REFERENCE,
      'frequency' => 'annual',
      'interval' => 1,
      'anchorOffsetDays' => -200,
      'leadTimeDays' => 30,
      'nextOccurrenceOffsetDays' => 165,
      'lastMaterializedOffsetDays' => 0,
      'isActive' => 'no',
    ],
  ];

  /**
   * Constant RECURRENCE_RUN_SEEDS.
   *
   * Materialization attempts, one per occurrence. The failed row is what the
   * recurrence health indicator reads.
   *
   * @since 1.0.0
   *
   * @var list<array{recurrenceId: string, occurrenceOffsetDays: int, status: string, interventionReference: string, error: string}>
   */
  public const array RECURRENCE_RUN_SEEDS = [
    ['recurrenceId' => 'f7a9ec11-6955-4c5d-8bb2-f10b0561beb3', 'occurrenceOffsetDays' => -56, 'status' => 'succeeded', 'interventionReference' => self::PUBLISHED_INTERVENTION_REFERENCE, 'error' => ''],
    ['recurrenceId' => 'f7a9ec11-6955-4c5d-8bb2-f10b0561beb3', 'occurrenceOffsetDays' => -26, 'status' => 'succeeded', 'interventionReference' => self::DRAFT_INTERVENTION_REFERENCE, 'error' => ''],
    ['recurrenceId' => '2ebb4d6f-1005-40b7-82ce-5c30fee367a8', 'occurrenceOffsetDays' => -30, 'status' => 'failed', 'interventionReference' => '', 'error' => 'Template default responsible is no longer an active member.'],
  ];

  /**
   * Constant BULK_INTERVENTION_COUNT.
   *
   * On top of the twelve hand-authored {@see self::INTERVENTION_SEEDS}, a
   * generated pool so the intervention board — list, board and calendar
   * views alike — clears 50 rows and its pagination has more than one page.
   * Deliberately simpler than the hand-authored dozen: one work item, no
   * changes, no publication, no comments. Volume, not narrative.
   *
   * @since 1.2.0
   *
   * @var int
   */
  public const int BULK_INTERVENTION_COUNT = 40;

  /**
   * Constant BULK_STATUS_CYCLE.
   *
   * `changes_requested` and `abandoned` are deliberately excluded: both are
   * already exercised by {@see self::INTERVENTION_SEEDS} and each carries
   * extra invariants (a review note, an early exit from the status path)
   * that would need per-row handling instead of a flat cycle.
   *
   * @since 1.2.0
   *
   * @var list<string>
   */
  private const array BULK_STATUS_CYCLE = ['draft', 'planned', 'in_progress', 'submitted', 'published'];

  /**
   * Constant BULK_STATUS_PATHS.
   *
   * The transition chain from `draft` to each {@see self::BULK_STATUS_CYCLE}
   * case, replayed into the activity feed exactly like
   * {@see self::INTERVENTION_SEEDS}'s `statusPath`.
   *
   * @since 1.2.0
   *
   * @var array<string, string>
   */
  private const array BULK_STATUS_PATHS = [
    'draft' => '',
    'planned' => 'planned',
    'in_progress' => 'planned,in_progress',
    'submitted' => 'planned,in_progress,submitted',
    'published' => 'planned,in_progress,submitted,published',
  ];

  /**
   * Constant BULK_TYPE_CYCLE.
   *
   * @since 1.2.0
   *
   * @var list<string>
   */
  private const array BULK_TYPE_CYCLE = ['site_setup', 'inventory', 'inspection_campaign'];

  /**
   * Constant BULK_PRIORITY_CYCLE.
   *
   * @since 1.2.0
   *
   * @var list<string>
   */
  private const array BULK_PRIORITY_CYCLE = ['low', 'normal', 'high', 'urgent'];

  /**
   * Constant BULK_RESPONSIBLE_REFERENCE_CYCLE.
   *
   * @since 1.2.0
   *
   * @var list<string>
   */
  private const array BULK_RESPONSIBLE_REFERENCE_CYCLE = [
    OrganizationFixtures::OWNER_MEMBER_REFERENCE,
    OrganizationFixtures::SAFETY_MANAGER_MEMBER_REFERENCE,
    OrganizationFixtures::PARIS_TECHNICIAN_MEMBER_REFERENCE,
    OrganizationFixtures::FIELD_TECHNICIAN_MEMBER_REFERENCE,
    OrganizationFixtures::REGIONAL_COORDINATOR_MEMBER_REFERENCE,
    OrganizationFixtures::WAREHOUSE_LEAD_MEMBER_REFERENCE,
  ];
  // #endregion

  // #region Methods
  public static function getGroups(): array
  {
    return ['intervention', 'main-seed'];
  }

  /**
   * @return array<class-string<Fixture>>
   */
  public function getDependencies(): array
  {
    return [
      OrganizationFixtures::class,
      FacilityFixtures::class,
      EquipmentFixtures::class,
    ];
  }

  public function load(ObjectManager $manager): void
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->getReference(OrganizationFixtures::ORGANIZATION_REFERENCE, OrganizationRecord::class);

    $labels = $this->loadLabels($manager, $organization);
    $templates = $this->loadTemplates($manager, $organization, $labels);
    $interventions = $this->loadInterventions($manager, $organization, $labels);
    $this->loadWorkItems($manager, $interventions);
    $this->loadChanges($manager, $interventions);
    $this->loadPublications($manager, $interventions);
    $this->loadAttachments($manager, $interventions);
    $this->loadComments($manager, $organization, $interventions);
    $this->loadBulkInterventions($manager, $organization, $labels);
    $this->loadRecurrences($manager, $organization, $templates);

    // The allocator must sit at (or past) the highest seeded number, or the
    // next runtime creation collides with the unique (organization, number).
    $counter = new InterventionNumberCounterRecord();
    $counter->organizationId = $organization->id;
    $counter->lastNumber = count(self::INTERVENTION_SEEDS) + self::BULK_INTERVENTION_COUNT;
    $manager->persist($counter);

    $manager->flush();
  }

  /**
   * Method loadLabels.
   *
   * @since 1.0.0
   *
   * @param ObjectManager $manager the object manager
   * @param OrganizationRecord $organization the owning organization
   *
   * @return array<string, InterventionLabelRecord> the labels, keyed by fixture reference
   */
  private function loadLabels(ObjectManager $manager, OrganizationRecord $organization): array
  {
    $createdAt = SeedTimeline::at('2026-02-09T09:00:00+00:00');
    $labels = [];

    foreach (self::LABEL_SEEDS as $seed) {
      $label = new InterventionLabelRecord();
      $label->id = $seed['id'];
      $label->organization = $organization;
      $label->name = $seed['name'];
      $label->color = $seed['color'];
      $label->createdAt = $createdAt;
      $label->updatedAt = $createdAt;
      $manager->persist($label);
      $this->addReference($seed['reference'], $label);
      $labels[$seed['reference']] = $label;
    }

    return $labels;
  }

  /**
   * Method loadTemplates.
   *
   * @since 1.0.0
   *
   * @param ObjectManager $manager the object manager
   * @param OrganizationRecord $organization the owning organization
   * @param array<string, InterventionLabelRecord> $labels the labels, keyed by fixture reference
   *
   * @return array<string, InterventionTemplateRecord> the templates, keyed by fixture reference
   */
  private function loadTemplates(ObjectManager $manager, OrganizationRecord $organization, array $labels): array
  {
    $templates = [];

    foreach (self::TEMPLATE_SEEDS as $seed) {
      $createdAt = SeedTimeline::at($seed['createdAt']);

      $labelIds = [];
      foreach ($this->references($seed['labelReferences']) as $labelReference) {
        $labelIds[] = $labels[$labelReference]->id;
      }

      $template = new InterventionTemplateRecord();
      $template->id = $seed['id'];
      $template->organization = $organization;
      $template->name = $seed['name'];
      $template->description = $seed['description'];
      $template->type = $seed['type'];
      $template->priority = $seed['priority'];
      $template->defaultSiteId = '' === $seed['siteReference'] ? null : $this->facilityId($seed['siteReference']);
      $template->defaultResponsibleId = $this->memberId($seed['responsibleReference']);
      $template->duration = $seed['duration'];
      $template->labelIds = $labelIds;
      $template->createdAt = $createdAt;
      $template->updatedAt = $createdAt;
      $manager->persist($template);
      $this->addReference($seed['reference'], $template);
      $templates[$seed['reference']] = $template;
    }

    $positions = [];
    foreach (self::TEMPLATE_ITEM_SEEDS as $index => $seed) {
      $template = $templates[$seed['templateReference']];
      $position = $positions[$seed['templateReference']] ?? 0;
      $positions[$seed['templateReference']] = $position + 1;

      $item = new InterventionTemplateItemRecord();
      $item->id = SeedUuid::from(sprintf('intervention-template-item:%d', $index));
      $item->template = $template;
      $item->position = $position;
      $item->action = $seed['action'];
      $item->target = $seed['target'];
      $item->required = 'yes' === $seed['required'];
      $item->defaultAssigneeId = $template->defaultResponsibleId;
      $manager->persist($item);
    }

    return $templates;
  }

  /**
   * Method loadInterventions.
   *
   * Persists the interventions themselves plus the system half of their
   * activity feed, replayed from `statusPath`.
   *
   * @since 1.0.0
   *
   * @param ObjectManager $manager the object manager
   * @param OrganizationRecord $organization the owning organization
   * @param array<string, InterventionLabelRecord> $labels the labels, keyed by fixture reference
   *
   * @return array<int, InterventionRecord> the interventions, keyed by number
   */
  private function loadInterventions(ObjectManager $manager, OrganizationRecord $organization, array $labels): array
  {
    $interventions = [];
    $activityIndex = 0;
    $updatedAt = SeedTimeline::fromNow(-1, 12);

    foreach (self::INTERVENTION_SEEDS as $seed) {
      $createdAt = SeedTimeline::at($seed['createdAt']);
      $responsibleId = $this->memberId($seed['responsibleReference']);

      $participants = [];
      foreach ($this->references($seed['participantReferences']) as $participantReference) {
        $participants[] = $this->memberId($participantReference);
      }

      $intervention = new InterventionRecord();
      $intervention->id = $seed['id'];
      $intervention->organization = $organization;
      $intervention->type = $seed['type'];
      $intervention->name = $seed['name'];
      $intervention->number = $seed['number'];
      $intervention->description = $seed['description'];
      $intervention->status = $seed['status'];
      $intervention->siteId = '' === $seed['siteReference'] ? null : $this->facilityId($seed['siteReference']);
      $intervention->responsibleId = $responsibleId;
      $intervention->participants = $participants;
      $intervention->priority = $seed['priority'];
      $intervention->plannedStartAt = SeedTimeline::fromNow($seed['plannedStartOffsetDays'], 8);
      $intervention->dueAt = SeedTimeline::fromNow($seed['dueOffsetDays'], 17);
      $intervention->reviewNote = '' === $seed['reviewNote'] ? null : $seed['reviewNote'];
      $intervention->revision = $seed['revision'];
      $intervention->createdAt = $createdAt;
      $intervention->updatedAt = $updatedAt;

      foreach ($this->references($seed['labelReferences']) as $labelReference) {
        $intervention->labels->add($labels[$labelReference]);
      }

      $manager->persist($intervention);
      $this->addReference($seed['reference'], $intervention);
      $interventions[$seed['number']] = $intervention;

      $manager->persist($this->activity(
        SeedUuid::from(sprintf('intervention-activity:%d', $activityIndex++)),
        $intervention,
        $organization,
        $responsibleId,
        'system',
        'created',
        null,
        null,
        $createdAt,
      ));

      $from = InterventionStatus::DRAFT->value;
      foreach ($this->references($seed['statusPath']) as $hop => $to) {
        $manager->persist($this->activity(
          SeedUuid::from(sprintf('intervention-activity:%d', $activityIndex++)),
          $intervention,
          $organization,
          $responsibleId,
          'system',
          'status_changed',
          null,
          ['from' => $from, 'to' => $to],
          $createdAt->modify(sprintf('+%d days', ($hop + 1) * 2)),
        ));
        $from = $to;
      }
    }

    return $interventions;
  }

  /**
   * Method loadWorkItems.
   *
   * @since 1.0.0
   *
   * @param ObjectManager $manager the object manager
   * @param array<int, InterventionRecord> $interventions the interventions, keyed by number
   */
  private function loadWorkItems(ObjectManager $manager, array $interventions): void
  {
    foreach (self::WORK_ITEM_SEEDS as $index => $seed) {
      $intervention = $interventions[(int) $seed['interventionNumber']];

      $workItem = new InterventionWorkItemRecord();
      $workItem->id = SeedUuid::from(sprintf('intervention-work-item:%d', $index));
      $workItem->intervention = $intervention;
      $workItem->action = $seed['action'];
      $workItem->target = $seed['target'];
      $workItem->resultResource = '' === $seed['equipmentReference']
        ? null
        : $this->equipmentIri($seed['equipmentReference']);
      $workItem->assigneeId = $this->memberId($seed['assigneeReference']);
      $workItem->source = $seed['source'];
      $workItem->status = $seed['status'];
      $workItem->required = 'yes' === $seed['required'];
      $workItem->skipReason = '' === $seed['skipReason'] ? null : $seed['skipReason'];
      $workItem->createdAt = $intervention->createdAt;
      $workItem->updatedAt = $intervention->updatedAt;
      $manager->persist($workItem);
    }
  }

  /**
   * Method loadChanges.
   *
   * @since 1.0.0
   *
   * @param ObjectManager $manager the object manager
   * @param array<int, InterventionRecord> $interventions the interventions, keyed by number
   */
  private function loadChanges(ObjectManager $manager, array $interventions): void
  {
    foreach (self::CHANGE_SEEDS as $index => $seed) {
      $intervention = $interventions[$seed['interventionNumber']];

      $change = new InterventionChangeRecord();
      $change->id = SeedUuid::from(sprintf('intervention-change:%d', $index));
      $change->intervention = $intervention;
      $change->resource = $this->equipmentIri($seed['equipmentReference']);
      $change->patch = [$seed['patchField'] => $seed['patchValue']];
      $change->status = $seed['status'];
      $change->createdAt = $intervention->createdAt;
      $change->updatedAt = $intervention->updatedAt;
      $manager->persist($change);
    }
  }

  /**
   * Method loadPublications.
   *
   * @since 1.0.0
   *
   * @param ObjectManager $manager the object manager
   * @param array<int, InterventionRecord> $interventions the interventions, keyed by number
   */
  private function loadPublications(ObjectManager $manager, array $interventions): void
  {
    foreach (self::PUBLICATION_SEEDS as $index => $seed) {
      $intervention = $interventions[$seed['interventionNumber']];

      $publication = new PublicationRecord();
      $publication->id = SeedUuid::from(sprintf('intervention-publication:%d', $index));
      $publication->intervention = $intervention;
      $publication->interventionRevision = $seed['revision'];
      $publication->status = $seed['status'];
      $publication->error = '' === $seed['error'] ? null : $seed['error'];
      $publication->createdAt = $intervention->updatedAt->modify('-2 hours');
      $publication->completedAt = 'completed' === $seed['status']
        ? $intervention->updatedAt->modify('-1 hour')
        : null;
      $manager->persist($publication);
    }
  }

  /**
   * Method loadAttachments.
   *
   * @since 1.0.0
   *
   * @param ObjectManager $manager the object manager
   * @param array<int, InterventionRecord> $interventions the interventions, keyed by number
   */
  private function loadAttachments(ObjectManager $manager, array $interventions): void
  {
    foreach (self::ATTACHMENT_SEEDS as $index => $seed) {
      $intervention = $interventions[$seed['interventionNumber']];

      $attachment = new InterventionAttachmentRecord();
      $attachment->id = SeedUuid::from(sprintf('intervention-attachment:%d', $index));
      $attachment->intervention = $intervention;
      $attachment->fileName = $seed['fileName'];
      $attachment->storagePath = sprintf('/fixtures/intervention/%d/%s', $seed['interventionNumber'], $seed['fileName']);
      $attachment->mimeType = $seed['mimeType'];
      $attachment->size = $seed['size'];
      $attachment->label = $seed['label'];
      $attachment->uploadedAt = $intervention->createdAt->modify('+2 days');
      $manager->persist($attachment);
    }
  }

  /**
   * Method loadComments.
   *
   * @since 1.0.0
   *
   * @param ObjectManager $manager the object manager
   * @param OrganizationRecord $organization the owning organization
   * @param array<int, InterventionRecord> $interventions the interventions, keyed by number
   */
  private function loadComments(ObjectManager $manager, OrganizationRecord $organization, array $interventions): void
  {
    foreach (self::COMMENT_SEEDS as $index => $seed) {
      $manager->persist($this->activity(
        SeedUuid::from(sprintf('intervention-comment:%d', $index)),
        $interventions[$seed['interventionNumber']],
        $organization,
        $this->memberId($seed['authorReference']),
        'comment',
        'comment',
        $seed['body'],
        null,
        SeedTimeline::fromNow($seed['dayOffset'], 14),
      ));
    }
  }

  /**
   * Method loadBulkInterventions.
   *
   * Generates {@see self::BULK_INTERVENTION_COUNT} plain interventions past
   * the twelve hand-authored ones, cycling status/type/priority/responsible
   * so the pool stays varied without hand-typing forty more rows. See the
   * class docblock for why the seed tables above stay flat scalar rows —
   * the same reasoning applies to keeping this generator's constants flat.
   *
   * @since 1.2.0
   *
   * @param ObjectManager $manager the object manager
   * @param OrganizationRecord $organization the owning organization
   * @param array<string, InterventionLabelRecord> $labels the labels, keyed by fixture reference
   */
  private function loadBulkInterventions(ObjectManager $manager, OrganizationRecord $organization, array $labels): void
  {
    $siteReferences = [
      FacilityFixtures::SITE_REFERENCE,
      FacilityFixtures::BUILDING_REFERENCE,
      FacilityFixtures::LYON_SITE_REFERENCE,
      FacilityFixtures::MARSEILLE_SITE_REFERENCE,
      FacilityFixtures::BORDEAUX_SITE_REFERENCE,
      FacilityFixtures::LILLE_SITE_REFERENCE,
    ];
    foreach (FacilityFixtures::REGIONAL_SITE_SEEDS as $seed) {
      $siteReferences[] = $seed['reference'];
    }

    $labelReferences = array_values($labels);
    $startingNumber = count(self::INTERVENTION_SEEDS) + 1;

    for ($i = 0; $i < self::BULK_INTERVENTION_COUNT; ++$i) {
      $number = $startingNumber + $i;
      $status = self::BULK_STATUS_CYCLE[$i % count(self::BULK_STATUS_CYCLE)];
      $type = self::BULK_TYPE_CYCLE[$i % count(self::BULK_TYPE_CYCLE)];
      $priority = self::BULK_PRIORITY_CYCLE[$i % count(self::BULK_PRIORITY_CYCLE)];
      $responsibleReference = self::BULK_RESPONSIBLE_REFERENCE_CYCLE[$i % count(self::BULK_RESPONSIBLE_REFERENCE_CYCLE)];
      $participantReference = OrganizationFixtures::bulkMemberReference($i % OrganizationFixtures::BULK_MEMBER_COUNT);
      $siteReference = $siteReferences[$i % count($siteReferences)];
      $responsibleId = $this->memberId($responsibleReference);
      $createdAt = SeedTimeline::at('2026-03-15T08:00:00+00:00')->modify(sprintf('+%d hours', $i * 6));

      $intervention = new InterventionRecord();
      $intervention->id = SeedUuid::from(sprintf('intervention-bulk:%d', $i));
      $intervention->organization = $organization;
      $intervention->type = $type;
      $intervention->name = sprintf('Bulk seed intervention #%d', $number);
      $intervention->number = $number;
      $intervention->description = sprintf('Generated seed intervention kept deliberately simple to pad the pool (#%d).', $number);
      $intervention->status = $status;
      $intervention->siteId = $this->facilityId($siteReference);
      $intervention->responsibleId = $responsibleId;
      $intervention->participants = [$this->memberId($participantReference)];
      $intervention->priority = $priority;
      $intervention->plannedStartAt = SeedTimeline::fromNow(-30 + ($i % 60), 8);
      $intervention->dueAt = SeedTimeline::fromNow(-10 + ($i % 60), 17);
      $intervention->reviewNote = null;
      $intervention->revision = 1;
      $intervention->createdAt = $createdAt;
      $intervention->updatedAt = $createdAt;
      $intervention->labels->add($labelReferences[$i % count($labelReferences)]);

      $manager->persist($intervention);

      $manager->persist($this->activity(
        SeedUuid::from(sprintf('intervention-bulk-activity-created:%d', $i)),
        $intervention,
        $organization,
        $responsibleId,
        'system',
        'created',
        null,
        null,
        $createdAt,
      ));

      $from = InterventionStatus::DRAFT->value;
      $hop = 0;
      foreach ($this->references(self::BULK_STATUS_PATHS[$status]) as $to) {
        $manager->persist($this->activity(
          SeedUuid::from(sprintf('intervention-bulk-activity-status:%d:%d', $i, $hop)),
          $intervention,
          $organization,
          $responsibleId,
          'system',
          'status_changed',
          null,
          ['from' => $from, 'to' => $to],
          $createdAt->modify(sprintf('+%d hours', ($hop + 1) * 6)),
        ));
        $from = $to;
        ++$hop;
      }

      $workItemStatus = match ($status) {
        'draft', 'planned' => 'planned',
        'in_progress' => 'in_progress',
        default => 'completed',
      };

      $workItem = new InterventionWorkItemRecord();
      $workItem->id = SeedUuid::from(sprintf('intervention-bulk-work-item:%d', $i));
      $workItem->intervention = $intervention;
      $workItem->action = $type;
      $workItem->target = sprintf('Bulk work item for intervention #%d', $number);
      $workItem->resultResource = null;
      $workItem->assigneeId = $responsibleId;
      $workItem->source = 'planned';
      $workItem->status = $workItemStatus;
      $workItem->required = true;
      $workItem->skipReason = null;
      $workItem->createdAt = $createdAt;
      $workItem->updatedAt = $createdAt;
      $manager->persist($workItem);
    }
  }

  /**
   * Method loadRecurrences.
   *
   * @since 1.0.0
   *
   * @param ObjectManager $manager the object manager
   * @param OrganizationRecord $organization the owning organization
   * @param array<string, InterventionTemplateRecord> $templates the templates, keyed by fixture reference
   */
  private function loadRecurrences(ObjectManager $manager, OrganizationRecord $organization, array $templates): void
  {
    $recurrences = [];

    foreach (self::RECURRENCE_SEEDS as $seed) {
      $createdAt = SeedTimeline::fromNow($seed['anchorOffsetDays'], 9);

      $recurrence = new InterventionRecurrenceRecord();
      $recurrence->id = $seed['id'];
      $recurrence->organization = $organization;
      $recurrence->template = $templates[$seed['templateReference']];
      $recurrence->name = $seed['name'];
      $recurrence->siteId = '' === $seed['siteReference'] ? null : $this->facilityId($seed['siteReference']);
      $recurrence->responsibleId = $this->memberId($seed['responsibleReference']);
      $recurrence->frequency = $seed['frequency'];
      $recurrence->interval = $seed['interval'];
      $recurrence->anchorDate = $createdAt;
      $recurrence->timezone = 'Europe/Paris';
      $recurrence->leadTimeDays = $seed['leadTimeDays'];
      $recurrence->nextOccurrenceAt = SeedTimeline::fromNow($seed['nextOccurrenceOffsetDays'], 8);
      $recurrence->lastMaterializedAt = 0 === $seed['lastMaterializedOffsetDays']
        ? null
        : SeedTimeline::fromNow($seed['lastMaterializedOffsetDays'], 8);
      $recurrence->isActive = 'yes' === $seed['isActive'];
      $recurrence->createdAt = $createdAt;
      $recurrence->updatedAt = SeedTimeline::fromNow(-1, 12);
      $manager->persist($recurrence);
      $recurrences[$seed['id']] = $recurrence;
    }

    foreach (self::RECURRENCE_RUN_SEEDS as $index => $seed) {
      $run = new InterventionRecurrenceRunRecord();
      $run->id = SeedUuid::from(sprintf('intervention-recurrence-run:%d', $index));
      $run->recurrence = $recurrences[$seed['recurrenceId']];
      $run->occurrenceDate = SeedTimeline::fromNow($seed['occurrenceOffsetDays'], 0);
      $run->status = $seed['status'];
      $run->interventionId = '' === $seed['interventionReference']
        ? null
        : $this->getReference($seed['interventionReference'], InterventionRecord::class)->id;
      $run->error = '' === $seed['error'] ? null : $seed['error'];
      $run->createdAt = SeedTimeline::fromNow($seed['occurrenceOffsetDays'], 8);
      $manager->persist($run);
    }
  }

  /**
   * Method activity.
   *
   * Builds one append-only activity row.
   *
   * @since 1.0.0
   *
   * @param string $id the activity identifier
   * @param InterventionRecord $intervention the owning intervention
   * @param OrganizationRecord $organization the owning organization
   * @param string $actorId the acting member identifier
   * @param string $kind either `comment` or `system`
   * @param string $event the event name
   * @param ?string $body the comment body, null for system events
   * @param ?array<string, mixed> $payload the structured event data
   * @param DateTimeImmutable $createdAt the instant the entry was written
   *
   * @return InterventionActivityRecord the activity row
   */
  private function activity(
    string $id,
    InterventionRecord $intervention,
    OrganizationRecord $organization,
    string $actorId,
    string $kind,
    string $event,
    ?string $body,
    ?array $payload,
    DateTimeImmutable $createdAt,
  ): InterventionActivityRecord {
    $activity = new InterventionActivityRecord();
    $activity->id = $id;
    $activity->intervention = $intervention;
    $activity->organizationId = $organization->id;
    $activity->actorId = $actorId;
    $activity->kind = $kind;
    $activity->event = $event;
    $activity->body = $body;
    $activity->payload = $payload;
    $activity->createdAt = $createdAt;

    return $activity;
  }

  /**
   * Method references.
   *
   * Splits a comma-separated reference list, tolerating the empty string that
   * stands in for "none".
   *
   * @since 1.0.0
   *
   * @param string $value the comma-separated list
   *
   * @return list<string> the individual references
   */
  private function references(string $value): array
  {
    return '' === $value ? [] : explode(',', $value);
  }

  /**
   * Method memberId.
   *
   * @since 1.0.0
   *
   * @param string $reference the organization member fixture reference
   *
   * @return string the member identifier
   */
  private function memberId(string $reference): string
  {
    return $this->getReference($reference, OrganizationMemberRecord::class)->id;
  }

  /**
   * Method facilityId.
   *
   * @since 1.0.0
   *
   * @param string $reference the facility fixture reference
   *
   * @return string the facility identifier
   */
  private function facilityId(string $reference): string
  {
    return $this->getReference($reference, FacilityRecord::class)->id;
  }

  /**
   * Method equipmentIri.
   *
   * @since 1.0.0
   *
   * @param string $reference the equipment fixture reference
   *
   * @return string the equipment API Platform IRI
   */
  private function equipmentIri(string $reference): string
  {
    return sprintf('/api/equipment/%s', $this->getReference($reference, EquipmentRecord::class)->id);
  }
  // #endregion
}
