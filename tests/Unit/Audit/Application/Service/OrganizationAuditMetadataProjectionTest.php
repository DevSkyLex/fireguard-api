<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Application\Service;

use Audit\Application\Service\OrganizationAuditMetadataProjection;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

use function sprintf;
use function str_contains;

/**
 * Test OrganizationAuditMetadataProjectionTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationAuditMetadataProjection::class)]
final class OrganizationAuditMetadataProjectionTest extends TestCase
{
  /**
   * The property that makes this an allowlist rather than a denylist: an
   * action nobody has vetted publishes nothing. A producer that adds an
   * organization-scoped action tomorrow and forgets this map degrades the
   * feed; it cannot leak through it.
   */
  #[Test]
  public function testProjectDropsEverythingForAnUnknownAction(): void
  {
    $projected = OrganizationAuditMetadataProjection::project('organization.some_action_added_next_year', [
      'reason' => 'Sacked for cause: see HR file 44.',
      'member_id' => 'member-1',
    ]);

    self::assertSame([], $projected);
  }

  #[Test]
  public function testProjectKeepsOnlyTheKeysTheActionIsAllowedToPublish(): void
  {
    $projected = OrganizationAuditMetadataProjection::project('organization.member_added', [
      'organization_id' => 'org-1',
      'user_id' => 'user-1',
      'role_ids' => ['role-1'],
      'request_id' => 'req-1',
      'ip' => '203.0.113.5',
      'invited_email' => 'invitee@example.com',
    ]);

    self::assertSame(['user_id' => 'user-1', 'role_ids' => ['role-1']], $projected);
  }

  /**
   * A key the action is allowed to publish but the producer did not emit must
   * be absent, not present as null — the wire shape has to mean "the producer
   * said nothing", not "the producer said nothing in particular".
   */
  #[Test]
  public function testProjectOmitsAllowedKeysThePayloadDoesNotCarry(): void
  {
    $projected = OrganizationAuditMetadataProjection::project('organization.member_added', [
      'user_id' => 'user-1',
    ]);

    self::assertSame(['user_id' => 'user-1'], $projected);
  }

  /**
   * `organization.ownership_transferred` was added to develop while this
   * feature sat on a branch, so it is a new participant in the feed. Its
   * payload is two opaque user ids — safe, and explicitly vetted here rather
   * than admitted by accident.
   */
  #[Test]
  public function testProjectPublishesTheOwnershipTransferParticipants(): void
  {
    $projected = OrganizationAuditMetadataProjection::project('organization.ownership_transferred', [
      'previous_owner_user_id' => 'user-1',
      'new_owner_user_id' => 'user-2',
    ]);

    self::assertSame([
      'previous_owner_user_id' => 'user-1',
      'new_owner_user_id' => 'user-2',
    ], $projected);
  }

  /**
   * Unlike `testProjectDropsEverythingForAnUnknownAction`, these three
   * actions ARE known to the projection — `knownActions()` lists them with
   * an explicit, deliberately empty allowlist. `title` is operator-typed
   * free text and `starts_at` carries no unique identifier value once
   * `title` is gone, so both are withheld for every calendar event action.
   */
  #[Test]
  public function testProjectDropsCalendarEventTitleAndTimestampForEveryKnownAction(): void
  {
    $payload = [
      'title' => 'Board meeting: Q3 layoffs',
      'starts_at' => '2026-08-01T09:00:00+02:00',
    ];

    self::assertSame([], OrganizationAuditMetadataProjection::project('calendar.event_created', $payload));
    self::assertSame([], OrganizationAuditMetadataProjection::project('calendar.event_updated', $payload));
    self::assertSame([], OrganizationAuditMetadataProjection::project('calendar.event_deleted', $payload));

    $known = OrganizationAuditMetadataProjection::knownActions();
    self::assertArrayHasKey('calendar.event_created', $known);
    self::assertArrayHasKey('calendar.event_updated', $known);
    self::assertArrayHasKey('calendar.event_deleted', $known);
  }

  /**
   * @return iterable<string, array{string, string}>
   */
  public static function freeTextAndPiiActionProvider(): iterable
  {
    yield 'invitation revoked reason' => ['organization.invitation_revoked', 'reason'];
    yield 'permission grant denied context' => ['organization.permission_grant_denied', 'context'];
    yield 'publication failure reason' => ['intervention.publication_failed', 'reason'];
    yield 'recurrence materialization error' => ['intervention.recurrence_materialized', 'error'];
    yield 'automation rule failure error' => ['automation.rule_failed', 'error'];
    yield 'approval execution failure error' => ['approval.execution_failed', 'error'];
    yield 'import job error' => ['import.job_failed', 'job_error'];
    yield 'invited email' => ['organization.invitation_sent', 'invited_email'];
    yield 'invited email hash' => ['organization.invitation_sent', 'invited_email_hash'];
    yield 'webhook url host' => ['webhook.subscription_created', 'url_host'];
    yield 'messaging channel name' => ['messaging.channel_created', 'name'];
  }

  /**
   * Every key the producers genuinely emit that can carry operator-typed
   * prose, raw exception text, personal data, or another permission
   * surface's data — named one by one, because these are the leaks the
   * previous key-name denylist could not see.
   */
  #[Test]
  #[DataProvider('freeTextAndPiiActionProvider')]
  public function testProjectDropsFreeTextAndPiiKeysTheProducersActuallyEmit(string $action, string $key): void
  {
    $projected = OrganizationAuditMetadataProjection::project($action, [
      $key => 'SQLSTATE[23505]: duplicate key in /srv/app/src/Foo.php for jane.doe@example.com',
    ]);

    self::assertArrayNotHasKey($key, $projected, sprintf('"%s" must never be published for %s.', $key, $action));
  }

  /**
   * A standing guard over the whole map rather than over the entries that
   * exist today: whatever action is added next, its allowlist may not admit a
   * key whose very name marks it as prose, a credential, personal data, or
   * request context.
   */
  #[Test]
  public function testNoAllowlistAdmitsAProseCredentialOrRequestContextKey(): void
  {
    $forbiddenExact = ['reason', 'context', 'error', 'job_error', 'message', 'description', 'note', 'comment', 'ip', 'ip_address', 'ip_hash', 'user_agent', 'request_id', 'url', 'url_host'];
    $forbiddenFragments = ['email', 'secret', 'token', 'password', 'credential', 'phone'];

    foreach (OrganizationAuditMetadataProjection::knownActions() as $action => $keys) {
      foreach ($keys as $key) {
        self::assertNotContains($key, $forbiddenExact, sprintf('"%s" must not be published for %s.', $key, $action));

        foreach ($forbiddenFragments as $fragment) {
          self::assertFalse(
            str_contains($key, $fragment),
            sprintf('"%s" (allowed for %s) contains the forbidden fragment "%s".', $key, $action, $fragment),
          );
        }
      }
    }
  }
}
