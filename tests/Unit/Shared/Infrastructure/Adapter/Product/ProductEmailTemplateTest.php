<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Adapter\Product;

use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Tests\Support\Factory\EmailTranslatorTestFactory;
use Tests\Support\Shared\ProductConfigFixture;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

use function dirname;

/**
 * Class ProductEmailTemplateTest
 *
 * @category Test
 */
final class ProductEmailTemplateTest extends TestCase
{
  #[Test]
  public function preservesTheDefaultHeaderTitleAndFooter(): void
  {
    $html = $this->templates()->render('notification/email/layout.html.twig');

    self::assertStringContainsString('<title>Fireguard</title>', $html);
    self::assertStringContainsString('src="cid:fireguard-mark"', $html);
    self::assertStringContainsString('>Fireguard</td>', $html);
    self::assertStringContainsString('Fireguard — fire-safety field operations platform.', $html);
    self::assertStringNotContainsString('%product%', $html);
  }

  #[Test]
  #[DataProvider('localizedTemplates')]
  public function rendersTheConfiguredNameAndCidThroughoutTheEmail(string $template, string $locale): void
  {
    $html = $this->templates('Workshop', 'workshop-mark')->render('notification/email/' . $template . '.html.twig', [
      'subject' => 'Account notice',
      'body' => 'Notification body',
      'organizationName' => 'Example organization',
      'acceptUrl' => 'https://workshop.example.test/invitation?token=example',
      'confirmUrl' => 'https://workshop.example.test/confirm?token=example',
      'expiresAt' => '2026-10-11',
      'headingKey' => 'emailChange.pendingHeading',
      'bodyKey' => 'emailChange.pendingBody',
      'locale' => $locale,
    ]);

    self::assertStringContainsString('Workshop', $html);
    self::assertStringContainsString('src="cid:workshop-mark"', $html);
    self::assertStringNotContainsString('Fireguard', $html);
    self::assertStringNotContainsString('%product%', $html);
  }

  #[Test]
  public function escapesConfiguredNamesInTranslatedHtml(): void
  {
    $html = $this->templates('Workshop <script>alert(1)</script>')->render('notification/email/organization_invitation.html.twig', [
      'subject' => 'Invitation',
      'organizationName' => 'Example organization',
      'acceptUrl' => 'https://workshop.example.test/invitation?token=example',
      'expiresAt' => '2026-10-11',
    ]);

    self::assertStringContainsString('Workshop &lt;script&gt;alert(1)&lt;/script&gt;', $html);
    self::assertStringNotContainsString('<script>', $html);
  }

  /**
   * @return iterable<string, array{string, string}> the branded transactional templates and supported locales
   */
  public static function localizedTemplates(): iterable
  {
    foreach (['en', 'fr', 'es'] as $locale) {
      foreach (['default', 'organization_invitation', 'user_email_change_confirm', 'user_email_change_notice'] as $template) {
        yield $template . ' ' . $locale => [$template, $locale];
      }
    }
  }

  private function templates(?string $publicName = null, ?string $contentId = null): Environment
  {
    $overrides = [];
    if (null !== $publicName) {
      $overrides['product.public_name'] = $publicName;
    }
    if (null !== $contentId) {
      $overrides['product.email_mark_cid'] = $contentId;
    }
    $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 6) . '/templates'), ['autoescape' => 'html', 'strict_variables' => true]);
    $twig->addExtension(new TranslationExtension(EmailTranslatorTestFactory::create()));
    $twig->addGlobal('product', ProductConfigFixture::create($overrides));

    return $twig;
  }
}
