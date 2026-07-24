<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Query\Template\ListInterventionTemplates;

use Intervention\Application\Contract\Template\InterventionTemplatePage;
use Intervention\Application\Port\Outbound\InterventionTemplatePort;
use Intervention\Application\UseCase\Query\Template\ListInterventionTemplates\{
  ListInterventionTemplatesHandler,
  ListInterventionTemplatesQuery
};
use Intervention\Domain\Exception\InterventionAccessDeniedException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test ListInterventionTemplatesHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ListInterventionTemplatesHandlerTest extends TestCase
{
  private const string USER_ID = '11111111-1111-4111-8111-111111111111';

  private const string ORGANIZATION_ID = '22222222-2222-4222-8222-222222222222';

  #[Test]
  public function itReturnsThePageOfTemplatesForAnAuthorizedUser(): void
  {
    $page = new InterventionTemplatePage([], 2, 15, 0);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $templates = $this->createMock(InterventionTemplatePort::class);
    $templates->expects(self::once())
      ->method('list')
      ->with(self::ORGANIZATION_ID, 2, 15, 'boiler')
      ->willReturn($page);

    $handler = new ListInterventionTemplatesHandler($templates, $authorization);

    $result = $handler(new ListInterventionTemplatesQuery(
      self::USER_ID,
      self::ORGANIZATION_ID,
      2,
      15,
      'boiler',
    ));

    self::assertSame($page, $result->page);
  }

  #[Test]
  public function itPassesANullSearchWhenNoneIsProvided(): void
  {
    $page = new InterventionTemplatePage([], 1, 30, 0);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $templates = $this->createMock(InterventionTemplatePort::class);
    $templates->expects(self::once())
      ->method('list')
      ->with(self::ORGANIZATION_ID, 1, 30, null)
      ->willReturn($page);

    $handler = new ListInterventionTemplatesHandler($templates, $authorization);

    $result = $handler(new ListInterventionTemplatesQuery(
      self::USER_ID,
      self::ORGANIZATION_ID,
      1,
      30,
    ));

    self::assertSame($page, $result->page);
  }

  #[Test]
  public function itDeniesAccessWhenThePermissionIsMissing(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $templates = $this->createMock(InterventionTemplatePort::class);
    $templates->expects(self::never())->method('list');

    $handler = new ListInterventionTemplatesHandler($templates, $authorization);

    $this->expectException(InterventionAccessDeniedException::class);
    $this->expectExceptionMessage('Missing organization.interventions.read permission.');

    $handler(new ListInterventionTemplatesQuery(
      self::USER_ID,
      self::ORGANIZATION_ID,
      1,
      30,
    ));
  }
}
