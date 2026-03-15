<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function basename;
use function is_array;
use function is_string;
use function json_encode;
use function str_contains;
use function uniqid;

/**
 * End-to-end tests for the Inspection module flow.
 *
 * Covers: checklist lifecycle, full inspection lifecycle (DRAFT→SUBMITTED→CLOSED),
 * non-conformity management, state-machine guards, and auth checks.
 *
 * @category E2E Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InspectionFlowTest extends OAuth2WebTestCase
{
  // #region Tests

  /**
   * Tests the complete happy-path lifecycle:
   *   create checklist → create inspection → add NC → submit → update NC → close.
   */
  public function testCompleteInspectionLifecycleFlow(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'inspector-' . uniqid() . '@example.com';
    $password = 'Inspector123!';

    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);
    $organizationId = $this->createOrganization($client, $token, 'Inspection Org ' . uniqid());
    $this->assertNotNull($organizationId, 'Organization should be created successfully.');

    // Create real equipment via API so cross-module validation passes.
    $equipmentId = $this->createEquipment($client, $token, $organizationId);
    $this->assertNotNull($equipmentId, 'Equipment should be created successfully.');

    // ------------------------------------------------------------------
    // Step 1: Create a checklist.
    // ------------------------------------------------------------------
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/checklists',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'name' => 'Fire Extinguisher Checklist',
        'version' => 'v1.0',
        'items' => [
          ['id' => 'item-1', 'label' => 'Check pressure gauge', 'position' => 0, 'required' => true],
          ['id' => 'item-2', 'label' => 'Check pin and tamper seal', 'position' => 1, 'required' => true],
        ],
      ]) ?: '',
    );

    $createChecklistResponse = $client->getResponse();
    $this->assertContains(
      $createChecklistResponse->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_OK],
      'Checklist creation should succeed. Response: ' . $createChecklistResponse->getContent(),
    );

    $checklistData = $this->decodeJsonResponse($createChecklistResponse->getContent() ?: '{}');
    $checklistId = $this->extractResourceId($checklistData);
    $this->assertNotNull($checklistId, 'Checklist ID should be present in create response.');
    $this->assertSame('active', $checklistData['status'] ?? null, 'New checklist should be active.');
    $this->assertSame('Fire Extinguisher Checklist', $checklistData['name'] ?? null);

    // ------------------------------------------------------------------
    // Step 2: List checklists — created item should appear.
    // ------------------------------------------------------------------
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/checklists',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $listChecklistsResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $listChecklistsResponse->getStatusCode(),
      'Listing checklists should succeed.',
    );

    $listChecklistsData = $this->decodeJsonResponse($listChecklistsResponse->getContent() ?: '{}');
    $checklistMembers = $this->getCollectionMembers($listChecklistsData);
    $this->assertTrue(
      $this->collectionContainsId($checklistMembers, $checklistId),
      'Created checklist should appear in the list.',
    );

    // ------------------------------------------------------------------
    // Step 3: Get checklist by ID.
    // ------------------------------------------------------------------
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/checklists/' . $checklistId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $getChecklistResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $getChecklistResponse->getStatusCode(),
      'Getting checklist by ID should succeed.',
    );

    $getChecklistData = $this->decodeJsonResponse($getChecklistResponse->getContent() ?: '{}');
    $this->assertSame($checklistId, $this->extractResourceId($getChecklistData));

    // ------------------------------------------------------------------
    // Step 4: Create an inspection (DRAFT).
    // ------------------------------------------------------------------
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/inspections',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'equipmentId' => $equipmentId,
        'result' => 'fail',
        'performedAt' => '2026-03-01T10:00:00+00:00',
        'inspectorType' => 'user',
        'inspectorName' => 'John Doe',
        'inspectorUserId' => '550e8400-e29b-41d4-a716-446655440001',
        'checklistId' => $checklistId,
        'notes' => 'Initial inspection notes.',
      ]) ?: '',
    );

    $createInspectionResponse = $client->getResponse();
    $this->assertContains(
      $createInspectionResponse->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_OK],
      'Inspection creation should succeed. Response: ' . $createInspectionResponse->getContent(),
    );

    $inspectionData = $this->decodeJsonResponse($createInspectionResponse->getContent() ?: '{}');
    $inspectionId = $this->extractResourceId($inspectionData);
    $this->assertNotNull($inspectionId, 'Inspection ID should be present in create response.');
    $this->assertSame('draft', $inspectionData['status'] ?? null, 'New inspection should be draft.');
    $this->assertSame('fail', $inspectionData['result'] ?? null);
    $this->assertSame(0, $inspectionData['nonConformitiesCount'] ?? -1, 'No NC yet.');

    // ------------------------------------------------------------------
    // Step 5: List inspections — created item should appear.
    // ------------------------------------------------------------------
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/inspections',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $listInspectionsResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $listInspectionsResponse->getStatusCode(),
      'Listing inspections should succeed.',
    );

    $listInspectionsData = $this->decodeJsonResponse($listInspectionsResponse->getContent() ?: '{}');
    $inspectionMembers = $this->getCollectionMembers($listInspectionsData);
    $this->assertTrue(
      $this->collectionContainsId($inspectionMembers, $inspectionId),
      'Created inspection should appear in the list.',
    );

    // ------------------------------------------------------------------
    // Step 6: Get inspection by ID.
    // ------------------------------------------------------------------
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/inspections/' . $inspectionId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $getInspectionResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $getInspectionResponse->getStatusCode(),
      'Getting inspection by ID should succeed.',
    );

    $getInspectionData = $this->decodeJsonResponse($getInspectionResponse->getContent() ?: '{}');
    $this->assertSame($inspectionId, $this->extractResourceId($getInspectionData));

    // ------------------------------------------------------------------
    // Step 7: Add a non-conformity.
    // ------------------------------------------------------------------
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/inspections/' . $inspectionId . '/non-conformities',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'description' => 'Pressure gauge reads below 10 bar.',
        'severity' => 'high',
        'notes' => 'Requires immediate replacement.',
      ]) ?: '',
    );

    $addNcResponse = $client->getResponse();
    $this->assertContains(
      $addNcResponse->getStatusCode(),
      [Response::HTTP_CREATED, Response::HTTP_OK],
      'Adding a non-conformity should succeed. Response: ' . $addNcResponse->getContent(),
    );

    $ncData = $this->decodeJsonResponse($addNcResponse->getContent() ?: '{}');
    $ncId = $this->extractResourceId($ncData);
    $this->assertNotNull($ncId, 'Non-conformity ID should be present.');
    $this->assertSame('open', $ncData['status'] ?? null, 'New NC should be open.');
    $this->assertSame('high', $ncData['severity'] ?? null);

    // ------------------------------------------------------------------
    // Step 8: List non-conformities.
    // ------------------------------------------------------------------
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/inspections/' . $inspectionId . '/non-conformities',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $listNcResponse = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $listNcResponse->getStatusCode(),
      'Listing non-conformities should succeed.',
    );

    $listNcData = $this->decodeJsonResponse($listNcResponse->getContent() ?: '{}');
    $ncMembers = $this->getCollectionMembers($listNcData);
    $this->assertTrue(
      $this->collectionContainsId($ncMembers, $ncId),
      'Created NC should appear in the list.',
    );

    // Inspection GET should now reflect nonConformitiesCount = 1.
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/inspections/' . $inspectionId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );
    $updatedInspectionData = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    $this->assertSame(1, $updatedInspectionData['nonConformitiesCount'] ?? -1, 'NC count should be 1.');

    // ------------------------------------------------------------------
    // Step 9: Submit inspection (DRAFT → SUBMITTED).
    // ------------------------------------------------------------------
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/inspections/' . $inspectionId . '/submit',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: '{}',
    );

    $submitResponse = $client->getResponse();
    $this->assertContains(
      $submitResponse->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Submit inspection should succeed. Response: ' . $submitResponse->getContent(),
    );

    $submitData = $this->decodeJsonResponse($submitResponse->getContent() ?: '{}');
    $this->assertSame('submitted', $submitData['status'] ?? null, 'Status should be submitted after submit.');

    // ------------------------------------------------------------------
    // Step 10: Update non-conformity status (open → in_progress).
    // ------------------------------------------------------------------
    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . $organizationId . '/inspections/' . $inspectionId . '/non-conformities/' . $ncId . '/status',
      server: [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode(['status' => 'in_progress']) ?: '',
    );

    $updateNcResponse = $client->getResponse();
    $this->assertContains(
      $updateNcResponse->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Updating NC status should succeed. Response: ' . $updateNcResponse->getContent(),
    );

    $updateNcData = $this->decodeJsonResponse($updateNcResponse->getContent() ?: '{}');
    $this->assertSame('in_progress', $updateNcData['status'] ?? null, 'NC status should be in_progress.');

    // ------------------------------------------------------------------
    // Step 11: Close inspection (SUBMITTED → CLOSED).
    // ------------------------------------------------------------------
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/inspections/' . $inspectionId . '/close',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: '{}',
    );

    $closeResponse = $client->getResponse();
    $this->assertContains(
      $closeResponse->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Close inspection should succeed. Response: ' . $closeResponse->getContent(),
    );

    $closeData = $this->decodeJsonResponse($closeResponse->getContent() ?: '{}');
    $this->assertSame('closed', $closeData['status'] ?? null, 'Status should be closed after close.');

    // ------------------------------------------------------------------
    // Step 12: Archive the checklist.
    // ------------------------------------------------------------------
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/checklists/' . $checklistId . '/archive',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: '{}',
    );

    $archiveResponse = $client->getResponse();
    $this->assertContains(
      $archiveResponse->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Archiving checklist should succeed. Response: ' . $archiveResponse->getContent(),
    );

    $archiveData = $this->decodeJsonResponse($archiveResponse->getContent() ?: '{}');
    $this->assertSame('archived', $archiveData['status'] ?? null, 'Checklist status should be archived.');
  }

  /**
   * Tests that closing a DRAFT inspection (not yet submitted) returns 409.
   */
  public function testCloseDraftInspectionReturns409(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'inspector-draft-' . uniqid() . '@example.com';
    $password = 'Inspector123!';

    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);
    $organizationId = $this->createOrganization($client, $token, 'Draft Guard Org ' . uniqid());
    $this->assertNotNull($organizationId);

    $equipmentId = $this->createEquipment($client, $token, $organizationId);
    $this->assertNotNull($equipmentId);

    // Create inspection in DRAFT.
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/inspections',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'equipmentId' => $equipmentId,
        'result' => 'pass',
        'performedAt' => '2026-03-01T10:00:00+00:00',
        'inspectorType' => 'external',
        'inspectorName' => 'Safety Corp Inspector',
      ]) ?: '',
    );

    $createData = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    $inspectionId = $this->extractResourceId($createData);
    $this->assertNotNull($inspectionId);

    // Attempt to close without submitting → must return 409.
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/inspections/' . $inspectionId . '/close',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: '{}',
    );

    $this->assertSame(
      Response::HTTP_CONFLICT,
      $client->getResponse()->getStatusCode(),
      'Closing a DRAFT inspection (not submitted) should return HTTP 409.',
    );
  }

  /**
   * Tests that submitting the same inspection twice returns 409.
   */
  public function testDoubleSubmitReturns409(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'inspector-double-' . uniqid() . '@example.com';
    $password = 'Inspector123!';

    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);
    $organizationId = $this->createOrganization($client, $token, 'Double Submit Org ' . uniqid());
    $this->assertNotNull($organizationId);

    $equipmentId = $this->createEquipment($client, $token, $organizationId);
    $this->assertNotNull($equipmentId);

    // Create and submit inspection.
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/inspections',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'equipmentId' => $equipmentId,
        'result' => 'pass',
        'performedAt' => '2026-03-01T10:00:00+00:00',
        'inspectorType' => 'external',
        'inspectorName' => 'Safety Corp Inspector',
      ]) ?: '',
    );

    $createData = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    $inspectionId = $this->extractResourceId($createData);
    $this->assertNotNull($inspectionId);

    $submitUri = '/api/organizations/' . $organizationId . '/inspections/' . $inspectionId . '/submit';
    $submitHeaders = [
      'CONTENT_TYPE' => 'application/ld+json',
      'HTTP_ACCEPT' => 'application/ld+json',
      'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
    ];

    // First submit must succeed.
    $client->request(method: 'POST', uri: $submitUri, server: $submitHeaders, content: '{}');
    $this->assertContains(
      $client->getResponse()->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'First submit should succeed.',
    );

    // Second submit must return 409.
    $client->request(method: 'POST', uri: $submitUri, server: $submitHeaders, content: '{}');
    $this->assertSame(
      Response::HTTP_CONFLICT,
      $client->getResponse()->getStatusCode(),
      'Second submit should return HTTP 409.',
    );
  }

  /**
   * Tests that adding a non-conformity to a closed inspection returns 409.
   */
  public function testAddNonConformityToClosedInspectionReturns409(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'inspector-closed-nc-' . uniqid() . '@example.com';
    $password = 'Inspector123!';

    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);
    $organizationId = $this->createOrganization($client, $token, 'Closed NC Org ' . uniqid());
    $this->assertNotNull($organizationId);

    $equipmentId = $this->createEquipment($client, $token, $organizationId);
    $this->assertNotNull($equipmentId);

    $inspectionUri = '/api/organizations/' . $organizationId . '/inspections';
    $authHeaders = [
      'CONTENT_TYPE' => 'application/ld+json',
      'HTTP_ACCEPT' => 'application/ld+json',
      'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
    ];

    // Create inspection.
    $client->request(
      method: 'POST',
      uri: $inspectionUri,
      server: $authHeaders,
      content: json_encode([
        'equipmentId' => $equipmentId,
        'result' => 'pass',
        'performedAt' => '2026-03-01T10:00:00+00:00',
        'inspectorType' => 'external',
        'inspectorName' => 'Safety Corp Inspector',
      ]) ?: '',
    );
    $inspectionId = $this->extractResourceId(
      $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}'),
    );
    $this->assertNotNull($inspectionId);

    // Submit, then close.
    $client->request(method: 'POST', uri: $inspectionUri . '/' . $inspectionId . '/submit', server: $authHeaders, content: '{}');
    $this->assertContains($client->getResponse()->getStatusCode(), [Response::HTTP_OK, Response::HTTP_CREATED], 'Submit should succeed.');

    $client->request(method: 'POST', uri: $inspectionUri . '/' . $inspectionId . '/close', server: $authHeaders, content: '{}');
    $this->assertContains($client->getResponse()->getStatusCode(), [Response::HTTP_OK, Response::HTTP_CREATED], 'Close should succeed.');

    // Adding NC to a closed inspection must return 409.
    $client->request(
      method: 'POST',
      uri: $inspectionUri . '/' . $inspectionId . '/non-conformities',
      server: $authHeaders,
      content: json_encode([
        'description' => 'Issue found after closing.',
        'severity' => 'low',
      ]) ?: '',
    );

    $this->assertSame(
      Response::HTTP_CONFLICT,
      $client->getResponse()->getStatusCode(),
      'Adding a NC to a closed inspection should return HTTP 409.',
    );
  }

  /**
   * Tests that closing an already-closed inspection returns 409.
   */
  public function testDoubleCloseReturns409(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'inspector-double-close-' . uniqid() . '@example.com';
    $password = 'Inspector123!';

    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);
    $organizationId = $this->createOrganization($client, $token, 'Double Close Org ' . uniqid());
    $this->assertNotNull($organizationId);

    $equipmentId = $this->createEquipment($client, $token, $organizationId);
    $this->assertNotNull($equipmentId);

    $inspectionUri = '/api/organizations/' . $organizationId . '/inspections';
    $authHeaders = [
      'CONTENT_TYPE' => 'application/ld+json',
      'HTTP_ACCEPT' => 'application/ld+json',
      'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
    ];

    // Create, submit, close once.
    $client->request(
      method: 'POST',
      uri: $inspectionUri,
      server: $authHeaders,
      content: json_encode([
        'equipmentId' => $equipmentId,
        'result' => 'partial',
        'performedAt' => '2026-03-01T10:00:00+00:00',
        'inspectorType' => 'external',
        'inspectorName' => 'Safety Corp Inspector',
      ]) ?: '',
    );
    $inspectionId = $this->extractResourceId(
      $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}'),
    );
    $this->assertNotNull($inspectionId);

    $client->request(method: 'POST', uri: $inspectionUri . '/' . $inspectionId . '/submit', server: $authHeaders, content: '{}');
    $client->request(method: 'POST', uri: $inspectionUri . '/' . $inspectionId . '/close', server: $authHeaders, content: '{}');

    // Second close must return 409.
    $client->request(method: 'POST', uri: $inspectionUri . '/' . $inspectionId . '/close', server: $authHeaders, content: '{}');
    $this->assertSame(
      Response::HTTP_CONFLICT,
      $client->getResponse()->getStatusCode(),
      'Closing an already-closed inspection should return HTTP 409.',
    );
  }

  /**
   * Tests that archiving an already-archived checklist returns 409.
   */
  public function testDoubleArchiveChecklistReturns409(): void
  {
    $client = static::createClientWithFixtures();

    $email = 'inspector-checklist-' . uniqid() . '@example.com';
    $password = 'Inspector123!';

    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);
    $organizationId = $this->createOrganization($client, $token, 'Checklist Org ' . uniqid());
    $this->assertNotNull($organizationId);

    $checklistUri = '/api/organizations/' . $organizationId . '/checklists';
    $authHeaders = [
      'CONTENT_TYPE' => 'application/ld+json',
      'HTTP_ACCEPT' => 'application/ld+json',
      'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
    ];

    // Create checklist.
    $client->request(
      method: 'POST',
      uri: $checklistUri,
      server: $authHeaders,
      content: json_encode(['name' => 'Checklist to archive', 'version' => 'v1.0']) ?: '',
    );
    $checklistId = $this->extractResourceId(
      $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}'),
    );
    $this->assertNotNull($checklistId);

    // First archive must succeed.
    $client->request(method: 'POST', uri: $checklistUri . '/' . $checklistId . '/archive', server: $authHeaders, content: '{}');
    $this->assertContains(
      $client->getResponse()->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'First archive should succeed.',
    );

    // Second archive must return 409.
    $client->request(method: 'POST', uri: $checklistUri . '/' . $checklistId . '/archive', server: $authHeaders, content: '{}');
    $this->assertSame(
      Response::HTTP_CONFLICT,
      $client->getResponse()->getStatusCode(),
      'Double archiving a checklist should return HTTP 409.',
    );
  }

  /**
   * Tests that all Inspection endpoints require authentication.
   */
  public function testInspectionEndpointsRequireAuthentication(): void
  {
    $client = static::createClientWithFixtures();
    $orgId = '550e8400-e29b-41d4-a716-446655440000';
    $inspId = '550e8400-e29b-41d4-a716-446655440001';

    $endpoints = [
      ['GET', '/api/organizations/' . $orgId . '/inspections'],
      ['GET', '/api/organizations/' . $orgId . '/inspections/' . $inspId],
      ['POST', '/api/organizations/' . $orgId . '/inspections'],
      ['POST', '/api/organizations/' . $orgId . '/inspections/' . $inspId . '/submit'],
      ['POST', '/api/organizations/' . $orgId . '/inspections/' . $inspId . '/close'],
      ['GET', '/api/organizations/' . $orgId . '/inspections/' . $inspId . '/non-conformities'],
      ['POST', '/api/organizations/' . $orgId . '/inspections/' . $inspId . '/non-conformities'],
      ['GET', '/api/organizations/' . $orgId . '/checklists'],
      ['GET', '/api/organizations/' . $orgId . '/checklists/' . $inspId],
      ['POST', '/api/organizations/' . $orgId . '/checklists'],
      ['POST', '/api/organizations/' . $orgId . '/checklists/' . $inspId . '/archive'],
    ];

    foreach ($endpoints as [$method, $uri]) {
      $client->request(
        method: $method,
        uri: $uri,
        server: ['HTTP_ACCEPT' => 'application/ld+json'],
        content: '{}',
      );

      $statusCode = $client->getResponse()->getStatusCode();

      $this->assertContains(
        $statusCode,
        [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
        "Expected 401/403 for unauthenticated {$method} {$uri}, got {$statusCode}.",
      );
    }
  }

  // #endregion

  // #region Helpers
  private function loginAndGetUserAccessToken(KernelBrowser $client, string $email, string $password): string
  {
    $client->request(
      method: 'POST',
      uri: '/api/auth/login',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'email' => $email,
        'password' => $password,
      ]) ?: '',
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'User login should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $token = $data['access_token'] ?? null;

    $this->assertTrue(is_string($token) && '' !== $token, 'Login response should contain access_token.');

    return $token;
  }

  private function createOrganization(KernelBrowser $client, string $token, string $name): ?string
  {
    $client->request(
      method: 'POST',
      uri: '/api/organizations',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode(['name' => $name]) ?: '',
    );

    $data = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');

    return $this->extractResourceId($data);
  }

  private function createEquipment(KernelBrowser $client, string $token, string $organizationId): ?string
  {
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/equipment',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'type' => 'fire_extinguisher',
        'brand' => 'Sicli',
        'model' => 'Pro 6',
        'serialNumber' => 'EXT-INSP-' . uniqid(),
        'locationLabel' => 'Floor 1 – Test',
      ]) ?: '',
    );

    $data = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');

    return $this->extractResourceId($data);
  }

  /**
   * @param array<string, mixed> $data
   */
  private function extractResourceId(array $data): ?string
  {
    $id = $data['id'] ?? null;
    if (is_string($id) && '' !== $id) {
      return $id;
    }

    $iri = $data['@id'] ?? null;
    if (is_string($iri) && str_contains($iri, '/')) {
      $candidate = basename($iri);

      return '' !== $candidate ? $candidate : null;
    }

    return null;
  }

  /**
   * @param array<string, mixed> $data
   *
   * @return list<array<string, mixed>>
   */
  private function getCollectionMembers(array $data): array
  {
    $members = $data['member'] ?? [];
    if (!is_array($members)) {
      return [];
    }

    $result = [];
    foreach ($members as $member) {
      if (!is_array($member)) {
        continue;
      }

      $normalized = [];
      foreach ($member as $key => $value) {
        if (is_string($key)) {
          $normalized[$key] = $value;
        }
      }

      $result[] = $normalized;
    }

    return $result;
  }

  /**
   * @param list<array<string, mixed>> $collection
   */
  private function collectionContainsId(array $collection, string $id): bool
  {
    foreach ($collection as $item) {
      if ($id === $this->extractResourceId($item)) {
        return true;
      }
    }

    return false;
  }
  // #endregion
}
