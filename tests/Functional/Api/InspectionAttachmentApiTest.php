<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Test InspectionAttachmentApiTest.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InspectionAttachmentApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  #[Test]
  public function testUploadInspectionAttachmentRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/inspections/' . self::DUMMY_UUID . '/attachments');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'POST /inspections/{id}/attachments endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /inspections/{id}/attachments, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListInspectionAttachmentsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/inspections/' . self::DUMMY_UUID . '/attachments');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /inspections/{id}/attachments endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /inspections/{id}/attachments, got ' . $statusCode,
    );
  }

  #[Test]
  public function testUploadNonConformityAttachmentRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/non-conformities/' . self::DUMMY_UUID . '/attachments');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'POST /non-conformities/{id}/attachments endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /non-conformities/{id}/attachments, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListNonConformityAttachmentsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/non-conformities/' . self::DUMMY_UUID . '/attachments');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /non-conformities/{id}/attachments endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /non-conformities/{id}/attachments, got ' . $statusCode,
    );
  }

  #[Test]
  public function testGetInspectionAttachmentRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/inspection-attachments/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /inspection-attachments/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /inspection-attachments/{id}, got ' . $statusCode,
    );
  }

  #[Test]
  public function testDeleteInspectionAttachmentRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('DELETE', '/api/inspection-attachments/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'DELETE /inspection-attachments/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated DELETE /inspection-attachments/{id}, got ' . $statusCode,
    );
  }
}
