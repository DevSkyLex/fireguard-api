<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function str_starts_with;

/**
 * Test InterventionReportExportApiTest.
 *
 * Contract tests for `GET /interventions/{id}/report`. Mirrors
 * `InterventionAttachmentApiTest`'s download-route denial split, since the
 * permission gate is the SAME `organization.interventions.read` check
 * `GetInterventionWorkflowHandler` already enforces for `GET /interventions/{id}`:
 *
 * - a member of the OWNING organization who lacks
 *   `organization.interventions.read` gets **403**;
 * - a caller with no active membership in the owning organization gets
 *   **404** — the record must be invisible, not merely forbidden.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionReportExportApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655490001';

  private const string ADMIN_USER_ID = '550e8400-e29b-41d4-a716-446655490002';

  private const string ADMIN_MEMBER_ID = '550e8400-e29b-41d4-a716-446655490022';

  private const string PLAIN_MEMBER_USER_ID = '550e8400-e29b-41d4-a716-446655490003';

  private const string OUTSIDER_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655490004';

  private const string OUTSIDER_USER_ID = '550e8400-e29b-41d4-a716-446655490005';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655490010';

  #[Test]
  public function testExportInterventionReportRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/interventions/' . self::DUMMY_UUID . '/report');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /interventions/{id}/report endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /interventions/{id}/report, got ' . $statusCode,
    );
  }

  #[Test]
  public function testExportInterventionReportReturns404ForAnUnknownId(): void
  {
    $client = static::createClient();
    $this->seedOrganization();

    $this->loginAs($client, self::ADMIN_USER_ID, 'report-admin@example.com');

    $client->request('GET', '/api/interventions/' . self::DUMMY_UUID . '/report');

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A non-existent intervention id must yield 404.',
    );
  }

  #[Test]
  public function testExportInterventionReportSucceedsAndStreamsAPdf(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedIntervention('draft');

    $this->loginAs($client, self::ADMIN_USER_ID, 'report-admin@example.com');

    $client->request('GET', '/api/interventions/' . self::INTERVENTION_ID . '/report');
    $response = $client->getResponse();

    self::assertSame(200, $response->getStatusCode(), 'Report export should succeed. Response: ' . $response->getContent());
    self::assertSame('application/pdf', $response->headers->get('Content-Type'));

    $disposition = $response->headers->get('Content-Disposition');
    self::assertIsString($disposition);
    self::assertStringStartsWith('attachment;', $disposition);
    self::assertStringContainsString('intervention-FG-900-report.pdf', $disposition);

    $content = (string) $response->getContent();
    self::assertNotSame('', $content, 'The PDF body must not be empty.');
    self::assertTrue(str_starts_with($content, '%PDF-'), 'The response body must be a real PDF.');
  }

  #[Test]
  public function testExportInterventionReportIsAvailableRegardlessOfPhase(): void
  {
    // No phase gate — mirrors the attachment-download precedent: the report
    // must stay generatable even for a published (otherwise immutable)
    // intervention.
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedIntervention('published');

    $this->loginAs($client, self::ADMIN_USER_ID, 'report-admin@example.com');

    $client->request('GET', '/api/interventions/' . self::INTERVENTION_ID . '/report');

    self::assertSame(200, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testExportInterventionReportRejectsMemberWithoutReadPermission(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedIntervention('draft');

    $this->loginAs($client, self::PLAIN_MEMBER_USER_ID, 'report-plain-member@example.com');

    $client->request('GET', '/api/interventions/' . self::INTERVENTION_ID . '/report');

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A member without organization.interventions.read must get 403.',
    );
  }

  #[Test]
  public function testExportInterventionReportReturns404ForACallerFromAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seedOrganization();
    $this->seedIntervention('draft');

    $this->loginAs($client, self::OUTSIDER_USER_ID, 'report-outsider@example.com');

    $client->request('GET', '/api/interventions/' . self::INTERVENTION_ID . '/report');

    // No active membership in the owning organization: the intervention must
    // be invisible, not merely forbidden — the same OUTSIDE_SCOPE -> 404
    // mapping every other read path in this module uses.
    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A caller outside the owning organization must get 404, not 403.',
    );
  }

  /**
   * Method loginAs.
   *
   * Authenticates the client against the stateless `api` firewall.
   */
  private function loginAs(KernelBrowser $client, string $userId, string $email): void
  {
    $user = new SecurityUser(
      id: $userId,
      email: $email,
      password: 'hashed-password',
      roles: ['ROLE_USER'],
    );
    $client->loginUser($user, 'api');
  }

  /**
   * Method seedOrganization.
   *
   * Seeds (idempotently) an organization with an admin member (permissions
   * `['*']`) and a plain member (`organization.read` only, no
   * `organization.interventions.read`), plus a second, unrelated
   * organization with its own member — the "outside scope" caller.
   */
  private function seedOrganization(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    foreach ([self::ORGANIZATION_ID, self::OUTSIDER_ORGANIZATION_ID] as $organizationId) {
      $existing = $entityManager->find(OrganizationRecord::class, $organizationId);
      if ($existing instanceof OrganizationRecord) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }
    }

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Report Export Test Org';
    $organization->slug = 'report-export-test-org-' . self::ORGANIZATION_ID;
    $organization->ownerUserId = self::ADMIN_USER_ID;
    $organization->createdByUserId = self::ADMIN_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $outsiderOrganization = new OrganizationRecord();
    $outsiderOrganization->id = self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->name = 'Report Export Outsider Org';
    $outsiderOrganization->slug = 'report-export-outsider-org-' . self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->ownerUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->createdByUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->status = 'active';
    $outsiderOrganization->isActive = true;
    $outsiderOrganization->createdAt = $now;
    $outsiderOrganization->updatedAt = $now;
    $entityManager->persist($outsiderOrganization);

    $adminRole = new OrganizationRoleRecord();
    $adminRole->id = '550e8400-e29b-41d4-a716-446655490020';
    $adminRole->organization = $organization;
    $adminRole->name = 'report-full-access';
    $adminRole->permissions = ['*'];
    $adminRole->description = 'Functional-test-only role granting every permission.';
    $adminRole->isSystem = false;
    $adminRole->createdAt = $now;
    $entityManager->persist($adminRole);

    $readOnlyRole = new OrganizationRoleRecord();
    $readOnlyRole->id = '550e8400-e29b-41d4-a716-446655490021';
    $readOnlyRole->organization = $organization;
    $readOnlyRole->name = 'report-read-only';
    $readOnlyRole->permissions = ['organization.read'];
    $readOnlyRole->description = 'Functional-test-only role without intervention read access.';
    $readOnlyRole->isSystem = false;
    $readOnlyRole->createdAt = $now;
    $entityManager->persist($readOnlyRole);

    $outsiderRole = new OrganizationRoleRecord();
    $outsiderRole->id = '550e8400-e29b-41d4-a716-446655490024';
    $outsiderRole->organization = $outsiderOrganization;
    $outsiderRole->name = 'report-outsider-full-access';
    $outsiderRole->permissions = ['*'];
    $outsiderRole->description = 'Functional-test-only role for the unrelated organization.';
    $outsiderRole->isSystem = false;
    $outsiderRole->createdAt = $now;
    $entityManager->persist($outsiderRole);

    $adminMember = new OrganizationMemberRecord();
    $adminMember->id = self::ADMIN_MEMBER_ID;
    $adminMember->organization = $organization;
    $adminMember->userId = self::ADMIN_USER_ID;
    $adminMember->isActive = true;
    $adminMember->joinedAt = $now;
    $entityManager->persist($adminMember);

    $adminAssignment = new OrganizationMemberRoleRecord();
    $adminAssignment->member = $adminMember;
    $adminAssignment->role = $adminRole;
    $adminAssignment->assignedAt = $now;
    $entityManager->persist($adminAssignment);

    $plainMember = new OrganizationMemberRecord();
    $plainMember->id = '550e8400-e29b-41d4-a716-446655490023';
    $plainMember->organization = $organization;
    $plainMember->userId = self::PLAIN_MEMBER_USER_ID;
    $plainMember->isActive = true;
    $plainMember->joinedAt = $now;
    $entityManager->persist($plainMember);

    $plainAssignment = new OrganizationMemberRoleRecord();
    $plainAssignment->member = $plainMember;
    $plainAssignment->role = $readOnlyRole;
    $plainAssignment->assignedAt = $now;
    $entityManager->persist($plainAssignment);

    $outsiderMember = new OrganizationMemberRecord();
    $outsiderMember->id = '550e8400-e29b-41d4-a716-446655490025';
    $outsiderMember->organization = $outsiderOrganization;
    $outsiderMember->userId = self::OUTSIDER_USER_ID;
    $outsiderMember->isActive = true;
    $outsiderMember->joinedAt = $now;
    $entityManager->persist($outsiderMember);

    $outsiderAssignment = new OrganizationMemberRoleRecord();
    $outsiderAssignment->member = $outsiderMember;
    $outsiderAssignment->role = $outsiderRole;
    $outsiderAssignment->assignedAt = $now;
    $entityManager->persist($outsiderAssignment);

    $entityManager->flush();
  }

  /**
   * Method seedIntervention.
   *
   * Seeds (idempotently) a single intervention owned by
   * {@see self::ORGANIZATION_ID} in the given status, with the admin member
   * ({@see self::ADMIN_MEMBER_ID}) as responsible.
   */
  private function seedIntervention(string $status): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $existing = $entityManager->find(InterventionRecord::class, self::INTERVENTION_ID);
    if ($existing instanceof InterventionRecord) {
      $entityManager->remove($existing);
      $entityManager->flush();
    }

    /** @var OrganizationRecord $organization */
    $organization = $entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $intervention = new InterventionRecord();
    $intervention->id = self::INTERVENTION_ID;
    $intervention->organization = $organization;
    $intervention->type = 'site_setup';
    $intervention->name = 'Report Export Test Intervention';
    $intervention->number = 900;
    $intervention->status = $status;
    $intervention->responsibleId = self::ADMIN_MEMBER_ID;
    $intervention->createdAt = $now;
    $intervention->updatedAt = $now;
    $entityManager->persist($intervention);
    $entityManager->flush();
  }
}
