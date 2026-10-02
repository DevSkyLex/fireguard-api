<?php

declare(strict_types=1);

namespace Tests\Functional\Notification\Infrastructure\Template;

use Shared\Application\Contract\GeoIp\IpLocation;
use Shared\Application\Contract\Notification\EmailRequestDetails;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * Checks the actual security email templates, including translated attribution and escaping.
 *
 * @category Functional Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GeoIpEmailTemplateTest extends KernelTestCase
{
  public function testUnknownBrowserStillDisplaysEscapedLocationInEveryLocale(): void
  {
    self::bootKernel();
    $twig = self::getContainer()->get('twig');
    self::assertInstanceOf(Environment::class, $twig);
    foreach (['en' => 'Geolocation by DB-IP', 'fr' => 'Géolocalisation par DB-IP', 'es' => 'Geolocalización por DB-IP'] as $locale => $attribution) {
      foreach (['otp/email/code.html.twig', 'notification/email/user_email_change_confirm.html.twig', 'notification/email/user_email_change_notice.html.twig'] as $template) {
        $html = $twig->render($template, [
          'locale' => $locale, 'code' => '123456', 'purpose' => 'login', 'expiresAt' => '2026-10-01T12:00:00Z',
          'subject' => 'Security request', 'instruction' => 'Verify request', 'expiresInMinutes' => 5,
          'body' => 'Security request', 'confirmUrl' => 'https://app.fireguard.test/confirm',
          'headingKey' => 'emailChange.pendingHeading', 'bodyKey' => 'emailChange.pendingBody',
          'origin' => null, 'requestDetails' => new EmailRequestDetails(location: new IpLocation('FR', '<script>alert(1)</script>')),
        ]);
        self::assertStringContainsString($attribution, $html);
        self::assertStringContainsString('href="https://db-ip.com/"', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>', $html);
      }
    }
    self::assertStringNotContainsString('href="https://db-ip.com/"', $twig->render('otp/email/code.html.twig', ['code' => '123456', 'subject' => 'Verify', 'instruction' => 'Verify', 'expiresInMinutes' => 5]));
  }
}
