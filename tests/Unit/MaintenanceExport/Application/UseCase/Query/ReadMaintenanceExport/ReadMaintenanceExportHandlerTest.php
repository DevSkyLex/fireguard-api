<?php

declare(strict_types=1);

namespace Tests\Unit\MaintenanceExport\Application\UseCase\Query\ReadMaintenanceExport;

use DateTimeImmutable;
use Intervention\Application\Contract\Publication\{InterventionPublicationFacts, InterventionPublicationFactsPage, InterventionPublishedWorkFact};
use Intervention\Application\Port\Inbound\InterventionPublicationFactsPort;
use MaintenanceExport\Application\Port\Outbound\MaintenanceExportRepositoryPort;
use MaintenanceExport\Application\UseCase\Query\ReadMaintenanceExport\{ReadMaintenanceExportHandler, ReadMaintenanceExportQuery};
use MaintenanceExport\Domain\Exception\MaintenanceExportException;
use MaintenanceExport\Domain\Model\ExportDocument;
use MaintenanceExport\Domain\ValueObject\ExportArtifact;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

use function hash;

/**
 * Class ReadMaintenanceExportHandlerTest
 *
 * Source readiness preserves historical availability; private file reads authorize before disclosure.
 *
 * @category UnitTest
 */
final class ReadMaintenanceExportHandlerTest extends TestCase
{
  // #region Constants
  /**
   * Constant ORG
   */
  private const string ORG = 'aa0e8400-e29b-41d4-a716-446655449001';

  /**
   * Constant ACTOR
   */
  private const string ACTOR = 'aa0e8400-e29b-41d4-a716-446655449002';

  /**
   * Constant WORK
   */
  private const string WORK = 'aa0e8400-e29b-41d4-a716-446655449003';

  /**
   * Constant PUBLICATION
   */
  private const string PUBLICATION = 'aa0e8400-e29b-41d4-a716-446655449004';

  /**
   * Constant DOCUMENT
   */
  private const string DOCUMENT = 'aa0e8400-e29b-41d4-a716-446655449005';
  // #endregion

  // #region Methods
  /**
   * Method sourceReadinessPreservesMissingHistoryPrecedence.
   *
   * Checks the published snapshot states and their readiness precedence.
   *
   * @since 1.0.0
   *
   * @param 'available'|'snapshot_missing' $state the retained snapshot state
   * @param string|null $publicationId the retained publication identity
   * @param bool $validated whether the work was validated
   * @param string|null $blocked the expected blocking reason
   *
   * @return void no return value
   */
  #[Test]
  #[DataProvider('sourceAvailability')]
  public function sourceReadinessPreservesMissingHistoryPrecedence(string $state, ?string $publicationId, bool $validated, ?string $blocked): void
  {
    $now = new DateTimeImmutable('2026-10-07T12:00:00Z');
    $work = new InterventionPublishedWorkFact(self::WORK, 'repair', 'completed', null, null, null, null, null, null, null, $validated, 0, 0, $now, $now);
    $source = new InterventionPublicationFacts(self::WORK, self::ORG, 7, 'Équipement = literal', 'corrective_maintenance', 'published', 2, $now, null, null, $now, $publicationId, $state, 2, false, null, null, [$work], null);
    $publications = $this->createMock(InterventionPublicationFactsPort::class);
    $publications->expects(self::once())->method('publishedPage')->with(self::ORG, 2, 4, 'Équipement = literal')->willReturn(new InterventionPublicationFactsPage([$source], 7, 2, 4));
    $repository = $this->createMock(MaintenanceExportRepositoryPort::class);
    $repository->expects(self::never())->method('document');
    $result = new ReadMaintenanceExportHandler($repository, $this->authorization(false), $publications)(new ReadMaintenanceExportQuery(self::ACTOR, self::ORG, 'sources', page:2, itemsPerPage:4, search:'Équipement = literal'));

    self::assertSame('source', $result->kind);
    self::assertSame(7, $result->total);
    self::assertSame(2, $result->page);
    self::assertSame(4, $result->itemsPerPage);
    self::assertTrue($result->collection);
    self::assertSame($blocked, $result->items[0]['blockedReason']);
    self::assertSame(null === $blocked, $result->items[0]['ready']);
    self::assertSame($publicationId, $result->items[0]['publicationId']);
    self::assertSame($state, $result->items[0]['snapshotState']);
    self::assertNull($result->items[0]['site']);
    self::assertNull($result->items[0]['customer']);
    self::assertFalse($result->items[0]['identityComplete']);
    self::assertSame($now->format('c'), $result->items[0]['publishedAt']);
  }

  /**
   * Method sourceAvailability
   *
   * @access public
   *
   * @return iterable<string,array{'available'|'snapshot_missing',string|null,bool,string|null}> retained source availability and expected reason
   */
  public static function sourceAvailability(): iterable
  {
    yield 'ready publication' => ['available', self::PUBLICATION, true, null];
    yield 'missing snapshot despite validated work' => ['snapshot_missing', self::PUBLICATION, true, 'snapshot_missing'];
    yield 'missing publication despite validated work' => ['available', null, true, 'snapshot_missing'];
    yield 'available without validated work' => ['available', self::PUBLICATION, false, 'no_validated_work'];
    yield 'missing snapshot without validated work' => ['snapshot_missing', null, false, 'snapshot_missing'];
  }

  #[Test]
  public function financialDenialPrecedesMalformedFormatAndFileDisclosure(): void
  {
    $repository = $this->createMock(MaintenanceExportRepositoryPort::class);
    $repository->expects(self::once())->method('document')->with(self::ORG, self::DOCUMENT)->willReturn($this->document());
    $publications = $this->createMock(InterventionPublicationFactsPort::class);
    $publications->expects(self::never())->method('publishedPage');
    $handler = new ReadMaintenanceExportHandler($repository, $this->authorization(false), $publications);

    $this->expectException(MaintenanceExportException::class);
    $this->expectExceptionMessage('permission');
    $handler(new ReadMaintenanceExportQuery(self::ACTOR, self::ORG, 'file', self::DOCUMENT, 'pdf'));
  }

  #[Test]
  #[DataProvider('retainedFormats')]
  public function fileReadsReturnTheRetainedBytesAndTheirExactHash(string $format, string $mediaType): void
  {
    $document = $this->document();
    $repository = $this->createMock(MaintenanceExportRepositoryPort::class);
    $repository->expects(self::once())->method('document')->with(self::ORG, self::DOCUMENT)->willReturn($document);
    $publications = $this->createStub(InterventionPublicationFactsPort::class);
    $result = new ReadMaintenanceExportHandler($repository, $this->authorization(true), $publications)(new ReadMaintenanceExportQuery(self::ACTOR, self::ORG, 'file', self::DOCUMENT, $format));
    $bytes = 'json' === $format ? $document->jsonBytes : $document->csvBytes;

    self::assertSame('file', $result->kind);
    self::assertSame($bytes, $result->bytes);
    self::assertSame($mediaType, $result->mediaType);
    self::assertSame('fireguard-maintenance-' . self::DOCUMENT . '.' . $format, $result->fileName);
    self::assertSame(hash('sha256', $bytes), $result->sha256);
  }

  /**
   * Method retainedFormats
   *
   * @access public
   *
   * @return iterable<string,array{string,string}> exact retained file formats
   */
  public static function retainedFormats(): iterable
  {
    yield 'JSON' => ['json', 'application/json'];
    yield 'CSV' => ['csv', 'text/csv'];
  }

  /**
   * Method authorization
   *
   * @access private
   *
   * @param bool $financial independent financial entitlement
   *
   * @return OrganizationAuthorizationPort granted export read with explicit cost visibility
   */
  private function authorization(bool $financial): OrganizationAuthorizationPort
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturnCallback(static fn (string $actor, string $org, string $permission): OrganizationAccessDecision => 'organization.maintenance_cost.read' === $permission && !$financial ? OrganizationAccessDecision::MISSING_PERMISSION : OrganizationAccessDecision::GRANTED);

    return $authorization;
  }

  /**
   * Method document
   *
   * @access private
   *
   * @return ExportDocument original financial bytes with exact decimals and Unicode text
   */
  private function document(): ExportDocument
  {
    $rows = [['id' => 'source-row', 'kind' => 'prestation', 'equipmentName' => 'Équipement, "réserve"', 'amount' => '12.500001']];

    return new ExportDocument(self::DOCUMENT, self::ORG, self::ACTOR, 'initial', 'erp', true, [self::WORK], null, null, null, new DateTimeImmutable('2026-10-07T12:00:00Z'), $rows, ['work' => $rows[0]], ExportArtifact::json(['rows' => $rows]), ExportArtifact::csv($rows, true), true, 0);
  }
  // #endregion
}
