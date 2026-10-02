<?php

declare(strict_types=1);

namespace Tests\Functional\Notification\Infrastructure\Template;

use PHPUnit\Framework\Attributes\{DataProvider, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

use function html_entity_decode;
use function strpos;

use const ENT_QUOTES;

/**
 * Test NotificationEmailTemplatesTest.
 *
 * Renders the notification emails through the real Twig environment and translation catalogs:
 * the notification content sits in a card, the text around it does not, and no catalog key
 * leaks into the output.
 *
 * @category Functional Test
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NotificationEmailTemplatesTest extends KernelTestCase
{
  // #region Methods
  #[Test]
  public function testTheDefaultNotificationBodyIsInACardAndTheTextAroundItIsNot(): void
  {
    $html = $this->render('notification/email/default.html.twig', [
      'subject' => 'Intervention submitted for review',
      'body' => '"Annual check" was submitted and awaits review.',
    ]);

    $heading = (int) strpos($html, '<h1>Intervention submitted for review</h1>');
    $cardStart = (int) strpos($html, 'class="item-card"');
    $body = (int) strpos($html, 'was submitted and awaits review.');
    $cardEnd = (int) strpos($html, '</table>', $cardStart);
    $note = (int) strpos($html, 'You are receiving this email because of your notification settings');

    self::assertGreaterThan(0, $heading);
    self::assertLessThan($cardStart, $heading, 'The heading precedes the card.');
    self::assertTrue($cardStart < $body && $body < $cardEnd, 'The notification body is inside the card.');
    self::assertGreaterThan($cardEnd, $note, 'The settings note follows the card.');
  }

  #[Test]
  public function testTheOnboardingSummaryIsInACardWithTheCompletionTimeInUtc(): void
  {
    $html = $this->render('notification/email/onboarding_organization_completed.html.twig', [
      'subject' => 'Your organization is ready!',
      'organizationId' => '0198f6e2-6a3c-7c11-9b6b-5d2f6b7a1c11',
      'completedAt' => '2026-10-01T09:50:00+02:00',
    ]);

    self::assertStringContainsString('class="details-card"', $html);
    self::assertStringContainsString('0198f6e2-6a3c-7c11-9b6b-5d2f6b7a1c11', $html);
    self::assertStringContainsString('1 October 2026, 07:50 UTC', $html);
  }

  /**
   * Method invitationExpectations.
   *
   * @static
   *
   * @return array<string, array{string, string, string}> locale, heading fragment, ignore sentence fragment
   */
  public static function invitationExpectations(): array
  {
    return [
      'english' => ['en', "You're invited to join", "weren't expecting this invitation"],
      'french' => ['fr', 'Vous êtes invité à rejoindre', "n'attendiez pas cette invitation"],
      'spanish' => ['es', 'Te han invitado a unirte', 'no esperabas esta invitación'],
    ];
  }

  #[Test]
  #[DataProvider('invitationExpectations')]
  public function testTheInvitationExplainsWhatToDoIfItWasNotExpected(string $locale, string $heading, string $ignore): void
  {
    $html = html_entity_decode($this->render('notification/email/organization_invitation.html.twig', [
      'locale' => $locale,
      'organizationName' => 'ACME',
      'acceptUrl' => 'https://app.fireguard.test/organizations/invitations/accept?token=abc',
      'expiresAt' => '08/10/2026',
    ]), ENT_QUOTES);

    self::assertStringContainsString($heading, $html);
    self::assertStringContainsString($ignore, $html);
    self::assertStringNotContainsString('invitation.', $html, 'A raw translation key in the output means a missing catalog entry.');
  }

  /**
   * Method render.
   *
   * @since 1.0.0
   *
   * @param string $template the template under test
   * @param array<string, mixed> $context the template context
   *
   * @return string the rendered HTML
   */
  private function render(string $template, array $context): string
  {
    self::bootKernel();

    /** @var Environment $twig */
    $twig = self::getContainer()->get('twig');

    return $twig->render($template, $context);
  }
  // #endregion
}
