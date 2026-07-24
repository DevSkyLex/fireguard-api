<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Doctrine;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Re-declares the PostgreSQL partial indexes that Doctrine ORM's mapping layer
 * cannot express.
 *
 * A partial index (`CREATE INDEX ... WHERE <predicate>`) is a first-class
 * PostgreSQL feature but has no representation in ORM attributes, so a
 * migration-created partial index is invisible to `doctrine:schema:validate`:
 * the database has it, the mapping-derived schema does not, and validation
 * reports the table as out of sync forever. Left unaddressed, `migrations:diff`
 * would even generate a DROP for these indexes — silently removing a business
 * guarantee (the "one pending approval per subject" uniqueness) and a query
 * index.
 *
 * This listener injects those indexes into the schema Doctrine derives from the
 * mappings, so both sides match and validation passes without dropping them.
 * It only touches the `main` entity manager (see service registration); the
 * `hasTable()` guards make it a no-op anywhere the tables are absent.
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PartialIndexSchemaListener
{
  /**
   * @description Adds the unmappable partial indexes to the generated schema.
   *
   * @since 1.0.0
   *
   * @param GenerateSchemaEventArgs $args the schema-generation event
   */
  public function postGenerateSchema(GenerateSchemaEventArgs $args): void
  {
    $schema = $args->getSchema();

    if ($schema->hasTable('approval_requests')) {
      $table = $schema->getTable('approval_requests');

      if (!$table->hasIndex('uniq_approval_request_org_action_subject_pending')) {
        // The predicate must match, character for character, what DBAL
        // reads back via pg_get_expr(indpred, ...) — PostgreSQL stores
        // the normalized form, and the schema comparator compares the
        // two verbatim. Any textual difference makes it recreate the
        // index on every diff.
        $table->addUniqueIndex(
          ['organization_id', 'action_type', 'subject_id'],
          'uniq_approval_request_org_action_subject_pending',
          ['where' => "((status)::text = 'pending'::text)"],
        );
      }
    }

    if ($schema->hasTable('messaging_messages')) {
      $table = $schema->getTable('messaging_messages');

      if (!$table->hasIndex('idx_messaging_message_pinned')) {
        $table->addIndex(
          ['conversation_id', 'pinned_at'],
          'idx_messaging_message_pinned',
          [],
          ['where' => '(pinned_at IS NOT NULL)'],
        );
      }
    }
  }
}
