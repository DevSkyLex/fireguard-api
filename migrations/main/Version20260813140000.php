<?php

declare(strict_types=1);

namespace DoctrineMigrations\Main;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 5 review fix — closes the signature-duplicate race: two concurrent
 * `kind = 'signature'` uploads for the same intervention could both pass the
 * application-level "one signature per intervention" check and both persist,
 * since nothing in the schema forbade it. A partial unique index is the only
 * mechanism this repo has to express "at most one signature row per
 * intervention" (Doctrine's ORM attributes cannot express a partial index —
 * see `uniq_approval_request_org_action_subject_pending` for the same
 * precedent), so it is hand-written raw SQL rather than an
 * `#[ORM\UniqueConstraint]` on `InterventionAttachmentRecord`.
 *
 * `AddInterventionAttachmentHandler` / `InterventionAttachmentRepository`
 * translate the resulting `UniqueConstraintViolationException` into
 * `InterventionConflictException` (409), mirroring
 * `DoctrineInterventionLabelAdapter::flush()`'s (organization, name)
 * precedent.
 */
final class Version20260813140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 5 review: partial unique index enforcing at most one signature attachment per intervention.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE UNIQUE INDEX uniq_intervention_attachment_signature ON intervention_attachments (intervention_id) WHERE (kind = 'signature')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_intervention_attachment_signature');
    }
}
