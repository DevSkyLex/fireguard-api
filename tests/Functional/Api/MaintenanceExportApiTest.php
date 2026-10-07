<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use Customer\Infrastructure\Persistence\Doctrine\Record\CustomerRecord;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionRecord,InterventionTimeEntryRecord,InterventionWorkItemRecord,PublicationRecord};
use MaintenanceCost\Infrastructure\Persistence\Doctrine\Record\{MaintenanceCostSnapshotRecord, MaintenanceExpenseRecord};
use MaintenanceExport\Domain\Model\ExternalReference;
use MaintenanceExport\Domain\ValueObject\ExportArtifact;
use MaintenanceExport\Infrastructure\Persistence\Doctrine\Repository\MaintenanceExportRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord,OrganizationMemberRoleRecord,OrganizationRecord,OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\{KernelBrowser,Test\WebTestCase};

use function array_combine;
use function explode;
use function hash;
use function json_decode;
use function json_encode;
use function str_contains;
use function str_getcsv;
use function strtoupper;
use function substr;
use function trim;

use const JSON_THROW_ON_ERROR;

/**
 * Class MaintenanceExportApiTest
 * Real main PostgreSQL publication, private costs and saved-file reads prove immutable ERP transport.
 *
 * @category Test
 */
final class MaintenanceExportApiTest extends WebTestCase
{
  private const string ORG = '880e8400-e29b-41d4-a716-446655449001';

  private const string ADMIN = '880e8400-e29b-41d4-a716-446655449002';

  private const string WRITER = '880e8400-e29b-41d4-a716-446655449003';

  private const string READER = '880e8400-e29b-41d4-a716-446655449004';

  private const string OUTSIDER = '880e8400-e29b-41d4-a716-446655449005';

  private const string MANAGE_ONLY = '880e8400-e29b-41d4-a716-446655449006';

  private const string WORK = '880e8400-e29b-41d4-a716-446655449011';

  private const string TASK = '880e8400-e29b-41d4-a716-446655449012';

  private const string PUBLICATION = '880e8400-e29b-41d4-a716-446655449013';

  private const string TIME = '880e8400-e29b-41d4-a716-446655449014';

  private const string EQUIPMENT = '880e8400-e29b-41d4-a716-446655449015';

  private const string SITE = '880e8400-e29b-41d4-a716-446655449016';

  private const string CUSTOMER = '880e8400-e29b-41d4-a716-446655449017';

  private const string MEMBER = '880e8400-e29b-41d4-a716-446655449019';

  private const string OP = '880e8400-e29b-41d4-a716-446655449020';

  private const string OP2 = '880e8400-e29b-41d4-a716-446655449021';

  private const string OP3 = '880e8400-e29b-41d4-a716-446655449022';

  private const string OP4 = '880e8400-e29b-41d4-a716-446655449023';

  private const string TASK_B = '880e8400-e29b-41d4-a716-446655449031';

  private const string EQUIPMENT_B = '880e8400-e29b-41d4-a716-446655449032';

  private const string SITE_B = '880e8400-e29b-41d4-a716-446655449033';

  private const string CUSTOMER_B = '880e8400-e29b-41d4-a716-446655449034';

  private const string TIME_B = '880e8400-e29b-41d4-a716-446655449035';

  private const string EXPENSE_B = '880e8400-e29b-41d4-a716-446655449036';

  private string $actor = self::ADMIN;

  #[Test]
  public function retainedJsonCsvAndIdentitySurviveRenamesTimeCorrectionAndImport(): void
  {
    $client = $this->start();
    $export = $this->call($client, 'POST', '/maintenance-exports', $this->input());
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $id = $this->id($export);
    self::assertSame('generated', $export['state']);
    self::assertSame(1, $export['schemaVersion']);
    $json = $this->download($client, $id, 'json');
    $csv = $this->download($client, $id, 'csv');
    self::assertStringContainsString('Site at publication', $json);
    self::assertStringContainsString('Customer at publication', $json);
    self::assertStringContainsString('Extincteur A-01', $json);
    self::assertStringNotContainsString('contacts', $json);
    self::assertStringNotContainsString('amount', $json);
    self::assertStringNotContainsString('currency', $csv);
    self::assertIsArray($export['files']);
    self::assertIsArray($export['files']['json']);
    self::assertSame(hash('sha256', $json), $export['files']['json']['sha256']);
    $this->main()->getConnection()->executeStatement('UPDATE interventions SET name = :name WHERE id = :id', ['name' => 'Live renamed work', 'id' => self::WORK]);
    $this->changeTime(60, 2);
    $confirmed = $this->call($client, 'POST', '/maintenance-exports/' . $id . '/confirm', ['clientOperationId' => self::OP2, 'externalImportReference' => 'ERP-IMPORTED-42'], 1);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame('import_confirmed', $confirmed['state']);
    self::assertSame(2, $confirmed['revision']);
    self::assertSame($json, $this->download($client, $id, 'json'));
    self::assertSame($csv, $this->download($client, $id, 'csv'));
    $upper = $this->input();
    $upper['clientOperationId'] = strtoupper(self::OP);
    $upper['interventionIds'] = [strtoupper(self::WORK)];
    $replay = $this->call($client, 'POST', '/maintenance-exports', $upper);
    self::assertSame(201, $client->getResponse()->getStatusCode());
    self::assertTrue($replay['replayed']);
    self::assertSame($id, $replay['id']);
    self::assertSame('generated', $replay['state'], 'A transport replay returns the exact original receipt metadata.');
    self::assertSame($json, $this->download($client, $id, 'json'));
  }

  #[Test]
  public function genuineCorrectionsAppendCompensationAndCannotRepeatOrFork(): void
  {
    $client = $this->start();
    $original = $this->call($client, 'POST', '/maintenance-exports', $this->input());
    $id = $this->id($original);
    $bytes = $this->download($client, $id, 'json');
    $this->call($client, 'POST', '/maintenance-exports/' . $id . '/adjustments', ['clientOperationId' => self::OP2, 'reason' => 'No real change'], 1);
    self::assertSame(409, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $this->changeTime(60, 2);
    $input = ['clientOperationId' => self::OP2, 'reason' => 'Corrected the validated time journal'];
    $adjusted = $this->call($client, 'POST', '/maintenance-exports/' . $id . '/adjustments', $input, 1);
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $adjustmentId = $this->id($adjusted);
    self::assertSame($id, $adjusted['originalExportId']);
    self::assertSame($id, $adjusted['adjustmentOf']);
    $adjustment = $this->decode($this->download($client, $adjustmentId, 'json'));
    self::assertIsArray($adjustment['rows']);
    self::assertCount(2, $adjustment['rows']);
    self::assertIsArray($adjustment['rows'][0]);
    self::assertIsArray($adjustment['rows'][1]);
    self::assertSame(-30, $adjustment['rows'][0]['minutes']);
    self::assertSame(60, $adjustment['rows'][1]['minutes']);
    $newRowId = $adjustment['rows'][1]['id'];
    $replay = $this->call($client, 'POST', '/maintenance-exports/' . $id . '/adjustments', $input, 0);
    self::assertSame(201, $client->getResponse()->getStatusCode());
    self::assertTrue($replay['replayed']);
    self::assertSame($adjustmentId, $replay['id']);
    $this->call($client, 'POST', '/maintenance-exports/' . $id . '/adjustments', ['clientOperationId' => self::OP3, 'reason' => 'Illegal fork'], 1);
    self::assertSame(409, $client->getResponse()->getStatusCode());
    $this->call($client, 'POST', '/maintenance-exports/' . $adjustmentId . '/adjustments', ['clientOperationId' => self::OP3, 'reason' => 'No second change'], 1);
    self::assertSame(409, $client->getResponse()->getStatusCode());
    $this->changeTime(80, 3);
    $second = $this->call($client, 'POST', '/maintenance-exports/' . $adjustmentId . '/adjustments', ['clientOperationId' => self::OP3, 'reason' => 'Second corrected time fact'], 1);
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $secondJson = $this->decode($this->download($client, $this->id($second), 'json'));
    self::assertIsArray($secondJson['rows']);
    self::assertIsArray($secondJson['rows'][0]);
    self::assertSame($newRowId, $secondJson['rows'][0]['correctionOf']);
    self::assertSame($bytes, $this->download($client, $id, 'json'));
    $work = $this->main()->find(InterventionRecord::class, self::WORK);
    self::assertInstanceOf(InterventionRecord::class, $work);
    $times = $work->closureSnapshot['timeEntries'] ?? null;
    self::assertIsArray($times);
    self::assertIsArray($times[0]);
    self::assertSame(30, $times[0]['minutes'] ?? null);
  }

  #[Test]
  public function missingHistoricalSnapshotBlocksGenerationInsteadOfInventingIdentity(): void
  {
    $client = $this->start(false);
    $sources = $this->call($client, 'GET', '/maintenance-export-sources');
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $members = $sources['member'] ?? $sources['hydra:member'] ?? null;
    self::assertIsArray($members);
    self::assertIsArray($members[0]);
    self::assertSame('snapshot_missing', $members[0]['blockedReason']);
    self::assertFalse($members[0]['ready']);
    self::assertNull($members[0]['site']);
    $this->call($client, 'POST', '/maintenance-exports', $this->input());
    self::assertSame(422, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame(0, $this->main()->getConnection()->fetchOne('SELECT COUNT(*) FROM maintenance_export_documents WHERE organization_id = ?', [self::ORG]));
  }

  #[Test]
  public function internalCostsRemainExplicitlyIncompleteAndRequireIndependentReadAuthority(): void
  {
    $client = $this->start();
    $input = $this->input();
    $input['includeInternalCosts'] = true;
    $export = $this->call($client, 'POST', '/maintenance-exports', $input);
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertFalse($export['costsComplete']);
    self::assertSame(1, $export['incompleteCostCount']);
    $id = $this->id($export);
    $json = $this->download($client, $id, 'json');
    self::assertStringContainsString('"amount":null', $json);
    $this->actor = self::WRITER;
    $this->call($client, 'GET', '/maintenance-exports/' . $id);
    self::assertSame(403, $client->getResponse()->getStatusCode());
    $this->call($client, 'GET', '/maintenance-exports/' . $id . '/files/json');
    self::assertSame(403, $client->getResponse()->getStatusCode());
    $input['clientOperationId'] = self::OP2;
    $this->call($client, 'POST', '/maintenance-exports', $input);
    self::assertSame(403, $client->getResponse()->getStatusCode());
    $listing = $this->call($client, 'GET', '/maintenance-exports');
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertSame(0, $listing['totalItems'] ?? $listing['hydra:totalItems'] ?? null);
  }

  #[Test]
  public function importConfirmationHasDedicatedRightsRevisionAndIdempotentReceipt(): void
  {
    $client = $this->start();
    $export = $this->call($client, 'POST', '/maintenance-exports', $this->input());
    $id = $this->id($export);
    $body = ['clientOperationId' => self::OP2, 'externalImportReference' => 'ERP-file-99'];
    $this->actor = self::WRITER;
    $this->call($client, 'POST', '/maintenance-exports/' . $id . '/confirm', $body, 1);
    self::assertSame(403, $client->getResponse()->getStatusCode());
    $this->actor = self::ADMIN;
    $this->call($client, 'POST', '/maintenance-exports/' . $id . '/confirm', $body);
    self::assertSame(428, $client->getResponse()->getStatusCode());
    $this->call($client, 'POST', '/maintenance-exports/' . $id . '/confirm', $body, 0);
    self::assertSame(412, $client->getResponse()->getStatusCode());
    $confirmed = $this->call($client, 'POST', '/maintenance-exports/' . $id . '/confirm', $body, 1);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $body['clientOperationId'] = strtoupper(self::OP2);
    $replay = $this->call($client, 'POST', '/maintenance-exports/' . $id . '/confirm', $body, 1);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertTrue($replay['replayed']);
    self::assertIsArray($confirmed['confirmation']);
    self::assertIsArray($replay['confirmation']);
    foreach (['clientOperationId', 'externalImportReference', 'actorId', 'confirmedAt'] as $field) {
      self::assertSame($confirmed['confirmation'][$field], $replay['confirmation'][$field]);
    }
    $body['externalImportReference'] = 'Changed-ERP-reference';
    $this->call($client, 'POST', '/maintenance-exports/' . $id . '/confirm', $body, 2);
    self::assertSame(409, $client->getResponse()->getStatusCode());
    $body['clientOperationId'] = self::OP3;
    $this->call($client, 'POST', '/maintenance-exports/' . $id . '/confirm', $body, 2);
    self::assertSame(409, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function sourceDirectoryUsesFrozenTitlesAndLiteralSearch(): void
  {
    $client = $this->start();
    $this->main()->getConnection()->executeStatement('UPDATE interventions SET name = ? WHERE id = ?', ['Renamed live', self::WORK]);
    $found = $this->call($client, 'GET', '/maintenance-export-sources?search=Published%20repair');
    self::assertSame(1, $found['totalItems'] ?? $found['hydra:totalItems'] ?? null);
    $none = $this->call($client, 'GET', '/maintenance-export-sources?search=%25');
    self::assertSame(0, $none['totalItems'] ?? $none['hydra:totalItems'] ?? null);
  }

  #[Test]
  public function managementWithoutReadAndOutsideOrganizationAreDeniedBeforeSourceCapture(): void
  {
    $client = $this->start();
    $this->actor = self::MANAGE_ONLY;
    $this->call($client, 'POST', '/maintenance-exports', $this->input());
    self::assertSame(403, $client->getResponse()->getStatusCode());
    $this->actor = self::READER;
    $this->call($client, 'POST', '/maintenance-exports', $this->input());
    self::assertSame(403, $client->getResponse()->getStatusCode());
    $this->actor = self::OUTSIDER;
    $this->call($client, 'GET', '/maintenance-export-sources');
    self::assertSame(404, $client->getResponse()->getStatusCode());
    $this->call($client, 'POST', '/maintenance-exports', $this->input());
    self::assertSame(404, $client->getResponse()->getStatusCode());
    $this->actor = self::ADMIN;
    $input = $this->input();
    $input['interventionIds'] = [self::OUTSIDER];
    $this->call($client, 'POST', '/maintenance-exports', $input);
    self::assertSame(404, $client->getResponse()->getStatusCode());
    self::assertSame(0, $this->main()->getConnection()->fetchOne('SELECT COUNT(*) FROM maintenance_export_documents WHERE organization_id = ?', [self::ORG]));
  }

  /**
   * Method externalReferenceCorrectionUsesScopedOwnerIdentityAndPreservesOriginalBytes
   *
   * @return void
   */
  #[Test]
  public function externalReferenceCorrectionUsesScopedOwnerIdentityAndPreservesOriginalBytes(): void
  {
    $client = $this->start();
    $path = '/maintenance-export-references/customer/' . self::CUSTOMER;
    $input = ['clientOperationId' => self::OP2, 'system' => 'erp', 'reference' => '=CUSTOMER-001'];
    $first = $this->call($client, 'PATCH', $path, $input, 0);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame(1, $first['revision']);
    $export = $this->call($client, 'POST', '/maintenance-exports', $this->input());
    self::assertSame(201, $client->getResponse()->getStatusCode());
    $id = $this->id($export);
    $json = $this->download($client, $id, 'json');
    $csv = $this->download($client, $id, 'csv');
    self::assertStringContainsString('=CUSTOMER-001', $json);
    self::assertStringContainsString("'=CUSTOMER-001", $csv);
    self::assertStringNotContainsString('private@example.com', $json);
    $input['clientOperationId'] = self::OP3;
    $input['reference'] = 'CUSTOMER-002';
    $updated = $this->call($client, 'PATCH', $path, $input, 1);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertSame(2, $updated['revision']);
    $replay = $this->call($client, 'PATCH', $path, ['clientOperationId' => self::OP2, 'system' => 'erp', 'reference' => '=CUSTOMER-001'], 0);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertTrue($replay['replayed']);
    self::assertSame(1, $replay['revision']);
    $this->call($client, 'PATCH', '/maintenance-export-references/customer/' . self::OUTSIDER, ['clientOperationId' => self::OP4, 'system' => 'erp', 'reference' => 'FOREIGN'], 0);
    self::assertSame(404, $client->getResponse()->getStatusCode());
    $adjusted = $this->call($client, 'POST', '/maintenance-exports/' . $id . '/adjustments', ['clientOperationId' => self::OP4, 'reason' => 'Correct the ERP customer reference'], 1);
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertStringContainsString('CUSTOMER-002', $this->download($client, $this->id($adjusted), 'json'));
    self::assertSame($json, $this->download($client, $id, 'json'));
    self::assertSame($csv, $this->download($client, $id, 'csv'));
  }

  #[Test]
  public function aLateFinancialExpenseIsAnExactNewAdjustmentAndSeparatelyConfirmedImport(): void
  {
    $client = $this->start();
    $input = $this->input();
    $input['includeInternalCosts'] = true;
    $export = $this->call($client, 'POST', '/maintenance-exports', $input);
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $id = $this->id($export);
    $bytes = $this->download($client, $id, 'json');
    $expense = $this->call($client, 'POST', '/interventions/' . self::WORK . '/costs/expenses', ['clientId' => 'erp-late-expense', 'amount' => '12.345678', 'description' => 'Late received service expense', 'incurredAt' => '2026-10-05T13:00:00Z', 'workItemId' => self::TASK]);
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertIsArray($expense['frozen']);
    self::assertNull($expense['frozen']['total']);
    $adjusted = $this->call($client, 'POST', '/maintenance-exports/' . $id . '/adjustments', ['clientOperationId' => self::OP2, 'reason' => 'Include the separately received late expense'], 1);
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $adjustmentId = $this->id($adjusted);
    $json = $this->decode($this->download($client, $adjustmentId, 'json'));
    self::assertIsArray($json['rows']);
    $expenseRows = [];
    foreach ($json['rows'] as $row) {
      self::assertIsArray($row);
      if ('expense' === ($row['kind'] ?? null)) {
        $expenseRows[] = $row;
      }
    }
    self::assertCount(1, $expenseRows);
    self::assertSame('12.345678', $expenseRows[0]['amount']);
    self::assertSame('add', $expenseRows[0]['change']);
    self::assertSame('generated', $adjusted['state']);
    $confirmed = $this->call($client, 'POST', '/maintenance-exports/' . $adjustmentId . '/confirm', ['clientOperationId' => self::OP3, 'externalImportReference' => 'ERP-adjustment-002'], 1);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertSame('import_confirmed', $confirmed['state']);
    self::assertSame($bytes, $this->download($client, $id, 'json'));
    $original = $this->call($client, 'GET', '/maintenance-exports/' . $id);
    self::assertSame('generated', $original['state']);
  }

  #[Test]
  public function skippedTargetCostsRetainTheirOwnJsonCsvReferencesThroughTimeAdjustment(): void
  {
    $client = $this->start();
    $this->addSkippedFinancialTarget();
    $publishedSnapshot = $this->main()->getConnection()->fetchOne('SELECT closure_snapshot FROM interventions WHERE id = ?', [self::WORK]);
    $input = $this->input();
    $input['includeInternalCosts'] = true;
    $export = $this->call($client, 'POST', '/maintenance-exports', $input);
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $id = $this->id($export);
    $json = $this->download($client, $id, 'json');
    $csv = $this->download($client, $id, 'csv');
    $document = $this->decode($json);
    self::assertIsArray($document['rows']);
    self::assertCount(4, $document['rows']);
    $prestations = 0;
    $financialB = [];
    foreach ($document['rows'] as $row) {
      self::assertIsArray($row);
      if ('prestation' === $row['kind']) {
        ++$prestations;
        self::assertSame(self::TASK, $row['workItemId']);
      }
      if (self::TASK_B === $row['workItemId']) {
        $this->assertFinancialTargetB($row);
        self::assertIsString($row['kind']);
        $financialB[$row['kind']] = $row;
      }
    }
    self::assertSame(1, $prestations);
    self::assertCount(2, $financialB);
    self::assertSame('12.345678', $financialB['expense']['amount']);
    self::assertSame('18.000000', $financialB['time']['amount']);
    $csvB = 0;
    foreach ($this->csvRows($csv) as $row) {
      if (self::TASK_B === $row['workItemId']) {
        $this->assertFinancialTargetB($row);
        ++$csvB;
      }
    }
    self::assertSame(2, $csvB);
    $this->main()->getConnection()->executeStatement('UPDATE intervention_time_entries SET minutes = 30, revision = 2 WHERE id = ?', [self::TIME_B]);
    $adjusted = $this->call($client, 'POST', '/maintenance-exports/' . $id . '/adjustments', ['clientOperationId' => self::OP2, 'reason' => 'Correct the time spent on skipped equipment B'], 1);
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $adjustmentId = $this->id($adjusted);
    $adjustment = $this->decode($this->download($client, $adjustmentId, 'json'));
    self::assertIsArray($adjustment['rows']);
    self::assertCount(2, $adjustment['rows']);
    foreach ($adjustment['rows'] as $row) {
      self::assertIsArray($row);
      $this->assertFinancialTargetB($row);
      self::assertSame('time', $row['kind']);
      self::assertSame($financialB['time']['id'], $row['correctionOf']);
      self::assertSame('reverse' === $row['change'] ? '-18.000000' : '30.000000', $row['amount']);
    }
    foreach ($this->csvRows($this->download($client, $adjustmentId, 'csv')) as $row) {
      $this->assertFinancialTargetB($row);
      self::assertSame('time', $row['kind']);
    }
    self::assertSame($json, $this->download($client, $id, 'json'));
    self::assertSame($csv, $this->download($client, $id, 'csv'));
    self::assertSame($publishedSnapshot, $this->main()->getConnection()->fetchOne('SELECT closure_snapshot FROM interventions WHERE id = ?', [self::WORK]));
  }

  /**
   * Method addSkippedFinancialTarget
   *
   * Adds a real second task, independent journal and frozen private contributions to the existing publication.
   *
   * @return void
   */
  private function addSkippedFinancialTarget(): void
  {
    $em = $this->main();
    $now = new DateTimeImmutable('2026-10-05T12:00:00Z');
    $work = $em->find(InterventionRecord::class, self::WORK);
    self::assertInstanceOf(InterventionRecord::class, $work);
    $snapshot = $work->closureSnapshot;
    self::assertIsArray($snapshot);
    self::assertIsArray($snapshot['workItems']);
    self::assertIsArray($snapshot['timeEntries']);
    $identity = ['id' => self::EQUIPMENT_B, 'name' => 'Extincteur B-02', 'assetReference' => 'B-02', 'type' => 'fire_extinguisher', 'brand' => null, 'model' => null, 'serialNumber' => null, 'facilityId' => self::SITE_B, 'site' => ['id' => self::SITE_B, 'name' => 'Site B at publication'], 'customer' => ['id' => self::CUSTOMER_B, 'name' => 'Customer B at publication']];
    $task = ['id' => self::TASK_B, 'action' => 'repair', 'status' => 'skipped', 'target' => '/api/equipment/' . self::EQUIPMENT_B, 'resultResource' => null, 'executionResult' => ['state' => 'skipped', 'reason' => 'Equipment access unavailable'], 'equipmentIdentity' => $identity, 'spentMinutes' => 18, 'evidenceCount' => 0, 'createdAt' => $now->format('c'), 'updatedAt' => $now->format('c')];
    $snapshot['workItems'][] = $task;
    $snapshot['timeEntries'][] = ['id' => self::TIME_B, 'workItemId' => self::TASK_B, 'memberId' => self::MEMBER, 'workedOn' => '2026-10-05', 'minutes' => 18, 'revision' => 1, 'cancelled' => false];
    $work->closureSnapshot = $snapshot;
    $record = new InterventionWorkItemRecord();
    $record->id = self::TASK_B;
    $record->intervention = $work;
    $record->action = 'repair';
    $record->status = 'skipped';
    $record->target = $task['target'];
    $record->executionResult = $task['executionResult'];
    $record->createdAt = $now;
    $record->updatedAt = $now;
    $em->persist($record);
    $time = new InterventionTimeEntryRecord();
    $time->id = self::TIME_B;
    $time->organizationId = self::ORG;
    $time->workItem = $record;
    $time->memberId = self::MEMBER;
    $time->workedOn = '2026-10-05';
    $time->minutes = 18;
    $time->createdBy = self::ADMIN;
    $time->updatedBy = self::ADMIN;
    $time->createdAt = $now;
    $time->updatedAt = $now;
    $em->persist($time);
    $expense = new MaintenanceExpenseRecord();
    $expense->id = self::EXPENSE_B;
    $expense->organizationId = self::ORG;
    $expense->interventionId = self::WORK;
    $expense->workItemId = self::TASK_B;
    $expense->clientId = 'skipped-equipment-expense';
    $expense->amount = '12.345678';
    $expense->currency = 'EUR';
    $expense->description = 'Travel to inaccessible equipment B';
    $expense->incurredAt = $now;
    $expense->createdBy = self::ADMIN;
    $expense->payloadHash = hash('sha256', $expense->clientId);
    $expense->createdAt = $now;
    $em->persist($expense);
    $cost = $em->getRepository(MaintenanceCostSnapshotRecord::class)->findOneBy(['organizationId' => self::ORG, 'interventionId' => self::WORK]);
    self::assertInstanceOf(MaintenanceCostSnapshotRecord::class, $cost);
    $cost->items[0]['allocation'] = ['identityState' => 'captured', 'equipment' => ['id' => self::EQUIPMENT, 'name' => 'Extincteur A-01', 'assetReference' => 'A-01'], 'site' => ['id' => self::SITE, 'name' => 'Site at publication'], 'customer' => ['id' => self::CUSTOMER, 'name' => 'Customer at publication']];
    $allocation = ['identityState' => 'captured', 'equipment' => ['id' => self::EQUIPMENT_B, 'name' => $identity['name'], 'assetReference' => $identity['assetReference']], 'site' => $identity['site'], 'customer' => $identity['customer']];
    $cost->items[] = ['id' => 'time:' . self::TIME_B . ':1', 'kind' => 'time', 'workItemId' => self::TASK_B, 'sourceId' => self::TIME_B, 'sourceRevision' => 1, 'amount' => '18.000000', 'currency' => 'EUR', 'description' => 'Recorded work time', 'occurredAt' => '2026-10-05', 'correctionOf' => null, 'hourlyAmount' => '60.000000', 'rateId' => null, 'equipmentId' => self::EQUIPMENT_B, 'allocation' => $allocation];
    $cost->items[] = ['id' => 'expense:' . self::EXPENSE_B, 'kind' => 'expense', 'workItemId' => self::TASK_B, 'sourceId' => self::EXPENSE_B, 'sourceRevision' => null, 'amount' => $expense->amount, 'currency' => 'EUR', 'description' => $expense->description, 'occurredAt' => $now->format('c'), 'correctionOf' => null, 'hourlyAmount' => null, 'rateId' => null, 'equipmentId' => self::EQUIPMENT_B, 'allocation' => $allocation];
    $cost->knownTotal = '30.345678';
    $em->flush();
    $repository = new MaintenanceExportRepository($em->getConnection());
    $repository->synchronized(self::ORG, function () use ($repository, $now): void {
      foreach (['equipment' => self::EQUIPMENT_B, 'site' => self::SITE_B, 'customer' => self::CUSTOMER_B] as $type => $id) {
        $repository->saveReference(new ExternalReference(ExportArtifact::rowId('target-b-reference-' . $type), self::ORG, 'erp', $type, $id, 'ERP-B-' . $type, 1, $now));
      }
    });
  }

  /**
   * Method assertFinancialTargetB
   *
   * @param array<array-key,mixed> $row JSON or CSV financial contribution
   *
   * @return void
   */
  private function assertFinancialTargetB(array $row): void
  {
    self::assertSame(self::TASK_B, $row['workItemId']);
    self::assertSame(self::EQUIPMENT_B, $row['equipmentId']);
    self::assertSame('Extincteur B-02', $row['equipmentName']);
    self::assertSame('B-02', $row['assetReference']);
    self::assertSame(self::SITE_B, $row['siteId']);
    self::assertSame('Site B at publication', $row['siteName']);
    self::assertSame(self::CUSTOMER_B, $row['customerId']);
    self::assertSame('Customer B at publication', $row['customerName']);
    self::assertSame('ERP-B-equipment', $row['equipmentReference']);
    self::assertSame('ERP-B-site', $row['siteReference']);
    self::assertSame('ERP-B-customer', $row['customerReference']);
  }

  /**
   * Method csvRows
   *
   * @param string $bytes exact saved CSV without multiline fixture fields
   *
   * @return list<array<string,string|null>> parsed financial rows
   */
  private function csvRows(string $bytes): array
  {
    $lines = explode("\r\n", trim($bytes));
    $columns = str_getcsv($lines[0], ',', '"', '');
    $rows = [];
    foreach ($lines as $index => $line) {
      if (0 === $index) {
        continue;
      }
      /** @var list<string> $columns */
      $rows[] = array_combine($columns, str_getcsv($line, ',', '"', ''));
    }

    return $rows;
  }

  /**
   * Method start
   *
   * @param bool $snapshot preserve an actual publication fixture or exercise missing history
   *
   * @return KernelBrowser fixture-owning client
   */
  private function start(bool $snapshot = true): KernelBrowser
  {
    $this->actor = self::ADMIN;
    $client = static::createClient();
    $em = $this->main();
    $now = new DateTimeImmutable('2026-10-05T12:00:00Z');
    $organization = new OrganizationRecord();
    $organization->id = self::ORG;
    $organization->name = 'Exports API';
    $organization->slug = 'exports-api';
    $organization->ownerUserId = self::ADMIN;
    $organization->createdByUserId = self::ADMIN;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $em->persist($organization);
    $customer = new CustomerRecord();
    $customer->id = self::CUSTOMER;
    $customer->organizationId = self::ORG;
    $customer->name = 'Live internal customer';
    $customer->email = 'private@example.com';
    $customer->createdAt = $now;
    $customer->updatedAt = $now;
    $em->persist($customer);
    foreach ([self::ADMIN => ['*'], self::WRITER => ['organization.maintenance_exports.read', 'organization.maintenance_exports.manage'], self::READER => ['organization.maintenance_exports.read'], self::MANAGE_ONLY => ['organization.maintenance_exports.manage', 'organization.maintenance_cost.read']] as $user => $permissions) {
      $member = new OrganizationMemberRecord();
      $member->id = '880e8402' . substr($user, 8);
      $member->organization = $organization;
      $member->userId = $user;
      $member->joinedAt = $now;
      $em->persist($member);
      $role = new OrganizationRoleRecord();
      $role->id = '880e8401' . substr($user, 8);
      $role->organization = $organization;
      $role->name = 'export-' . $user;
      $role->permissions = $permissions;
      $role->createdAt = $now;
      $em->persist($role);
      $assignment = new OrganizationMemberRoleRecord();
      $assignment->member = $member;
      $assignment->role = $role;
      $assignment->assignedAt = $now;
      $em->persist($assignment);
    }
    $work = new InterventionRecord();
    $work->id = self::WORK;
    $work->organization = $organization;
    $work->name = 'Live work';
    $work->number = 1;
    $work->type = 'corrective_maintenance';
    $work->status = 'published';
    $work->revision = 4;
    $work->createdAt = $now;
    $work->updatedAt = $now;
    $entry = ['id' => self::TIME, 'workItemId' => self::TASK, 'memberId' => self::MEMBER, 'workedOn' => '2026-10-05', 'minutes' => 30, 'revision' => 1, 'cancelled' => false];
    $identity = ['id' => self::EQUIPMENT, 'name' => 'Extincteur A-01', 'assetReference' => 'A-01', 'type' => 'fire_extinguisher', 'brand' => null, 'model' => null, 'serialNumber' => null, 'facilityId' => self::SITE, 'site' => ['id' => self::SITE, 'name' => 'Site at publication'], 'customer' => ['id' => self::CUSTOMER, 'name' => 'Customer at publication']];
    $task = ['id' => self::TASK, 'action' => 'repair', 'status' => 'completed', 'target' => '/api/equipment/' . self::EQUIPMENT, 'resultResource' => null, 'executionResult' => ['state' => 'validated', 'equipmentId' => self::EQUIPMENT, 'performedAt' => $now->format('c'), 'outcome' => 'successful', 'workPerformed' => 'Restored extinguisher'], 'equipmentIdentity' => $identity, 'spentMinutes' => 30, 'evidenceCount' => 1, 'createdAt' => $now->format('c'), 'updatedAt' => $now->format('c')];
    $work->closureSnapshot = $snapshot ? ['version' => 2, 'capturedAt' => $now->format('c'), 'revision' => 4, 'interventionId' => self::WORK, 'number' => 1, 'name' => 'Published repair', 'type' => 'corrective_maintenance', 'publicationId' => self::PUBLICATION, 'site' => $identity['site'], 'customer' => $identity['customer'], 'workItems' => [$task], 'timeEntries' => [$entry], 'attachments' => [], 'report' => ['name' => 'Published repair', 'number' => 1, 'type' => 'corrective_maintenance']] : null;
    $em->persist($work);
    $record = new InterventionWorkItemRecord();
    $record->id = self::TASK;
    $record->intervention = $work;
    $record->action = 'repair';
    $record->status = 'completed';
    $record->target = $task['target'];
    $record->executionResult = $task['executionResult'];
    $record->createdAt = $now;
    $record->updatedAt = $now;
    $em->persist($record);
    $time = new InterventionTimeEntryRecord();
    $time->id = self::TIME;
    $time->organizationId = self::ORG;
    $time->workItem = $record;
    $time->memberId = self::MEMBER;
    $time->workedOn = '2026-10-05';
    $time->minutes = 30;
    $time->createdBy = self::ADMIN;
    $time->updatedBy = self::ADMIN;
    $time->createdAt = $now;
    $time->updatedAt = $now;
    $em->persist($time);
    $publication = new PublicationRecord();
    $publication->id = self::PUBLICATION;
    $publication->intervention = $work;
    $publication->interventionRevision = 3;
    $publication->status = 'completed';
    $publication->createdAt = $now;
    $publication->completedAt = $now;
    $em->persist($publication);
    $cost = new MaintenanceCostSnapshotRecord();
    $cost->organizationId = self::ORG;
    $cost->interventionId = self::WORK;
    $cost->publicationId = self::PUBLICATION;
    $cost->interventionRevision = 3;
    $cost->capturedAt = $now->format('c');
    $cost->currency = 'EUR';
    $cost->knownTotal = '0.000000';
    $cost->total = null;
    $cost->complete = false;
    $cost->items = [['id' => 'time:' . self::TIME . ':1', 'kind' => 'time', 'workItemId' => self::TASK, 'sourceId' => self::TIME, 'sourceRevision' => 1, 'amount' => null, 'currency' => 'EUR', 'description' => 'Recorded work time', 'occurredAt' => '2026-10-05', 'correctionOf' => null, 'hourlyAmount' => null, 'rateId' => null, 'equipmentId' => self::EQUIPMENT]];
    $em->persist($cost);
    $em->flush();
    $client->loginUser(new SecurityUser($this->actor, 'exports@example.com', 'hashed', ['ROLE_USER']), 'api');

    return $client;
  }

  /**
   * Method call
   *
   * @param array<string,mixed>|null $body declaration
   *
   * @return array<string,mixed> decoded metadata
   */
  private function call(KernelBrowser &$client, string $method, string $suffix, ?array $body = null, ?int $revision = null): array
  {
    self::ensureKernelShutdown();
    $client = static::createClient();
    $client->disableReboot();
    $client->loginUser(new SecurityUser($this->actor, $this->actor . '@example.com', 'hashed', ['ROLE_USER']), 'api');
    $headers = ['CONTENT_TYPE' => 'PATCH' === $method ? 'application/merge-patch+json' : 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];
    if (null !== $revision) {
      $headers['HTTP_IF_MATCH'] = '"revision-' . $revision . '"';
    }
    $client->request($method, '/api/organizations/' . self::ORG . $suffix, server:$headers, content:null === $body ? null : json_encode($body, JSON_THROW_ON_ERROR));
    if (str_contains($suffix, '/files/') && $client->getResponse()->isSuccessful()) {
      return [];
    }

    return $this->decode((string) $client->getResponse()->getContent());
  }

  /**
   * Method download
   *
   * @return string exact response bytes
   */
  private function download(KernelBrowser &$client, string $id, string $format): string
  {
    $this->call($client, 'GET', '/maintenance-exports/' . $id . '/files/' . $format);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

    return (string) $client->getResponse()->getContent();
  }

  /**
   * Method input
   *
   * @return array<string,mixed> stable selection
   */
  private function input(): array
  {
    return ['clientOperationId' => self::OP, 'interventionIds' => [self::WORK], 'system' => 'erp'];
  }

  /**
   * Method id
   *
   * @param array<string,mixed> $projection metadata
   *
   * @return string retained UUID
   */
  private function id(array $projection): string
  {
    self::assertIsString($projection['id'] ?? null);

    return $projection['id'];
  }

  /**
   * Method decode
   *
   * @return array<string,mixed> JSON object
   */
  private function decode(string $bytes): array
  {
    $data = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
    self::assertIsArray($data);
    $object = [];
    foreach ($data as $key => $value) {
      self::assertIsString($key);
      $object[$key] = $value;
    }

    return $object;
  }

  /**
   * Method changeTime
   *
   * @return void update independent live journal only
   */
  private function changeTime(int $minutes, int $revision): void
  {
    $this->main()->getConnection()->executeStatement('UPDATE intervention_time_entries SET minutes = :minutes, revision = :revision WHERE id = :id', ['minutes' => $minutes, 'revision' => $revision, 'id' => self::TIME]);
  }

  /**
   * Method main
   *
   * @return EntityManagerInterface explicit business manager
   */
  private function main(): EntityManagerInterface
  {
    $em = static::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $em);

    return $em;
  }
}
