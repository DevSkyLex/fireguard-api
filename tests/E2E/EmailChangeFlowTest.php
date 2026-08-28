<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Email;

use function implode;
use function is_string;
use function json_encode;
use function preg_match;
use function sprintf;

/**
 * Test EmailChangeFlowTest.
 *
 * Full sign-in email change flow against the seeded databases:
 * password-verified request (two real emails out, Mailpit-style
 * assertions on both), public token confirmation, session revocation,
 * and re-login with the new address. Plus the refusals: wrong
 * password, address already taken, token reuse, and cancellation.
 *
 * @category E2E Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EmailChangeFlowTest extends OAuth2WebTestCase
{
  private const string NEW_EMAIL = 'e2e-email-change@example.com';

  // #region Tests
  #[Test]
  public function testFullEmailChangeFlowFromRequestToReLogin(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::SEEDED_ADMIN_EMAIL, self::SEEDED_ADMIN_PASSWORD);

    // 1. Request the change (authenticated, password verified) -> 202.
    $this->requestEmailChange($client, $token, self::NEW_EMAIL, self::SEEDED_ADMIN_PASSWORD);
    $response = $client->getResponse();
    self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode(), 'Request should be accepted: ' . $response->getContent());

    // 2. Two emails left in this request: the confirmation (with the
    //    token link) to the NEW address, the alert to the OLD one.
    // The message logger records each email twice (once when the Mailer
    // hands it to Messenger, once when the sync transport sends it), so
    // count distinct deliveries rather than raw log entries.
    $messages = self::getMailerMessages();
    self::assertCount(2, $this->distinctDeliveries($messages), 'Exactly two distinct emails should have been sent.');

    $confirmEmail = $this->emailTo($messages, self::NEW_EMAIL);
    $alertEmail = $this->emailTo($messages, self::SEEDED_ADMIN_EMAIL);

    $confirmBody = (string) $confirmEmail->getHtmlBody();
    $alertBody = (string) $alertEmail->getHtmlBody();
    self::assertStringContainsString('token=', $confirmBody, 'Confirmation email must carry the token link.');
    self::assertStringNotContainsString('token=', $alertBody, 'The old-address alert must NOT carry the token.');

    $rawToken = $this->extractToken($confirmBody);

    // 3. Confirm publicly (no Authorization header) -> 200.
    $this->confirmEmailChange($client, $rawToken);
    $response = $client->getResponse();
    self::assertSame(Response::HTTP_OK, $response->getStatusCode(), 'Confirm should succeed: ' . $response->getContent());

    // The effective-change notice goes to the OLD address only.
    $notices = self::getMailerMessages();
    self::assertCount(1, $this->distinctDeliveries($notices));
    $this->emailTo($notices, self::SEEDED_ADMIN_EMAIL);

    // 4. Single use: replaying the same token is refused neutrally.
    $this->confirmEmailChange($client, $rawToken);
    self::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

    // 5. Session revocation itself cannot be observed here: login-flow
    //    session tracking is deliberately best-effort (LoginHandler
    //    swallows recording failures) and records nothing in this
    //    harness, and per SECURITY.md a token whose session was never
    //    recorded stays accepted. The revokeAllForUser +
    //    revokeAllUserTokens calls are pinned by the handler unit test
    //    instead (ConfirmEmailChangeHandlerTest).

    // 6. The old address no longer signs in; the new one does.
    $client->request(
      method: 'POST',
      uri: '/api/auth/login',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'email' => self::SEEDED_ADMIN_EMAIL,
        'password' => self::SEEDED_ADMIN_PASSWORD,
      ]) ?: '',
    );
    self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode(), 'The old address must not sign in any more.');

    $newToken = $this->loginAndGetUserAccessToken($client, self::NEW_EMAIL, self::SEEDED_ADMIN_PASSWORD);
    self::assertNotSame('', $newToken);
  }

  #[Test]
  public function testRequestIsRefusedWithWrongPasswordAndTakenOrIdenticalEmail(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::SEEDED_ADMIN_EMAIL, self::SEEDED_ADMIN_PASSWORD);

    // Wrong password -> 422, and no email leaves.
    $this->requestEmailChange($client, $token, self::NEW_EMAIL, 'WrongP@ssw0rd!');
    self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    self::assertCount(0, self::getMailerMessages());

    // Identical to the current address -> neutral 409.
    $this->requestEmailChange($client, $token, self::SEEDED_ADMIN_EMAIL, self::SEEDED_ADMIN_PASSWORD);
    $response = $client->getResponse();
    self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
    self::assertStringContainsString('This email address cannot be used.', (string) $response->getContent());

    // Taken by another seeded account -> the SAME neutral 409.
    $this->requestEmailChange($client, $token, 'marie.lefevre@fireguard.local', self::SEEDED_ADMIN_PASSWORD);
    $response = $client->getResponse();
    self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
    self::assertStringContainsString('This email address cannot be used.', (string) $response->getContent());
  }

  #[Test]
  public function testCancelInvalidatesThePendingToken(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::SEEDED_ADMIN_EMAIL, self::SEEDED_ADMIN_PASSWORD);

    $this->requestEmailChange($client, $token, self::NEW_EMAIL, self::SEEDED_ADMIN_PASSWORD);
    self::assertSame(Response::HTTP_ACCEPTED, $client->getResponse()->getStatusCode());
    $rawToken = $this->extractToken((string) $this->emailTo(self::getMailerMessages(), self::NEW_EMAIL)->getHtmlBody());

    // Cancel -> 204; cancelling again stays 204 (idempotent).
    $this->cancelEmailChange($client, $token);
    self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
    $this->cancelEmailChange($client, $token);
    self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

    // The cancelled token no longer confirms.
    $this->confirmEmailChange($client, $rawToken);
    self::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testNewRequestReplacesThePreviousToken(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::SEEDED_ADMIN_EMAIL, self::SEEDED_ADMIN_PASSWORD);

    $this->requestEmailChange($client, $token, self::NEW_EMAIL, self::SEEDED_ADMIN_PASSWORD);
    $firstToken = $this->extractToken((string) $this->emailTo(self::getMailerMessages(), self::NEW_EMAIL)->getHtmlBody());

    $this->requestEmailChange($client, $token, 'e2e-email-change-2@example.com', self::SEEDED_ADMIN_PASSWORD);
    self::assertSame(Response::HTTP_ACCEPTED, $client->getResponse()->getStatusCode());

    // The first token was replaced and must not confirm any more.
    $this->confirmEmailChange($client, $firstToken);
    self::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
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
    self::assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Login should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $token = $data['access_token'] ?? null;

    self::assertTrue(is_string($token) && '' !== $token, 'Login response should contain access_token.');

    return $token;
  }

  private function requestEmailChange(KernelBrowser $client, string $accessToken, string $newEmail, string $password): void
  {
    $client->request(
      method: 'POST',
      uri: '/api/me/email-change',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $accessToken,
      ],
      content: json_encode([
        'newEmail' => $newEmail,
        'currentPassword' => $password,
      ]) ?: '',
    );
  }

  private function confirmEmailChange(KernelBrowser $client, string $rawToken): void
  {
    $client->request(
      method: 'POST',
      uri: '/api/me/email-change/confirm',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode(['token' => $rawToken]) ?: '',
    );
  }

  private function cancelEmailChange(KernelBrowser $client, string $accessToken): void
  {
    $client->request(
      method: 'DELETE',
      uri: '/api/me/email-change',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $accessToken,
      ],
    );
  }

  /**
   * Deduplicates logged messages into distinct deliveries.
   *
   * @param array<\Symfony\Component\Mime\RawMessage> $messages the collected mailer messages
   *
   * @return array<string, Email> distinct deliveries keyed by recipient+subject
   */
  private function distinctDeliveries(array $messages): array
  {
    $distinct = [];
    foreach ($messages as $message) {
      if (!$message instanceof Email) {
        continue;
      }

      $recipients = [];
      foreach ($message->getTo() as $address) {
        $recipients[] = $address->getAddress();
      }

      $distinct[implode(',', $recipients) . '|' . $message->getSubject()] = $message;
    }

    return $distinct;
  }

  /**
   * Finds the message addressed to the given recipient.
   *
   * @param array<\Symfony\Component\Mime\RawMessage> $messages the collected mailer messages
   */
  private function emailTo(array $messages, string $recipient): Email
  {
    foreach ($messages as $message) {
      if (!$message instanceof Email) {
        continue;
      }

      foreach ($message->getTo() as $address) {
        if ($address->getAddress() === $recipient) {
          return $message;
        }
      }
    }

    self::fail(sprintf('No email addressed to "%s" was sent.', $recipient));
  }

  private function extractToken(string $htmlBody): string
  {
    $matches = [];
    $found = preg_match('/token=([0-9a-f]{64})/', $htmlBody, $matches);
    self::assertSame(1, $found, 'The confirmation email must contain a 64-hex-char token link.');

    return $matches[1];
  }
  // #endregion
}
