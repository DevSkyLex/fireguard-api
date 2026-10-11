<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Attachment;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Application\Contract\Attachment\InspectionAttachmentView;
use Inspection\Application\Port\Outbound\InspectionMediaLookupPort;

use function is_string;

/** Loads scalar media snapshots on the main connection.
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DoctrineInspectionMediaLookupAdapter implements InspectionMediaLookupPort
{
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  public function attachment(string $id, bool $lock = false): ?InspectionAttachmentView
  {
    /** @var array{id: string, organization_id: string, inspection_id: string, file_name: string, mime_type: string, size: int|string, label: ?string, revision: int|string, uploaded_at: string, non_conformity_id: ?string}|false $row */
    $row = $this->entityManager->getConnection()->fetchAssociative(
      'SELECT a.id, p.organization_id, a.inspection_id, a.file_name, a.mime_type, a.size, a.label, a.revision, a.uploaded_at, a.non_conformity_id FROM inspection_attachments a INNER JOIN inspections p ON p.id = a.inspection_id WHERE a.id = :id' . ($lock ? ' FOR UPDATE OF a' : ''),
      ['id' => $id],
    );
    if (false === $row) {
      return null;
    }

    return new InspectionAttachmentView(
      id: $row['id'],
      organizationId: $row['organization_id'],
      inspectionId: $row['inspection_id'],
      fileName: $row['file_name'],
      mimeType: $row['mime_type'],
      size: (int) $row['size'],
      label: $row['label'],
      revision: (int) $row['revision'],
      uploadedAt: new DateTimeImmutable($row['uploaded_at'])->format('c'),
      nonConformityId: $row['non_conformity_id'],
    );
  }

  public function organizationId(string $inspectionId): ?string
  {
    $id = $this->entityManager->getConnection()->fetchOne('SELECT organization_id FROM inspections WHERE id = :id', ['id' => $inspectionId]);

    return is_string($id) ? $id : null;
  }

  public function nonConformityScope(string $id): ?array
  {
    /** @var array{inspectionId: string, organizationId: string}|false $row */
    $row = $this->entityManager->getConnection()->fetchAssociative('SELECT i.id AS "inspectionId", i.organization_id AS "organizationId" FROM non_conformities n INNER JOIN inspections i ON i.id = n.inspection_id WHERE n.id = :id', ['id' => $id]);

    return false === $row ? null : $row;
  }
}
