<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\{File\UploadedFile, Response};
use User\Infrastructure\Image\AvatarResizer;

use function assert;
use function base64_decode;
use function file_exists;
use function file_put_contents;
use function is_string;
use function json_encode;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const UPLOAD_ERR_OK;

final class CurrentUserProfileFlowTest extends OAuth2WebTestCase
{
  #[Test]
  public function testCurrentUserCanUpdateProfileAndAvatar(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    self::assertNotNull($token, 'Should be able to obtain access token.');

    $client->request(
      method: 'PATCH',
      uri: '/api/me',
      server: [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([
        'firstName' => 'Updated',
        'lastName' => 'Profile',
      ]) ?: '',
    );

    self::assertSame(
      Response::HTTP_OK,
      $client->getResponse()->getStatusCode(),
      'Current user profile update should succeed. Response: ' . $client->getResponse()->getContent(),
    );

    $profile = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertSame(self::DEV_CLIENT_ID, $profile['id'] ?? null);
    self::assertSame('Updated', $profile['firstName'] ?? null);
    self::assertSame('Profile', $profile['lastName'] ?? null);

    $tempFile = tempnam(sys_get_temp_dir(), 'current_user_avatar_');
    file_put_contents($tempFile, $this->minimalPngBinary());

    $avatarResizer = static::getContainer()->get(AvatarResizer::class);
    assert($avatarResizer instanceof AvatarResizer);

    try {
      $client->request(
        method: 'PUT',
        uri: '/api/me/avatar',
        files: [
          'avatar' => new UploadedFile(
            path: $tempFile,
            originalName: 'avatar.png',
            mimeType: 'image/png',
            error: UPLOAD_ERR_OK,
            test: true,
          ),
        ],
        server: [
          'HTTP_ACCEPT' => 'application/ld+json',
          'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ],
      );

      self::assertSame(
        Response::HTTP_OK,
        $client->getResponse()->getStatusCode(),
        'Current user avatar upload should succeed. Response: ' . $client->getResponse()->getContent(),
      );

      $profile = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
      self::assertSame(self::DEV_CLIENT_ID, $profile['id'] ?? null);
      $avatarUrl = $profile['avatarUrl'] ?? null;
      self::assertIsString($avatarUrl);
      self::assertStringContainsString('/api/users/' . self::DEV_CLIENT_ID . '/avatar', $avatarUrl);
    } finally {
      $avatarResizer->delete(self::DEV_CLIENT_ID);
      if (file_exists($tempFile)) {
        unlink($tempFile);
      }
    }
  }

  #[Test]
  public function testCurrentUserCanDeactivateOwnAccountAndItIsIdempotent(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);

    self::assertNotNull($token, 'Should be able to obtain access token.');

    $client->request(
      method: 'POST',
      uri: '/api/me/deactivate',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    self::assertSame(
      Response::HTTP_OK,
      $client->getResponse()->getStatusCode(),
      'Self-service deactivation should succeed. Response: ' . $client->getResponse()->getContent(),
    );

    $profile = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertSame(self::DEV_CLIENT_ID, $profile['id'] ?? null);
    self::assertSame('inactive', $profile['status'] ?? null);

    // Idempotent: deactivating an already-inactive account must not fail.
    $client->request(
      method: 'POST',
      uri: '/api/me/deactivate',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    self::assertSame(
      Response::HTTP_OK,
      $client->getResponse()->getStatusCode(),
      'Repeated self-service deactivation should stay idempotent. Response: ' . $client->getResponse()->getContent(),
    );

    $profileAgain = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertSame('inactive', $profileAgain['status'] ?? null);
  }

  private function minimalPngBinary(): string
  {
    $binary = base64_decode(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
      true,
    );
    assert(is_string($binary));

    return $binary;
  }
}
