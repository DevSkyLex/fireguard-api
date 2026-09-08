<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, NonConformityRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord, PlanRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function str_starts_with;

/**
 * Test NonConformityReportExportApiTest.
 *
 * Contract tests for `GET /organizations/{organizationId}/non-conformities/report`.
 * Mirrors `InspectionReportExportApiTest`'s denial split (the same
 * `organization.inspection.read` permission and `pro`/`max` entitlement
 * gate) plus the filter validation the CSV export already enforces:
 *
 * - an invalid `severity` enum value answers **400**;
 * - a member of the OWNING organization who lacks the permission gets **403**;
 * - a caller with no active membership gets **404**;
 * - an entitled member of an organization whose plan is below `pro` gets a
 *   dedicated **403**.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NonConformityReportExportApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655540001';

  private const string ADMIN_USER_ID = '550e8400-e29b-41d4-a716-446655540002';

  private const string PLAIN_MEMBER_USER_ID = '550e8400-e29b-41d4-a716-446655540003';

  private const string OUTSIDER_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655540004';

  private const string OUTSIDER_USER_ID = '550e8400-e29b-41d4-a716-446655540005';

  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655540010';

  private const string NON_CONFORMITY_ID = '550e8400-e29b-41d4-a716-446655540011';

  private const string PRO_PLAN_ID = '550e8400-e29b-41d4-a716-446655540030';

  private const string BASIC_PLAN_ID = '550e8400-e29b-41d4-a716-446655540031';

  #[Test]
  public function testExportNonConformityReportRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/non-conformities/report');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/{organizationId}/non-conformities/report endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated report export, got ' . $statusCode,
    );
  }

  #[Test]
  public function testExportNonConformityReportSucceedsAndStreamsAPdf(): void
  {
    $client = static::createClient();
    $this->seedOrganization(planKey: 'pro');
    $this->seedNonConformity();

    $this->loginAs($client, self::ADMIN_USER_ID, 'nc-report-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/non-conformities/report');
    $response = $client->getResponse();

    self::assertSame(200, $response->getStatusCode(), 'Report export should succeed. Response: ' . $response->getContent());
    self::assertSame('application/pdf', $response->headers->get('Content-Type'));

    $disposition = $response->headers->get('Content-Disposition');
    self::assertIsString($disposition);
    self::assertStringStartsWith('attachment; filename="non-conformities-report-', $disposition);

    $content = (string) $response->getContent();
    self::assertNotSame('', $content, 'The PDF body must not be empty.');
    self::assertTrue(str_starts_with($content, '%PDF-'), 'The response body must be a real PDF.');
  }

  #[Test]
  public function testExportNonConformityReportAcceptsTheCsvExportFilters(): void
  {
    $client = static::createClient();
    $this->seedOrganization(planKey: 'pro');
    $this->seedNonConformity();

    $this->loginAs($client, self::ADMIN_USER_ID, 'nc-report-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/non-conformities/report?severity=high&status=open');

    self::assertSame(200, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testExportNonConformityReportRejectsAnInvalidSeverityFilter(): void
  {
    $client = static::createClient();
    $this->seedOrganization(planKey: 'pro');

    $this->loginAs($client, self::ADMIN_USER_ID, 'nc-report-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/non-conformities/report?severity=catastrophic');

    self::assertSame(400, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testExportNonConformityReportRejectsMemberWithoutReadPermission(): void
  {
    $client = static::createClient();
    $this->seedOrganization(planKey: 'pro');

    $this->loginAs($client, self::PLAIN_MEMBER_USER_ID, 'nc-report-plain@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/non-conformities/report');

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A member without organization.inspection.read must get 403.',
    );
  }

  #[Test]
  public function testExportNonConformityReportReturns404ForACallerFromAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seedOrganization(planKey: 'pro');

    $this->loginAs($client, self::OUTSIDER_USER_ID, 'nc-report-outsider@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/non-conformities/report');

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A caller outside the owning organization must get 404, not 403.',
    );
  }

  #[Test]
  public function testExportNonConformityReportRejectsANonEntitledPlanWith403(): void
  {
    $client = static::createClient();
    $this->seedOrganization(planKey: 'nc-report-basic-test');

    $this->loginAs($client, self::ADMIN_USER_ID, 'nc-report-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/non-conformities/report');

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'An organization below the pro plan must get the dedicated entitlement 403.',
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
   * Seeds (idempotently) an organization on the given plan key with an
   * admin member (permissions `['*']`) and a plain member
   * (`organization.read` only), plus a second, unrelated organization with
   * its own member — the "outside scope" caller.
   */
  private function seedOrganization(string $planKey): void
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

    $planId = 'pro' === $planKey ? self::PRO_PLAN_ID : self::BASIC_PLAN_ID;
    $plan = $entityManager->getRepository(PlanRecord::class)->findOneBy(['key' => $planKey]);
    if (!$plan instanceof PlanRecord) {
      $plan = new PlanRecord();
      $plan->id = $planId;
      $plan->key = $planKey;
      $plan->name = 'Report Test Plan ' . $planKey;
      $plan->limits = [];
      $plan->isActive = true;
      $plan->isDefault = false;
      $plan->sortOrder = 0;
      $plan->createdAt = $now;
      $plan->updatedAt = $now;
      $entityManager->persist($plan);
    }

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'NC Report Test Org';
    $organization->slug = 'nc-report-test-org-' . self::ORGANIZATION_ID;
    $organization->ownerUserId = self::ADMIN_USER_ID;
    $organization->createdByUserId = self::ADMIN_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->planId = $plan->id;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $outsiderOrganization = new OrganizationRecord();
    $outsiderOrganization->id = self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->name = 'NC Report Outsider Org';
    $outsiderOrganization->slug = 'nc-report-outsider-org-' . self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->ownerUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->createdByUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->status = 'active';
    $outsiderOrganization->isActive = true;
    $outsiderOrganization->createdAt = $now;
    $outsiderOrganization->updatedAt = $now;
    $entityManager->persist($outsiderOrganization);

    $adminRole = new OrganizationRoleRecord();
    $adminRole->id = '550e8400-e29b-41d4-a716-446655540020';
    $adminRole->organization = $organization;
    $adminRole->name = 'nc-report-full-access';
    $adminRole->permissions = ['*'];
    $adminRole->description = 'Functional-test-only role granting every permission.';
    $adminRole->isSystem = false;
    $adminRole->createdAt = $now;
    $entityManager->persist($adminRole);

    $readOnlyRole = new OrganizationRoleRecord();
    $readOnlyRole->id = '550e8400-e29b-41d4-a716-446655540021';
    $readOnlyRole->organization = $organization;
    $readOnlyRole->name = 'nc-report-read-only';
    $readOnlyRole->permissions = ['organization.read'];
    $readOnlyRole->description = 'Functional-test-only role without inspection read access.';
    $readOnlyRole->isSystem = false;
    $readOnlyRole->createdAt = $now;
    $entityManager->persist($readOnlyRole);

    $outsiderRole = new OrganizationRoleRecord();
    $outsiderRole->id = '550e8400-e29b-41d4-a716-446655540024';
    $outsiderRole->organization = $outsiderOrganization;
    $outsiderRole->name = 'nc-report-outsider-full-access';
    $outsiderRole->permissions = ['*'];
    $outsiderRole->description = 'Functional-test-only role for the unrelated organization.';
    $outsiderRole->isSystem = false;
    $outsiderRole->createdAt = $now;
    $entityManager->persist($outsiderRole);

    $adminMember = new OrganizationMemberRecord();
    $adminMember->id = '550e8400-e29b-41d4-a716-446655540022';
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
    $plainMember->id = '550e8400-e29b-41d4-a716-446655540023';
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
    $outsiderMember->id = '550e8400-e29b-41d4-a716-446655540025';
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
   * Method seedNonConformity.
   *
   * Seeds (idempotently) one closed inspection with one open high-severity
   * non-conformity, so the grouped report has a real row.
   */
  private function seedNonConformity(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    foreach ([[NonConformityRecord::class, self::NON_CONFORMITY_ID], [InspectionRecord::class, self::INSPECTION_ID]] as [$recordClass, $id]) {
      $existing = $entityManager->find($recordClass, $id);
      if (null !== $existing) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }
    }

    /** @var OrganizationRecord $organization */
    $organization = $entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $inspection = new InspectionRecord();
    $inspection->id = self::INSPECTION_ID;
    $inspection->organization = $organization;
    $inspection->equipmentId = '550e8400-e29b-41d4-a716-446655540012';
    $inspection->inspectorType = 'user';
    $inspection->inspectorName = 'NC Report Inspector';
    $inspection->result = 'fail';
    $inspection->status = 'closed';
    $inspection->performedAt = $now;
    $inspection->createdAt = $now;
    $inspection->updatedAt = $now;
    $entityManager->persist($inspection);

    $nonConformity = new NonConformityRecord();
    $nonConformity->id = self::NON_CONFORMITY_ID;
    $nonConformity->inspection = $inspection;
    $nonConformity->description = 'Hose is worn out.';
    $nonConformity->severity = 'high';
    $nonConformity->status = 'open';
    $nonConformity->createdAt = $now;
    $nonConformity->updatedAt = $now;
    $entityManager->persist($nonConformity);

    $entityManager->flush();
  }
}
