<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function is_string;
use function json_encode;

/**
 * Test BillingWriteFlow.
 *
 * Exercises the write side of the organization billing surface — Checkout,
 * Cancel and Resume — with authenticated admin HTTP requests to raise
 * Presentation-layer coverage of the corresponding processors.
 *
 * The seeded admin (admin@fireguard.local) is the owner of the seeded
 * organization and holds the `organization.*` wildcard, so it clears the
 * `organization.settings.write` gate every processor enforces. The seeded
 * organization has no billing subscription, so the write use cases resolve
 * deterministically to their documented error branches (400 for an
 * unavailable plan, 409 for a missing subscription) without ever reaching
 * Stripe. A genuine 2xx from these endpoints requires live Stripe state that
 * the hermetic E2E environment does not provide, so it is intentionally not
 * asserted here.
 *
 * @category E2E Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class BillingWriteFlowTest extends OAuth2WebTestCase
{
  private const string ADMIN_EMAIL = 'admin@fireguard.local';

  private const string ADMIN_PASSWORD = 'Admin123!';

  /**
   * POST /api/organizations/{id}/billing/checkout — the admin passes the
   * settings.write gate, so the StartCheckoutProcessor dispatches the command.
   * Requesting a plan that has no configured Stripe price makes the handler
   * throw before any Stripe call; the processor unwraps it to a 400. This
   * drives StartCheckoutProcessor::process end to end (auth, permission,
   * dispatch and the failure-mapping branch).
   */
  public function testAdminStartCheckoutForUnavailablePlanIsRejected(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/billing/checkout',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode(['planKey' => 'free', 'interval' => 'month']) ?: '',
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_BAD_REQUEST,
      $response->getStatusCode(),
      'An unavailable plan should be rejected with a 400. Response: ' . $response->getContent(),
    );

    // RFC 7807 problem+json body is always present on the mapped error.
    $body = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $this->assertNotEmpty($body, 'The checkout rejection should carry an error body.');
  }

  /**
   * POST /api/organizations/{id}/billing/cancel — the admin passes the
   * settings.write gate, so the CancelSubscriptionProcessor dispatches the
   * command. The seeded organization has no subscription, so the handler
   * raises NoActiveSubscriptionException before touching Stripe and the
   * processor maps it to a 409, exercising its dispatch and conflict branch.
   */
  public function testAdminCancelSubscriptionWithoutActiveSubscriptionConflicts(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/billing/cancel',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([]) ?: '',
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_CONFLICT,
      $response->getStatusCode(),
      'Cancelling with no active subscription should be a 409. Response: ' . $response->getContent(),
    );
  }

  /**
   * POST /api/organizations/{id}/billing/resume — mirror of cancel: the admin
   * clears the settings.write gate, the ResumeSubscriptionProcessor dispatches
   * the command, and with no subscription the handler raises
   * NoActiveSubscriptionException before any Stripe call, mapped to a 409.
   */
  public function testAdminResumeSubscriptionWithoutSubscriptionConflicts(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/billing/resume',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: json_encode([]) ?: '',
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_CONFLICT,
      $response->getStatusCode(),
      'Resuming with no subscription should be a 409. Response: ' . $response->getContent(),
    );
  }

  /**
   * Each billing write route rejects an unauthenticated caller with 401/403 —
   * never a 404 — proving the route exists and is guarded before the processor
   * runs.
   */
  public function testBillingWriteRoutesRequireAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $organizationId = OrganizationFixtures::ORGANIZATION_ID;

    /** @var list<string> $routes */
    $routes = [
      '/api/organizations/' . $organizationId . '/billing/checkout',
      '/api/organizations/' . $organizationId . '/billing/cancel',
      '/api/organizations/' . $organizationId . '/billing/resume',
    ];

    foreach ($routes as $uri) {
      $client->request(
        method: 'POST',
        uri: $uri,
        server: [
          'CONTENT_TYPE' => 'application/ld+json',
          'HTTP_ACCEPT' => 'application/ld+json',
        ],
        content: json_encode([]) ?: '',
      );

      $status = $client->getResponse()->getStatusCode();

      $this->assertNotSame(
        Response::HTTP_NOT_FOUND,
        $status,
        "Billing write route POST {$uri} should exist.",
      );
      $this->assertContains(
        $status,
        [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
        "Billing write route POST {$uri} should require authentication. Status: {$status}",
      );
    }
  }

  // #region Helpers

  /**
   * Authenticate a seeded, active user through the password login endpoint and
   * return its bearer access token. MFA is disabled under PHPUnit, so login
   * yields tokens directly.
   */
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
      'Admin login should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $token = $data['access_token'] ?? null;

    $this->assertTrue(is_string($token) && '' !== $token, 'Login response should contain access_token.');

    return $token;
  }

  // #endregion
}
