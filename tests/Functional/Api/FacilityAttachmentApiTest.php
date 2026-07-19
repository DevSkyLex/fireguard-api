<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Test FacilityAttachmentApiTest.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityAttachmentApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  #[Test]
  public function testUploadFacilityAttachmentRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/facilities/' . self::DUMMY_UUID . '/attachments');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'POST /facilities/{id}/attachments endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /facilities/{id}/attachments, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListFacilityAttachmentsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/facilities/' . self::DUMMY_UUID . '/attachments');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /facilities/{id}/attachments endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /facilities/{id}/attachments, got ' . $statusCode,
    );
  }

  #[Test]
  public function testGetFacilityAttachmentRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/facility-attachments/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /facility-attachments/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /facility-attachments/{id}, got ' . $statusCode,
    );
  }

  #[Test]
  public function testDeleteFacilityAttachmentRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('DELETE', '/api/facility-attachments/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'DELETE /facility-attachments/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated DELETE /facility-attachments/{id}, got ' . $statusCode,
    );
  }
}
