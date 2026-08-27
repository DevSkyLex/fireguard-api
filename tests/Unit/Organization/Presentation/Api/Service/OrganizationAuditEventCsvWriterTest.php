<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Service;

use Organization\Application\UseCase\Query\Organization\ListOrganizationAuditEvents\OrganizationAuditEventResult;
use Organization\Presentation\Api\Service\OrganizationAuditEventCsvWriter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function fopen;
use function rewind;
use function stream_get_contents;

/**
 * Test OrganizationAuditEventCsvWriterTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationAuditEventCsvWriter::class)]
final class OrganizationAuditEventCsvWriterTest extends TestCase
{
  #[Test]
  public function testItWritesTheHeaderEvenWithNoRows(): void
  {
    // An empty export must still be a valid CSV file, not a zero-byte
    // download the user cannot tell from a failure.
    $csv = $this->write([]);

    self::assertStringContainsString('id,action,actor_type', $csv);
    self::assertStringContainsString('actor_is_organization_member', $csv);
  }

  #[Test]
  public function testItCarriesNoneOfThePlatformPiiColumns(): void
  {
    // The organization feed strips these on purpose. An export that reused the
    // platform column set would hand back in a file exactly what the read
    // endpoint refuses to show on screen.
    $csv = $this->write([$this->makeRow()]);

    foreach (['actor_email', 'ip_address', 'user_agent', 'prev_hash', 'payload_hash', 'tenant_id', 'client_id'] as $forbidden) {
      self::assertStringNotContainsString($forbidden, $csv);
    }
  }

  #[Test]
  public function testItWritesTheRowValues(): void
  {
    $csv = $this->write([$this->makeRow()]);

    self::assertStringContainsString('organization.member_added', $csv);
    self::assertStringContainsString('true', $csv);
    self::assertStringContainsString('2026-03-15T10:00:00+00:00', $csv);
  }

  #[Test]
  public function testItRendersNullsAsEmptyRatherThanTheWordNull(): void
  {
    $csv = $this->write([$this->makeRow(actorId: null, subjectType: null, subjectId: null)]);

    self::assertStringNotContainsString('null', $csv);
  }

  #[Test]
  public function testANonMemberActorIsMarkedFalse(): void
  {
    $csv = $this->write([$this->makeRow(actorIsOrganizationMember: false)]);

    self::assertStringContainsString('false', $csv);
  }

  /**
   * @param list<OrganizationAuditEventResult> $rows
   */
  private function write(array $rows): string
  {
    $handle = fopen('php://memory', 'w+b');
    self::assertIsResource($handle);

    new OrganizationAuditEventCsvWriter()->write($rows, $handle);
    rewind($handle);

    return (string) stream_get_contents($handle);
  }

  private function makeRow(
    ?string $actorId = '550e8400-e29b-41d4-a716-446655445503',
    bool $actorIsOrganizationMember = true,
    ?string $subjectType = 'organization_member',
    ?string $subjectId = '550e8400-e29b-41d4-a716-446655445502',
  ): OrganizationAuditEventResult {
    return new OrganizationAuditEventResult(
      id: '550e8400-e29b-41d4-a716-446655445504',
      action: 'organization.member_added',
      actorType: 'user',
      actorId: $actorId,
      actorIsOrganizationMember: $actorIsOrganizationMember,
      subjectType: $subjectType,
      subjectId: $subjectId,
      metadata: ['user_id' => '550e8400-e29b-41d4-a716-446655445503'],
      occurredAt: '2026-03-15T10:00:00+00:00',
      recordedAt: '2026-03-15T10:00:01+00:00',
    );
  }
}
