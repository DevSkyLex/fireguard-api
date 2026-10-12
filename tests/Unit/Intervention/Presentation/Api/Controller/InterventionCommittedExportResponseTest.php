<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\Contract\Export\{InterventionExportDocument, InterventionExportFailureException};
use Intervention\Presentation\Api\Controller\{ExportInterventionReportController, ExportInterventionsController};
use Intervention\Presentation\Api\Service\InterventionExportCriteriaFactory;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class InterventionCommittedExportResponseTest
 *
 * Keeps invalid durable export bytes from being exposed as a successful download.
 *
 * @category Unit Test
 */
#[CoversClass(ExportInterventionReportController::class)]
#[CoversClass(ExportInterventionsController::class)]
final class InterventionCommittedExportResponseTest extends TestCase
{
  // #region Methods
  /**
   * Method testInvalidCommittedDocumentPreservesTheExplicitExportFailure
   *
   * Checks the same owner failure for committed CSV and report responses.
   *
   * @access public
   *
   * @param bool $report selects the single-resource report controller
   *
   * @return void
   */
  #[DataProvider('controllerKinds')]
  public function testInvalidCommittedDocumentPreservesTheExplicitExportFailure(bool $report): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser('user', 'user@example.test', 'hashed', ['ROLE_USER'], [], true));
    $commands = $this->createStub(CommandBusPort::class);
    $commands->method('dispatch')->willReturn(new InterventionExportDocument('%%%', 'export.csv', 'text/csv', 'org'));
    $request = new Request(['organization' => '/api/organizations/org']);
    $request->attributes->set('id', 'intervention');
    $controller = $report
      ? new ExportInterventionReportController($commands, $security)
      : new ExportInterventionsController($commands, $security, new InterventionExportCriteriaFactory());
    $this->expectException(InterventionExportFailureException::class);
    $this->expectExceptionMessage('The committed export document is invalid.');

    $controller($request);
  }

  /**
   * Method controllerKinds
   *
   * Exercises both committed document response surfaces.
   *
   * @access public
   *
   * @return iterable<string, array{bool}> report and collection variants
   */
  public static function controllerKinds(): iterable
  {
    yield 'report' => [true];
    yield 'CSV collection' => [false];
  }
  // #endregion
}
