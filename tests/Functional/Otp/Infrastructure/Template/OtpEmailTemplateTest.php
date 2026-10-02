<?php

declare(strict_types=1);

namespace Tests\Functional\Otp\Infrastructure\Template;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

use function html_entity_decode;

use const ENT_QUOTES;

/**
 * Test OtpEmailTemplateTest.
 *
 * Renders the OTP code email through the real Twig environment: the request details block
 * shows the facts the recipient needs to tell their own sign-in from someone else's, only when
 * they are known, and always escaped.
 *
 * @category Functional Test
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OtpEmailTemplateTest extends KernelTestCase
{
  // #region Methods
  #[Test]
  public function testItShowsTheDateTimeBrowserAndOsOfTheRequest(): void
  {
    $html = $this->render([
      'requestedAt' => new DateTimeImmutable('2026-10-01 09:50:00', new DateTimeZone('Europe/Paris')),
      'origin' => ['browser' => 'Chrome', 'operatingSystem' => 'Windows'],
    ]);

    self::assertStringContainsString('Request details', $html);
    self::assertStringContainsString('1 October 2026, 07:50 UTC', $html, 'The time is shown in UTC whatever the server timezone.');
    self::assertStringContainsString('Chrome', $html);
    self::assertStringContainsString('Windows', $html);
    self::assertStringContainsString('482913', $html);
    self::assertStringContainsString('Verification code', $html);
    self::assertStringContainsString('request a new code', $html);
    self::assertStringContainsString('Never share it with anyone', $html);
  }

  #[Test]
  public function testItOmitsWhatIsUnknown(): void
  {
    $html = $this->render([
      'requestedAt' => new DateTimeImmutable('2026-10-01 07:50:00', new DateTimeZone('UTC')),
      'origin' => ['browser' => 'Firefox', 'operatingSystem' => null],
    ]);

    self::assertStringContainsString('Firefox', $html);
    self::assertStringNotContainsString('Operating system', $html);
  }

  #[Test]
  public function testItHasNoDetailsBlockWithoutAnOrigin(): void
  {
    foreach ([[], ['origin' => null], ['origin' => []]] as $context) {
      $html = $this->render($context + ['requestedAt' => new DateTimeImmutable()]);

      self::assertStringNotContainsString('Request details', $html);
      self::assertStringContainsString('482913', $html);
      self::assertStringContainsString("If you didn't request this code", html_entity_decode($html, ENT_QUOTES));
    }
  }

  #[Test]
  public function testItEscapesTheValuesItDisplays(): void
  {
    $html = $this->render([
      'requestedAt' => new DateTimeImmutable('2026-10-01 07:50:00', new DateTimeZone('UTC')),
      'origin' => ['browser' => '<script>alert(1)</script>', 'operatingSystem' => '<b>Windows</b>'],
    ]);

    self::assertStringNotContainsString('<script>', $html);
    self::assertStringNotContainsString('<b>Windows</b>', $html);
    self::assertStringContainsString('&lt;script&gt;', $html);
  }

  /**
   * Method render.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $context the origin-related variables to add to the fixture
   *
   * @return string the rendered HTML
   */
  private function render(array $context): string
  {
    self::bootKernel();

    /** @var Environment $twig */
    $twig = self::getContainer()->get('twig');

    return $twig->render('otp/email/code.html.twig', $context + [
      'subject' => 'Your login verification code',
      'instruction' => 'Enter the code below on the Fireguard sign-in verification screen to complete your sign-in.',
      'code' => '482913',
      'expiresInMinutes' => 10,
    ]);
  }
  // #endregion
}
