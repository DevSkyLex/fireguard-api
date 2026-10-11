<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Attachment;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Contract\Attachment\InterventionAttachmentView;
use Intervention\Application\Port\Outbound\InterventionMediaLookupPort;

use function is_string;

/** Loads scalar media snapshots on the main connection.
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DoctrineInterventionMediaLookupAdapter implements InterventionMediaLookupPort
{
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  public function attachment(string $id, bool $lock = false): ?InterventionAttachmentView
  {
    /** @var array{id: string, organization_id: string, intervention_id: string, file_name: string, mime_type: string, size: int|string, label: ?string, revision: int|string, uploaded_at: string, work_item_id: ?string, kind: string}|false $row */
    $row = $this->entityManager->getConnection()->fetchAssociative(
      'SELECT a.id, p.organization_id, a.intervention_id, a.file_name, a.mime_type, a.size, a.label, a.revision, a.uploaded_at, a.work_item_id, a.kind FROM intervention_attachments a INNER JOIN interventions p ON p.id = a.intervention_id WHERE a.id = :id' . ($lock ? ' FOR UPDATE OF a' : ''),
      ['id' => $id],
    );
    if (false === $row) {
      return null;
    }

    return new InterventionAttachmentView(
      id: $row['id'],
      organizationId: $row['organization_id'],
      interventionId: $row['intervention_id'],
      fileName: $row['file_name'],
      mimeType: $row['mime_type'],
      size: (int) $row['size'],
      label: $row['label'],
      revision: (int) $row['revision'],
      uploadedAt: new DateTimeImmutable($row['uploaded_at'])->format('c'),
      workItemId: $row['work_item_id'],
      kind: $row['kind'],
    );
  }

  public function organizationId(string $interventionId): ?string
  {
    $id = $this->entityManager->getConnection()->fetchOne('SELECT organization_id FROM interventions WHERE id = :id', ['id' => $interventionId]);

    return is_string($id) ? $id : null;
  }
}
