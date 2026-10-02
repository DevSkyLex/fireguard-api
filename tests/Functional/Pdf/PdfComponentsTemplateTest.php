<?php

declare(strict_types=1);

namespace Tests\Functional\Pdf;

use PHPUnit\Framework\Attributes\{DataProvider, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;

use function array_keys;
use function dirname;
use function is_array;
use function is_string;
use function sprintf;
use function str_contains;
use function trim;

/**
 * Test PdfComponentsTemplateTest.
 *
 * Exercises `pdf/components.html.twig` through the real Twig environment and translation
 * catalogs: the status macro resolves enum codes to translated labels, tints only the
 * end/alert states the web app tints, and degrades to a humanised label instead of
 * leaking a raw snake_case code into a document.
 *
 * @category Functional Test
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PdfComponentsTemplateTest extends KernelTestCase
{
  // #region Methods
  /**
   * Method statusExpectations.
   *
   * @static
   *
   * @return array<string, array{string, string, string, string, bool}> family, code, locale, label, tinted
   */
  public static function statusExpectations(): array
  {
    return [
      'neutral workflow step stays plain text' => ['intervention', 'in_progress', 'fr', 'En cours', false],
      'published end state is tinted' => ['intervention', 'published', 'fr', 'Publiée', true],
      'abandoned end state is tinted' => ['intervention', 'abandoned', 'en', 'Abandoned', true],
      'urgent priority is tinted' => ['priority', 'urgent', 'es', 'Urgente', true],
      'overdue maintenance is tinted' => ['maintenanceDue', 'overdue', 'fr', 'En retard', true],
      'unscheduled maintenance is not an alert' => ['maintenanceDue', 'unscheduled', 'en', 'Unscheduled', false],
      'failed inspection result is tinted' => ['inspectionResult', 'fail', 'en', 'Fail', true],
    ];
  }

  #[Test]
  #[DataProvider('statusExpectations')]
  public function testStatusMacroTranslatesAndTintsOnlyEndAndAlertStates(
    string $family,
    string $code,
    string $locale,
    string $label,
    bool $tinted,
  ): void {
    $html = $this->render(sprintf("{{ ui.status('%s', '%s', '%s') }}", $family, $code, $locale));

    self::assertStringContainsString($label, $html);
    self::assertSame($tinted, str_contains($html, 'class="pill pill-'));
    self::assertStringNotContainsString('status.', $html, 'A raw translation key in the output means a missing catalog entry.');
  }

  #[Test]
  public function testStatusMacroHumanisesAnUnknownCode(): void
  {
    $html = $this->render("{{ ui.status('intervention', 'awaiting_parts', 'fr') }}");

    self::assertSame('Awaiting parts', trim($html));
  }

  #[Test]
  public function testStatusMacroRendersADashForAnEmptyValue(): void
  {
    self::assertSame('—', trim($this->render("{{ ui.status('intervention', null, 'fr') }}")));
  }

  #[Test]
  public function testTagMacroKeepsTheRawCodeAsAStableHook(): void
  {
    $tinted = $this->render("{{ ui.tag('Non conforme', 'destructive', false, 'non_compliant') }}");
    $neutral = $this->render("{{ ui.tag('Non applicable', 'muted', false, 'not_applicable') }}");

    self::assertStringContainsString('pill pill-destructive status-non_compliant', $tinted);
    self::assertStringContainsString('<span class="status-not_applicable">Non applicable</span>', $neutral);
  }

  #[Test]
  public function testEveryStatusCodeIsTranslatedInEachLocale(): void
  {
    $catalogs = [];
    foreach (['en', 'fr', 'es'] as $locale) {
      $parsed = Yaml::parseFile(dirname(__DIR__, 3) . sprintf('/translations/pdf.%s.yaml', $locale));
      self::assertIsArray($parsed);
      self::assertIsArray($parsed['status'] ?? null, sprintf('pdf.%s.yaml has no "status" block.', $locale));
      $catalogs[$locale] = $parsed['status'];
    }

    foreach ($catalogs['en'] as $family => $codes) {
      self::assertIsArray($codes);
      foreach (['fr', 'es'] as $locale) {
        $translated = $catalogs[$locale][$family] ?? null;
        self::assertTrue(is_array($translated), sprintf('Family "%s" is missing in %s.', $family, $locale));
        self::assertSame(array_keys($codes), array_keys($translated), sprintf('Codes of "%s" differ in %s.', $family, $locale));
        foreach ($translated as $code => $label) {
          self::assertTrue(is_string($label) && '' !== $label, sprintf('%s.%s is empty in %s.', $family, $code, $locale));
        }
      }
    }
  }

  /**
   * Method render.
   *
   * Renders an inline snippet with the components imported as `ui`.
   *
   * @since 1.0.0
   *
   * @param string $snippet the Twig snippet calling `ui.*` macros
   *
   * @return string the rendered markup
   */
  private function render(string $snippet): string
  {
    self::bootKernel();

    /** @var Environment $twig */
    $twig = self::getContainer()->get('twig');

    return $twig->createTemplate("{% import 'pdf/components.html.twig' as ui %}" . $snippet)->render();
  }
  // #endregion
}
