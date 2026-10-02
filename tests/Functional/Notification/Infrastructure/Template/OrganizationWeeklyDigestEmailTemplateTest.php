<?php

declare(strict_types=1);

namespace Tests\Functional\Notification\Infrastructure\Template;

use PHPUnit\Framework\Attributes\{DataProvider, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

use function html_entity_decode;
use function strpos;
use function substr;
use function substr_count;

use const ENT_QUOTES;

/**
 * Test OrganizationWeeklyDigestEmailTemplateTest.
 *
 * Renders the weekly digest email template through the real Twig
 * environment and translation catalogs, per supported locale — the
 * template-lint layer for `organization_weekly_digest.html.twig`: a missing
 * translation key, a bad variable name, or invalid Twig fails here instead
 * of in production the following Monday.
 *
 * @category Functional Test
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationWeeklyDigestEmailTemplateTest extends KernelTestCase
{
  // #region Methods
  /**
   * Method localeExpectations.
   *
   * @static
   *
   * @return array<string, array{string, string}> locale => [locale, expected heading fragment]
   */
  public static function localeExpectations(): array
  {
    return [
      'english' => ['en', 'Weekly summary'],
      'french' => ['fr', 'Récapitulatif hebdomadaire'],
      'spanish' => ['es', 'Resumen semanal'],
    ];
  }

  #[Test]
  #[DataProvider('localeExpectations')]
  public function testTemplateRendersEverySectionInEachSupportedLocale(string $locale, string $expectedHeading): void
  {
    $html = $this->render($locale);

    self::assertStringContainsString($expectedHeading, $html);
    self::assertStringContainsString('ACME &amp; Co', $html);
    self::assertStringContainsString('#12', $html);
    self::assertStringContainsString('Replace extinguisher', $html);
    self::assertStringContainsString('extinguisher', $html);
    self::assertStringContainsString('Blocked emergency exit', $html);
    self::assertStringContainsString('https://app.fireguard.test/organizations/org-1', $html);
    self::assertStringContainsString('lang="' . $locale . '"', $html);
    self::assertStringNotContainsString('digest.', $html, 'A raw translation key in the output means a missing catalog entry.');
  }

  #[Test]
  public function testTemplateHoldsEachSectionItemsInACardAndKeepsTheTextOutsideIt(): void
  {
    $html = html_entity_decode($this->render('fr'), ENT_QUOTES);

    self::assertSame(3, substr_count($html, 'class="item-card"'), 'One card per non-empty section.');
    self::assertStringContainsString('Replace extinguisher', $this->firstCard($html));
    self::assertStringNotContainsString('Voici ce qui demande votre attention', $this->firstCard($html), 'The intro stays outside the cards.');
    self::assertStringNotContainsString('autres', $this->firstCard($html), '"and N more" stays outside the cards.');
    self::assertStringContainsString('chaque lundi en tant qu\'administrateur de ACME & Co', $html);
  }

  #[Test]
  public function testTemplateAnnouncesTheRemainderWhenCountsExceedTheDetailSample(): void
  {
    $html = $this->render('en');

    self::assertStringContainsString('and 6 more', $html);
  }

  /**
   * Method brandingExpectations.
   *
   * @static
   *
   * @return array<string, array{string, string, string}> locale => [locale, severity label, footer fragment]
   */
  public static function brandingExpectations(): array
  {
    return [
      'english' => ['en', 'Critical', 'fire-safety field operations platform'],
      'french' => ['fr', 'Critique', "plateforme d'opérations terrain"],
      'spanish' => ['es', 'Crítica', 'plataforma de operaciones de campo'],
    ];
  }

  #[Test]
  #[DataProvider('brandingExpectations')]
  public function testTemplateLocalizesSeverityAndFooterAndReferencesTheBrandMark(string $locale, string $severity, string $footer): void
  {
    $html = html_entity_decode($this->render($locale), ENT_QUOTES);

    self::assertStringContainsString($severity, $html);
    self::assertStringContainsString($footer, $html);
    self::assertStringContainsString('src="cid:fireguard-mark"', $html, 'The mailer embeds the mark under this Content-ID.');
    self::assertStringNotContainsString('FireGuard', $html, 'The brand is always written "Fireguard".');
  }

  /**
   * Method firstCard.
   *
   * Extracts the markup of the first item card.
   *
   * @since 1.0.0
   *
   * @param string $html the rendered email
   *
   * @return string the first `item-card` table
   */
  private function firstCard(string $html): string
  {
    $start = (int) strpos($html, 'class="item-card"');

    return substr($html, $start, (int) strpos($html, '</table>', $start) - $start);
  }

  /**
   * Method render.
   *
   * Renders the digest template with a full-context fixture: every section
   * populated, and more counted items than detail lines so the "and N more"
   * paths execute.
   *
   * @since 1.0.0
   *
   * @param string $locale the email locale under test
   *
   * @return string the rendered HTML
   */
  private function render(string $locale): string
  {
    self::bootKernel();

    /** @var Environment $twig */
    $twig = self::getContainer()->get('twig');

    return $twig->render('notification/email/organization_weekly_digest.html.twig', [
      'locale' => $locale,
      'organizationName' => 'ACME & Co',
      'dashboardUrl' => 'https://app.fireguard.test/organizations/org-1',
      'overdueInterventionsCount' => 7,
      'overdueInterventions' => [
        ['number' => 12, 'name' => 'Replace extinguisher', 'dueAt' => '20/08/2026'],
      ],
      'maintenanceDueSoonCount' => 2,
      'maintenanceOverdueCount' => 1,
      'maintenanceDeadlines' => [
        ['equipmentType' => 'extinguisher', 'nextDueAt' => '25/08/2026', 'overdue' => true],
        ['equipmentType' => 'alarm', 'nextDueAt' => '30/08/2026', 'overdue' => false],
      ],
      'openNonConformitiesCount' => 3,
      'slaBreachedNonConformitiesCount' => 1,
      'openNonConformities' => [
        ['description' => 'Blocked emergency exit', 'severity' => 'critical', 'openedAt' => '01/08/2026'],
      ],
    ]);
  }
  // #endregion
}
