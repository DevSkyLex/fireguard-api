<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Doctrine\PartialIndexSchemaListener;

/**
 * Test PartialIndexSchemaListenerTest.
 *
 * @category Doctrine Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PartialIndexSchemaListener::class)]
final class PartialIndexSchemaListenerTest extends TestCase
{
  // #region Constants
  private const string APPROVAL_INDEX = 'uniq_approval_request_org_action_subject_pending';

  private const string MESSAGING_INDEX = 'idx_messaging_message_pinned';

  private const string SIGNATURE_INDEX = 'uniq_intervention_attachment_signature';

  private const string PRIMARY_PLAN_INDEX = 'uniq_facility_attachment_primary_plan';
  // #endregion

  // #region Methods
  #[Test]
  public function testItAddsThePartialUniqueIndexOnApprovalRequests(): void
  {
    $schema = new Schema();
    $table = $schema->createTable('approval_requests');
    $table->addColumn('organization_id', 'string');
    $table->addColumn('action_type', 'string');
    $table->addColumn('subject_id', 'string');
    $table->addColumn('status', 'string');

    $this->listen($schema);

    $index = $schema->getTable('approval_requests')->getIndex(self::APPROVAL_INDEX);

    self::assertTrue($index->isUnique());
    self::assertSame(['organization_id', 'action_type', 'subject_id'], $index->getColumns());
    self::assertTrue($index->hasOption('where'));
    self::assertSame("((status)::text = 'pending'::text)", $index->getOption('where'));
  }

  #[Test]
  public function testItAddsThePartialIndexOnMessagingMessages(): void
  {
    $schema = new Schema();
    $table = $schema->createTable('messaging_messages');
    $table->addColumn('conversation_id', 'string');
    $table->addColumn('pinned_at', 'datetime_immutable', ['notnull' => false]);

    $this->listen($schema);

    $index = $schema->getTable('messaging_messages')->getIndex(self::MESSAGING_INDEX);

    self::assertFalse($index->isUnique());
    self::assertSame(['conversation_id', 'pinned_at'], $index->getColumns());
    self::assertSame('(pinned_at IS NOT NULL)', $index->getOption('where'));
  }

  /**
   * The predicate is asserted character for character on purpose. DBAL compares
   * it verbatim against what PostgreSQL reads back through
   * `pg_get_expr(indpred, ...)`, so a single missing parenthesis does not fail
   * loudly — it makes every `schema:validate` report drift and every
   * `migrations:diff` emit a DROP followed by an identical CREATE. Both forms
   * below were read out of `pg_indexes` rather than guessed.
   */
  #[Test]
  public function testItAddsThePartialUniqueIndexOnInterventionAttachments(): void
  {
    $schema = new Schema();
    $table = $schema->createTable('intervention_attachments');
    $table->addColumn('intervention_id', 'string');
    $table->addColumn('kind', 'string');

    $this->listen($schema);

    $index = $schema->getTable('intervention_attachments')->getIndex(self::SIGNATURE_INDEX);

    self::assertTrue($index->isUnique());
    self::assertSame(['intervention_id'], $index->getColumns());
    self::assertSame("((kind)::text = 'signature'::text)", $index->getOption('where'));
  }

  /**
   * A bare boolean predicate carries no parentheses and no cast, unlike the
   * varchar comparison above — PostgreSQL stores it exactly as written.
   */
  #[Test]
  public function testItAddsThePartialUniqueIndexOnFacilityAttachments(): void
  {
    $schema = new Schema();
    $table = $schema->createTable('facility_attachments');
    $table->addColumn('facility_id', 'string');
    $table->addColumn('is_primary_plan', 'boolean');

    $this->listen($schema);

    $index = $schema->getTable('facility_attachments')->getIndex(self::PRIMARY_PLAN_INDEX);

    self::assertTrue($index->isUnique());
    self::assertSame(['facility_id'], $index->getColumns());
    self::assertSame('is_primary_plan', $index->getOption('where'));
  }

  #[Test]
  public function testItIsIdempotent(): void
  {
    $schema = new Schema();
    $approvals = $schema->createTable('approval_requests');
    $approvals->addColumn('organization_id', 'string');
    $approvals->addColumn('action_type', 'string');
    $approvals->addColumn('subject_id', 'string');
    $approvals->addColumn('status', 'string');

    $messages = $schema->createTable('messaging_messages');
    $messages->addColumn('conversation_id', 'string');
    $messages->addColumn('pinned_at', 'datetime_immutable', ['notnull' => false]);

    $this->listen($schema);
    $this->listen($schema);

    self::assertTrue($schema->getTable('approval_requests')->hasIndex(self::APPROVAL_INDEX));
    self::assertTrue($schema->getTable('messaging_messages')->hasIndex(self::MESSAGING_INDEX));
  }

  #[Test]
  public function testItDoesNothingWhenTheTablesAreAbsent(): void
  {
    $schema = new Schema();
    $table = $schema->createTable('unrelated');
    $table->addColumn('id', 'string');

    $this->listen($schema);

    self::assertFalse($schema->hasTable('approval_requests'));
    self::assertFalse($schema->hasTable('messaging_messages'));
    self::assertFalse($schema->hasTable('intervention_attachments'));
    self::assertFalse($schema->hasTable('facility_attachments'));
    self::assertTrue($schema->hasTable('unrelated'));
  }
  // #endregion

  // #region Helpers
  private function listen(Schema $schema): void
  {
    new PartialIndexSchemaListener()->postGenerateSchema(
      new GenerateSchemaEventArgs($this->createStub(EntityManagerInterface::class), $schema),
    );
  }
  // #endregion
}
