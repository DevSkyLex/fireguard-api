<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Service;

use Intervention\Application\Contract\Export\InterventionExportRow;
use Intervention\Presentation\Api\Service\InterventionCsvWriter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function explode;
use function fclose;
use function fopen;
use function rewind;
use function str_getcsv;
use function stream_get_contents;

/**
 * Test InterventionCsvWriterTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionCsvWriter::class)]
final class InterventionCsvWriterTest extends TestCase
{
  #[Test]
  public function testWriteEmitsTheHeaderRowFirst(): void
  {
    $writer = new InterventionCsvWriter();

    $handle = fopen('php://memory', 'w+');
    self::assertNotFalse($handle);

    $writer->write([], $handle);
    rewind($handle);
    $content = (string) stream_get_contents($handle);
    fclose($handle);

    $lines = explode("\n", $content);
    self::assertSame(
      ['id', 'name', 'type', 'status', 'priority', 'facility', 'assignee', 'due_at', 'created_at', 'updated_at'],
      str_getcsv($lines[0], escape: '\\'),
    );
  }

  #[Test]
  public function testWriteFallsBackToTheRawIdentifierWhenTheNameCouldNotBeResolved(): void
  {
    $row = new InterventionExportRow(
      id: 'intervention-1',
      name: 'Fire panel check',
      type: 'inspection_campaign',
      status: 'planned',
      priority: 'high',
      siteId: 'site-1',
      siteName: null,
      responsibleId: 'member-1',
      responsibleName: null,
      dueAt: '2026-09-01T00:00:00+00:00',
      createdAt: '2026-08-01T00:00:00+00:00',
      updatedAt: '2026-08-02T00:00:00+00:00',
    );

    $writer = new InterventionCsvWriter();
    $handle = fopen('php://memory', 'w+');
    self::assertNotFalse($handle);

    $writer->write([$row], $handle);
    rewind($handle);
    $content = (string) stream_get_contents($handle);
    fclose($handle);

    $lines = explode("\n", $content);
    $dataRow = str_getcsv($lines[1], escape: '\\');

    self::assertSame('intervention-1', $dataRow[0]);
    self::assertSame('site-1', $dataRow[5], 'An unresolved facility name must fall back to the raw site id.');
    self::assertSame('member-1', $dataRow[6], 'An unresolved assignee name must fall back to the raw member id.');
  }

  #[Test]
  public function testWriteEmitsAnEmptyCellWhenNoSiteOrResponsibleIsSet(): void
  {
    $row = new InterventionExportRow(
      id: 'intervention-2',
      name: 'Unassigned draft',
      type: 'site_setup',
      status: 'draft',
      priority: 'normal',
      siteId: null,
      siteName: null,
      responsibleId: null,
      responsibleName: null,
      dueAt: null,
      createdAt: '2026-08-01T00:00:00+00:00',
      updatedAt: '2026-08-02T00:00:00+00:00',
    );

    $writer = new InterventionCsvWriter();
    $handle = fopen('php://memory', 'w+');
    self::assertNotFalse($handle);

    $writer->write([$row], $handle);
    rewind($handle);
    $content = (string) stream_get_contents($handle);
    fclose($handle);

    $lines = explode("\n", $content);
    $dataRow = str_getcsv($lines[1], escape: '\\');

    self::assertSame('', $dataRow[5]);
    self::assertSame('', $dataRow[6]);
    self::assertSame('', $dataRow[7]);
  }
}
