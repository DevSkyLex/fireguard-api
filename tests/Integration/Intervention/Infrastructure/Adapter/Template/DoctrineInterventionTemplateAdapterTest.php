<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\Adapter\Template;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Contract\Template\{InterventionTemplateAttributes, InterventionTemplateCreateRequest, InterventionTemplateDefaults};
use Intervention\Application\Contract\Template\{InterventionTemplateCollectionsPatch, InterventionTemplateDefaultsPatch, InterventionTemplateIdentityPatch, InterventionTemplatePlanningPatch, InterventionTemplateUpdateRequest};
use Intervention\Domain\Exception\{InterventionConflictException, InterventionNotFoundException};
use Intervention\Infrastructure\Adapter\Template\DoctrineInterventionTemplateAdapter;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\UuidGeneratorPort;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Throwable;

use function array_map;
use function str_repeat;

/**
 * Test DoctrineInterventionTemplateAdapter.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DoctrineInterventionTemplateAdapter::class)]
final class DoctrineInterventionTemplateAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'aa0e8400-e29b-41d4-a716-446655440001';

  private const string OTHER_ORGANIZATION_ID = 'aa0e8400-e29b-41d4-a716-446655440002';

  private EntityManagerInterface $entityManager;

  private DoctrineInterventionTemplateAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    /** @var UuidGeneratorPort $generator */
    $generator = static::getContainer()->get(UuidGeneratorPort::class);
    $this->adapter = new DoctrineInterventionTemplateAdapter($this->entityManager, new UuidFactory($generator));

    $this->createOrganization(self::ORGANIZATION_ID, 'template-adapter-test');
    $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'template-adapter-other');
    $this->entityManager->flush();
    $this->entityManager->clear();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testCreateThenFindRoundTripsTheTemplateWithItemsInPositionOrder(): void
  {
    $created = $this->adapter->create(new InterventionTemplateCreateRequest(
      self::ORGANIZATION_ID,
      new InterventionTemplateAttributes('Annual audit', 'Yearly fire safety audit', 'inspection_campaign', 'high', 'P14D'),
      new InterventionTemplateDefaults(null, null),
      ['aa0e8400-e29b-41d4-a716-446655440aaa'],
      [
        ['action' => 'Inspect extinguishers', 'target' => null, 'resultResource' => null, 'required' => true, 'defaultAssigneeId' => null],
        ['action' => 'Check alarms', 'target' => null, 'resultResource' => null, 'required' => false, 'defaultAssigneeId' => null],
      ],
    ));
    $this->entityManager->clear();

    self::assertSame(self::ORGANIZATION_ID, $created->organizationId);
    self::assertSame('Annual audit', $created->name);
    self::assertSame('inspection_campaign', $created->type);
    self::assertSame('high', $created->priority);
    self::assertSame('P14D', $created->duration);

    $found = $this->adapter->find($created->id);
    self::assertNotNull($found);
    self::assertSame($created->id, $found->id);
    self::assertSame('Yearly fire safety audit', $found->description);
    self::assertSame(['aa0e8400-e29b-41d4-a716-446655440aaa'], $found->labelIds);
    self::assertCount(2, $found->items);
    $actions = array_map(static fn ($item): string => $item->action, $found->items);
    self::assertSame(['Inspect extinguishers', 'Check alarms'], $actions);
    self::assertSame(0, $found->items[0]->position);
    self::assertSame(1, $found->items[1]->position);
  }

  #[Test]
  public function testListIsScopedToTheOrganizationAndOrderedByNameAscending(): void
  {
    $this->createTemplate('Zeta');
    $this->createTemplate('Alpha');
    // Belongs to another organization: must never appear in the scoped list.
    $this->createTemplate('Foreign', self::OTHER_ORGANIZATION_ID);
    $this->entityManager->clear();

    $page = $this->adapter->list(self::ORGANIZATION_ID, 1, 20);

    self::assertSame(2, $page->total);
    $names = array_map(static fn ($view): string => $view->name, $page->items);
    self::assertSame(['Alpha', 'Zeta'], $names);
  }

  #[Test]
  public function testListFiltersByCaseInsensitiveNameSearch(): void
  {
    $this->createTemplate('Fire Safety Audit');
    $this->createTemplate('Electrical Review');
    $this->entityManager->clear();

    $page = $this->adapter->list(self::ORGANIZATION_ID, 1, 20, 'fire');

    self::assertSame(1, $page->total);
    self::assertCount(1, $page->items);
    self::assertSame('Fire Safety Audit', $page->items[0]->name);
  }

  #[Test]
  public function testUpdateAppliesOnlyProvidedFields(): void
  {
    $created = $this->adapter->create(new InterventionTemplateCreateRequest(
      self::ORGANIZATION_ID,
      new InterventionTemplateAttributes('Draft template', 'Original description', 'site_setup', 'normal', null),
      new InterventionTemplateDefaults(null, null),
      [],
      [],
    ));
    $this->entityManager->clear();

    $updated = $this->adapter->update(new InterventionTemplateUpdateRequest(
      $created->id,
      new InterventionTemplateIdentityPatch('Renamed template', null, null, true, false, false),
      new InterventionTemplatePlanningPatch('high', null, true, false),
      new InterventionTemplateDefaultsPatch(null, null, false, false),
      new InterventionTemplateCollectionsPatch(null, null, false, false),
    ));
    $this->entityManager->clear();

    self::assertSame('Renamed template', $updated->name);
    self::assertSame('high', $updated->priority);
    // Untouched fields must be preserved.
    self::assertSame('Original description', $updated->description);
    self::assertSame('site_setup', $updated->type);

    $found = $this->adapter->find($created->id);
    self::assertNotNull($found);
    self::assertSame('Renamed template', $found->name);
    self::assertSame('Original description', $found->description);
  }

  #[Test]
  public function testDeleteRemovesTheTemplate(): void
  {
    $created = $this->adapter->create(new InterventionTemplateCreateRequest(
      self::ORGANIZATION_ID,
      new InterventionTemplateAttributes('Temporary template', null, 'site_setup', 'normal', null),
      new InterventionTemplateDefaults(null, null),
      [],
      [],
    ));
    $this->entityManager->clear();

    $this->adapter->delete($created->id);
    $this->entityManager->clear();

    self::assertNull($this->adapter->find($created->id));
  }

  #[Test]
  public function testCreateRejectsDuplicateNameWithinTheOrganization(): void
  {
    $this->adapter->create(new InterventionTemplateCreateRequest(
      self::ORGANIZATION_ID,
      new InterventionTemplateAttributes('Unique name', null, 'site_setup', 'normal', null),
      new InterventionTemplateDefaults(null, null),
      [],
      [],
    ));
    $this->entityManager->clear();

    $this->expectException(InterventionConflictException::class);
    $this->adapter->create(new InterventionTemplateCreateRequest(
      self::ORGANIZATION_ID,
      new InterventionTemplateAttributes('Unique name', null, 'site_setup', 'normal', null),
      new InterventionTemplateDefaults(null, null),
      [],
      [],
    ));
  }

  #[Test]
  public function testFindReturnsNullWhenUnknown(): void
  {
    self::assertNull($this->adapter->find('aa0e8400-e29b-41d4-a716-4466554400ff'));
  }

  #[Test]
  public function testCreateRejectsAnUnknownOrganization(): void
  {
    $this->expectException(InterventionNotFoundException::class);

    $this->adapter->create(new InterventionTemplateCreateRequest(
      'aa0e8400-e29b-41d4-a716-4466554400ee',
      new InterventionTemplateAttributes('Orphan template', null, 'site_setup', 'normal', null),
      new InterventionTemplateDefaults(null, null),
      [],
      [],
    ));
  }

  #[Test]
  public function testUpdateRejectsAnUnknownTemplate(): void
  {
    $this->expectException(InterventionNotFoundException::class);

    $this->adapter->update(new InterventionTemplateUpdateRequest(
      'aa0e8400-e29b-41d4-a716-4466554400ff',
      new InterventionTemplateIdentityPatch(null, null, null, false, false, false),
      new InterventionTemplatePlanningPatch(null, null, false, false),
      new InterventionTemplateDefaultsPatch(null, null, false, false),
      new InterventionTemplateCollectionsPatch(null, null, false, false),
    ));
  }

  #[Test]
  public function testDeleteRejectsAnUnknownTemplate(): void
  {
    $this->expectException(InterventionNotFoundException::class);

    $this->adapter->delete('aa0e8400-e29b-41d4-a716-4466554400ff');
  }

  #[Test]
  public function testUpdateAppliesEveryOptionalFieldAndReplacesItems(): void
  {
    $created = $this->adapter->create(new InterventionTemplateCreateRequest(
      self::ORGANIZATION_ID,
      new InterventionTemplateAttributes('Full template', 'Original description', 'site_setup', 'normal', 'P1D'),
      new InterventionTemplateDefaults('aa0e8400-e29b-41d4-a716-4466554401aa', 'aa0e8400-e29b-41d4-a716-4466554401bb'),
      ['aa0e8400-e29b-41d4-a716-4466554401cc'],
      [
        ['action' => 'Old step', 'target' => null, 'resultResource' => null, 'required' => true, 'defaultAssigneeId' => null],
      ],
    ));
    $this->entityManager->clear();

    $updated = $this->adapter->update(new InterventionTemplateUpdateRequest(
      $created->id,
      new InterventionTemplateIdentityPatch(null, null, 'inspection_campaign', false, true, true),
      new InterventionTemplatePlanningPatch(null, 'P30D', false, true),
      new InterventionTemplateDefaultsPatch(null, null, true, true),
      new InterventionTemplateCollectionsPatch(['aa0e8400-e29b-41d4-a716-4466554401dd', 'aa0e8400-e29b-41d4-a716-4466554401ee'], [
        ['action' => 'New step B', 'target' => 'equipment', 'resultResource' => 'inspection', 'required' => false, 'defaultAssigneeId' => 'aa0e8400-e29b-41d4-a716-4466554401ff'],
        ['action' => 'New step A', 'target' => null, 'resultResource' => null, 'required' => true, 'defaultAssigneeId' => null],
      ], true, true),
    ));
    $this->entityManager->clear();

    // Name and priority were not part of the patch, so they survive untouched.
    self::assertSame('Full template', $updated->name);
    self::assertSame('normal', $updated->priority);
    // Every flagged field is applied, including the nulls that clear a value.
    self::assertNull($updated->description);
    self::assertSame('inspection_campaign', $updated->type);
    self::assertNull($updated->defaultSiteId);
    self::assertNull($updated->defaultResponsibleId);
    self::assertSame('P30D', $updated->duration);
    self::assertSame(
      ['aa0e8400-e29b-41d4-a716-4466554401dd', 'aa0e8400-e29b-41d4-a716-4466554401ee'],
      $updated->labelIds,
    );

    $found = $this->adapter->find($created->id);
    self::assertNotNull($found);
    self::assertSame(
      ['New step B', 'New step A'],
      array_map(static fn ($item): string => $item->action, $found->items),
    );
    self::assertSame('equipment', $found->items[0]->target);
    self::assertSame('inspection', $found->items[0]->resultResource);
    self::assertFalse($found->items[0]->required);
    self::assertSame('aa0e8400-e29b-41d4-a716-4466554401ff', $found->items[0]->defaultAssigneeId);
  }

  #[Test]
  public function testUpdateWithNullLabelIdsClearsThemAndDropsAllItems(): void
  {
    $created = $this->adapter->create(new InterventionTemplateCreateRequest(
      self::ORGANIZATION_ID,
      new InterventionTemplateAttributes('Clearable template', null, 'site_setup', 'normal', null),
      new InterventionTemplateDefaults(null, null),
      ['aa0e8400-e29b-41d4-a716-4466554402aa'],
      [
        ['action' => 'Doomed step', 'target' => null, 'resultResource' => null, 'required' => true, 'defaultAssigneeId' => null],
      ],
    ));
    $this->entityManager->clear();

    $updated = $this->adapter->update(new InterventionTemplateUpdateRequest(
      $created->id,
      new InterventionTemplateIdentityPatch(null, null, null, false, false, false),
      new InterventionTemplatePlanningPatch(null, null, false, false),
      new InterventionTemplateDefaultsPatch(null, null, false, false),
      new InterventionTemplateCollectionsPatch(null, null, true, true),
    ));

    self::assertSame([], $updated->labelIds);
    self::assertSame([], $updated->items);
  }

  #[Test]
  public function testFlushRethrowsAFailureThatIsNotTheDuplicateNameConstraint(): void
  {
    // `duration` is a varchar(32); overflowing it fails the flush with a
    // driver error that is not the (organization, name) uniqueness violation,
    // so it must surface unchanged rather than as a domain conflict.
    $this->expectException(Throwable::class);
    $this->expectExceptionMessageMatches('/^(?!A template named).*/');

    $this->adapter->create(new InterventionTemplateCreateRequest(
      self::ORGANIZATION_ID,
      new InterventionTemplateAttributes('Overflowing duration', null, 'site_setup', 'normal', str_repeat('P1D', 40)),
      new InterventionTemplateDefaults(null, null),
      [],
      [],
    ));
  }

  private function createTemplate(string $name, string $organizationId = self::ORGANIZATION_ID): void
  {
    $this->adapter->create(new InterventionTemplateCreateRequest(
      $organizationId,
      new InterventionTemplateAttributes($name, null, 'site_setup', 'normal', null),
      new InterventionTemplateDefaults(null, null),
      [],
      [],
    ));
  }

  private function createOrganization(string $id, string $slug): void
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Template Adapter ' . $slug;
    $organization->slug = $slug;
    $organization->ownerUserId = 'aa0e8400-e29b-41d4-a716-446655449000';
    $organization->createdByUserId = 'aa0e8400-e29b-41d4-a716-446655449000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM intervention_template_items WHERE template_id IN (SELECT id FROM intervention_templates WHERE organization_id IN (:organizationIds))',
      ['organizationIds' => [self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $connection->executeStatement(
      'DELETE FROM intervention_templates WHERE organization_id IN (:organizationIds)',
      ['organizationIds' => [self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id IN (:organizationIds)',
      ['organizationIds' => [self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $this->entityManager->clear();
  }
}
