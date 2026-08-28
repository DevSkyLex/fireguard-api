<?php

declare(strict_types=1);

namespace Tests\Functional\Notification\Infrastructure\Template;

use PHPUnit\Framework\Attributes\{DataProvider, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

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
 *
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
  public function testTemplateAnnouncesTheRemainderWhenCountsExceedTheDetailSample(): void
  {
    $html = $this->render('en');

    self::assertStringContainsString('and 6 more', $html);
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
