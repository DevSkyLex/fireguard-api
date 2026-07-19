<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Test InterventionAttachmentApiTest.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionAttachmentApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  #[Test]
  public function testUploadInterventionAttachmentRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/interventions/' . self::DUMMY_UUID . '/attachments');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'POST /interventions/{id}/attachments endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /interventions/{id}/attachments, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListInterventionAttachmentsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/interventions/' . self::DUMMY_UUID . '/attachments');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /interventions/{id}/attachments endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /interventions/{id}/attachments, got ' . $statusCode,
    );
  }

  #[Test]
  public function testGetInterventionAttachmentRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/intervention-attachments/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /intervention-attachments/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /intervention-attachments/{id}, got ' . $statusCode,
    );
  }

  #[Test]
  public function testDeleteInterventionAttachmentRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('DELETE', '/api/intervention-attachments/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'DELETE /intervention-attachments/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated DELETE /intervention-attachments/{id}, got ' . $statusCode,
    );
  }
}
