<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Infrastructure\Adapter\Intervention;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Adapter\Intervention\InterventionInspectionResultAdapter;
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Class InterventionInspectionResultAdapterTest
 *
 * Proof retains the recorded instant and never crosses organization or intervention scope.
 *
 * @category Test
 */
final class InterventionInspectionResultAdapterTest extends TestCase
{
  #[Test]
  public function exposesRecordedFactWithinBothScopes(): void
  {
    $record = $this->record();
    $em = $this->createMock(EntityManagerInterface::class);
    $em->expects(self::once())->method('find')->with(InspectionRecord::class, 'inspection')->willReturn($record);
    $result = new InterventionInspectionResultAdapter($em)->find('organization', 'intervention', 'inspection');
    self::assertNotNull($result);
    self::assertSame('equipment', $result->equipmentId);
    self::assertSame('fail', $result->result);
    self::assertSame('closed', $result->status);
    self::assertSame($record->performedAt, $result->performedAt);
    self::assertSame('inspector', $result->authorId);
    self::assertSame('Defect observed', $result->notes);
  }

  #[Test]
  public function refusesInspectionFromAnotherOrganization(): void
  {
    $em = $this->createMock(EntityManagerInterface::class);
    $em->expects(self::once())->method('find')->willReturn($this->record());
    self::assertNull(new InterventionInspectionResultAdapter($em)->find('foreign', 'intervention', 'inspection'));
  }

  #[Test]
  public function refusesInspectionFromAnotherIntervention(): void
  {
    $em = $this->createMock(EntityManagerInterface::class);
    $em->expects(self::once())->method('find')->willReturn($this->record());
    self::assertNull(new InterventionInspectionResultAdapter($em)->find('organization', 'foreign', 'inspection'));
  }

  #[Test]
  public function unknownInspectionReturnsSameNull(): void
  {
    $em = $this->createMock(EntityManagerInterface::class);
    $em->expects(self::once())->method('find')->willReturn(null);
    self::assertNull(new InterventionInspectionResultAdapter($em)->find('organization', 'intervention', 'unknown'));
  }

  private function record(): InspectionRecord
  {
    $org = new OrganizationRecord();
    $org->id = 'organization';
    $inspection = new InspectionRecord();
    $inspection->id = 'inspection';
    $inspection->organization = $org;
    $inspection->interventionId = 'intervention';
    $inspection->equipmentId = 'equipment';
    $inspection->status = 'closed';
    $inspection->result = 'fail';
    $inspection->performedAt = new DateTimeImmutable('2026-10-05T14:30:00Z');
    $inspection->inspectorUserId = 'inspector';
    $inspection->notes = 'Defect observed';
    $inspection->recordStatus = 'draft';

    return $inspection;
  }
}
