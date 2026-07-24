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
 * Test BillingPresentationFlow.
 *
 * @category E2E Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class BillingPresentationFlowTest extends OAuth2WebTestCase
{
  private const string PLACEHOLDER_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440000';

  /**
   * GET /api/billing/pricing — authenticated members can read the plan pricing
   * catalog. Fully config-driven, so it never touches Stripe.
   */
  public function testListBillingPricingReturnsCatalog(): void
  {
    $client = static::createClientWithFixtures();

    $ownerEmail = 'billing-pricing-' . uniqid() . '@example.com';
    $ownerPassword = 'OwnerPassword123!';

    $this->createAndActivateUser($client, $ownerEmail, $ownerPassword);
    $ownerToken = $this->loginAndGetUserAccessToken($client, $ownerEmail, $ownerPassword);

    $client->request(
      method: 'GET',
      uri: '/api/billing/pricing',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Listing billing pricing should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $members = $this->getCollectionMembers($data);

    // Each pricing entry exposes at least its plan key and currency.
    foreach ($members as $member) {
      $this->assertArrayHasKey('planKey', $member, 'Each pricing entry should expose a planKey.');
      $this->assertArrayHasKey('currency', $member, 'Each pricing entry should expose a currency.');
    }

    // Structural fact: the payload is a Hydra collection (member is always present).
    $this->assertArrayHasKey('member', $data, 'Pricing response should be a Hydra collection.');
  }

  /**
   * GET /api/organizations/{id}/billing/subscription — the owner reads the
   * subscription state. A fresh organization has no local subscription row, so
   * the handler answers from the plan catalog without calling Stripe.
   */
  public function testGetOrganizationSubscriptionReturnsState(): void
  {
    $client = static::createClientWithFixtures();

    $ownerEmail = 'billing-subscription-' . uniqid() . '@example.com';
    $ownerPassword = 'OwnerPassword123!';

    $this->createAndActivateUser($client, $ownerEmail, $ownerPassword);
    $ownerToken = $this->loginAndGetUserAccessToken($client, $ownerEmail, $ownerPassword);
    $organizationId = $this->createOrganization($client, $ownerToken, 'Billing Subscription Org ' . uniqid());
    $this->assertNotNull($organizationId, 'Organization should be created successfully.');

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/billing/subscription',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Reading the organization subscription should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $this->assertArrayHasKey('organizationId', $data);
    $this->assertArrayHasKey('hasSubscription', $data);
    $this->assertArrayHasKey('active', $data);
    $this->assertArrayHasKey('cancelAtPeriodEnd', $data);
    // A brand new organization has no live subscription.
    $this->assertFalse($data['hasSubscription'] ?? true, 'A fresh organization should have no subscription.');
  }

  /**
   * GET /api/organizations/{id}/billing/payment-method — the owner reads the
   * saved-card state. With no billing customer, the handler returns
   * `hasPaymentMethod: false` without reaching Stripe.
   */
  public function testGetOrganizationPaymentMethodReturnsState(): void
  {
    $client = static::createClientWithFixtures();

    $ownerEmail = 'billing-payment-method-' . uniqid() . '@example.com';
    $ownerPassword = 'OwnerPassword123!';

    $this->createAndActivateUser($client, $ownerEmail, $ownerPassword);
    $ownerToken = $this->loginAndGetUserAccessToken($client, $ownerEmail, $ownerPassword);
    $organizationId = $this->createOrganization($client, $ownerToken, 'Billing Payment Method Org ' . uniqid());
    $this->assertNotNull($organizationId, 'Organization should be created successfully.');

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/billing/payment-method',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Reading the organization payment method should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $this->assertArrayHasKey('organizationId', $data);
    $this->assertArrayHasKey('hasPaymentMethod', $data);
    $this->assertFalse($data['hasPaymentMethod'] ?? true, 'A fresh organization should have no saved card.');
  }

  /**
   * GET /api/organizations/{id}/billing/invoices — the owner reads billing
   * history. With no billing customer, the handler returns an empty collection
   * without reaching Stripe.
   */
  public function testListOrganizationInvoicesReturnsCollection(): void
  {
    $client = static::createClientWithFixtures();

    $ownerEmail = 'billing-invoices-' . uniqid() . '@example.com';
    $ownerPassword = 'OwnerPassword123!';

    $this->createAndActivateUser($client, $ownerEmail, $ownerPassword);
    $ownerToken = $this->loginAndGetUserAccessToken($client, $ownerEmail, $ownerPassword);
    $organizationId = $this->createOrganization($client, $ownerToken, 'Billing Invoices Org ' . uniqid());
    $this->assertNotNull($organizationId, 'Organization should be created successfully.');

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/billing/invoices',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Listing organization invoices should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $this->assertArrayHasKey('member', $data, 'Invoices response should be a Hydra collection.');
    $this->assertTrue(is_array($data['member'] ?? null), 'Invoices collection member should be a list.');
  }

  /**
   * POST /api/organizations/{id}/billing/cancel — an authenticated owner on a
   * fresh organization has no active subscription, so the processor resolves
   * deterministically to 409 (or 403 when the role lacks settings.write) without
   * calling Stripe. This proves the route, security and processor wiring.
   */
  public function testCancelSubscriptionWithoutActiveSubscriptionIsHandled(): void
  {
    $client = static::createClientWithFixtures();

    $ownerEmail = 'billing-cancel-' . uniqid() . '@example.com';
    $ownerPassword = 'OwnerPassword123!';

    $this->createAndActivateUser($client, $ownerEmail, $ownerPassword);
    $ownerToken = $this->loginAndGetUserAccessToken($client, $ownerEmail, $ownerPassword);
    $organizationId = $this->createOrganization($client, $ownerToken, 'Billing Cancel Org ' . uniqid());
    $this->assertNotNull($organizationId, 'Organization should be created successfully.');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/billing/cancel',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
      content: json_encode([]) ?: '',
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_CONFLICT, Response::HTTP_FORBIDDEN],
      'Cancelling with no active subscription should be a handled conflict (or forbidden). Response: ' . $response->getContent(),
    );
  }

  /**
   * POST /api/organizations/{id}/billing/resume — mirror of cancel: with no
   * subscription the processor resolves to 409 (or 403) without calling Stripe.
   */
  public function testResumeSubscriptionWithoutSubscriptionIsHandled(): void
  {
    $client = static::createClientWithFixtures();

    $ownerEmail = 'billing-resume-' . uniqid() . '@example.com';
    $ownerPassword = 'OwnerPassword123!';

    $this->createAndActivateUser($client, $ownerEmail, $ownerPassword);
    $ownerToken = $this->loginAndGetUserAccessToken($client, $ownerEmail, $ownerPassword);
    $organizationId = $this->createOrganization($client, $ownerToken, 'Billing Resume Org ' . uniqid());
    $this->assertNotNull($organizationId, 'Organization should be created successfully.');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/billing/resume',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
      content: json_encode([]) ?: '',
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_CONFLICT, Response::HTTP_FORBIDDEN],
      'Resuming with no subscription should be a handled conflict (or forbidden). Response: ' . $response->getContent(),
    );
  }

  /**
   * POST /api/organizations/{id}/billing/portal — with no billing customer the
   * processor resolves to 409 (or 403) before reaching Stripe.
   */
  public function testStartPortalWithoutBillingCustomerIsHandled(): void
  {
    $client = static::createClientWithFixtures();

    $ownerEmail = 'billing-portal-' . uniqid() . '@example.com';
    $ownerPassword = 'OwnerPassword123!';

    $this->createAndActivateUser($client, $ownerEmail, $ownerPassword);
    $ownerToken = $this->loginAndGetUserAccessToken($client, $ownerEmail, $ownerPassword);
    $organizationId = $this->createOrganization($client, $ownerToken, 'Billing Portal Org ' . uniqid());
    $this->assertNotNull($organizationId, 'Organization should be created successfully.');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/billing/portal',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerToken,
      ],
      content: json_encode([]) ?: '',
    );

    $response = $client->getResponse();
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_CONFLICT, Response::HTTP_FORBIDDEN],
      'Opening the portal with no billing customer should be a handled conflict (or forbidden). Response: ' . $response->getContent(),
    );
  }

  /**
   * POST /api/billing/webhook — a public route (Stripe verifies the signature
   * inside the controller). An unsigned payload must be rejected, never routed
   * to a 404, proving the controller is wired.
   */
  public function testStripeWebhookRejectsUnsignedPayload(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'POST',
      uri: '/api/billing/webhook',
      server: [
        'CONTENT_TYPE' => 'application/json',
      ],
      content: json_encode(['type' => 'checkout.session.completed']) ?: '',
    );

    $response = $client->getResponse();
    $this->assertNotSame(
      Response::HTTP_NOT_FOUND,
      $response->getStatusCode(),
      'The Stripe webhook route should exist. Response: ' . $response->getContent(),
    );
    $this->assertGreaterThanOrEqual(
      Response::HTTP_BAD_REQUEST,
      $response->getStatusCode(),
      'An unsigned webhook payload must be rejected. Response: ' . $response->getContent(),
    );
  }

  /**
   * Every organization-scoped billing endpoint and the pricing catalog require
   * authentication. This is the reliable negative assertion for the whole
   * Billing Presentation surface.
   */
  public function testBillingEndpointsRequireAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $organizationId = self::PLACEHOLDER_ORGANIZATION_ID;

    $cases = [
      ['GET', '/api/billing/pricing'],
      ['GET', '/api/organizations/' . $organizationId . '/billing/subscription'],
      ['GET', '/api/organizations/' . $organizationId . '/billing/payment-method'],
      ['GET', '/api/organizations/' . $organizationId . '/billing/invoices'],
      ['POST', '/api/organizations/' . $organizationId . '/billing/checkout'],
      ['POST', '/api/organizations/' . $organizationId . '/billing/portal'],
      ['POST', '/api/organizations/' . $organizationId . '/billing/cancel'],
      ['POST', '/api/organizations/' . $organizationId . '/billing/resume'],
    ];

    foreach ($cases as [$method, $uri]) {
      $client->request(
        method: $method,
        uri: $uri,
        server: [
          'CONTENT_TYPE' => 'application/ld+json',
          'HTTP_ACCEPT' => 'application/ld+json',
        ],
        content: 'POST' === $method ? (json_encode([]) ?: '') : '',
      );

      $status = $client->getResponse()->getStatusCode();

      $this->assertNotSame(
        Response::HTTP_NOT_FOUND,
        $status,
        "Billing route {$method} {$uri} should exist.",
      );
      $this->assertContains(
        $status,
        [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
        "Billing route {$method} {$uri} should require authentication. Status: {$status}",
      );
    }
  }

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

  // #endregion
}
