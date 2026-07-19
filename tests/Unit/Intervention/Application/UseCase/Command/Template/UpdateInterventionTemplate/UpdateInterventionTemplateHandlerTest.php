<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Command\Template\UpdateInterventionTemplate;

use DateTimeImmutable;
use Intervention\Application\Contract\Template\InterventionTemplateView;
use Intervention\Application\Port\Outbound\InterventionTemplatePort;
use Intervention\Application\UseCase\Command\Template\UpdateInterventionTemplate\{UpdateInterventionTemplateCommand, UpdateInterventionTemplateHandler};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test UpdateInterventionTemplateHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UpdateInterventionTemplateHandler::class)]
final class UpdateInterventionTemplateHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c11';

  private const string TEMPLATE_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c12';

  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c14';

  #[Test]
  public function itThrowsWhenTheTemplateCannotBeFound(): void
  {
    $templates = $this->createMock(InterventionTemplatePort::class);
    $templates->method('find')->willReturn(null);
    $templates->expects(self::never())->method('update');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);

    $this->expectException(InterventionNotFoundException::class);

    new UpdateInterventionTemplateHandler($templates, $authorization)(self::command());
  }

  #[Test]
  public function itRejectsAUserMissingThePlanPermission(): void
  {
    $templates = $this->createMock(InterventionTemplatePort::class);
    $templates->method('find')->willReturn(self::view());
    $templates->expects(self::never())->method('update');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $this->expectException(InterventionAccessDeniedException::class);

    new UpdateInterventionTemplateHandler($templates, $authorization)(self::command());
  }

  #[Test]
  public function itOnlyPatchesFieldsPresentInTheMergePatchBody(): void
  {
    $updated = self::view();
    $templates = $this->createMock(InterventionTemplatePort::class);
    $templates->method('find')->willReturn(self::view());
    $templates->expects(self::once())
      ->method('update')
      ->with(
        self::TEMPLATE_ID,
        'Renamed template',
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        true,
        false,
        false,
        false,
        false,
        false,
        false,
        false,
        false,
      )
      ->willReturn($updated);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $result = new UpdateInterventionTemplateHandler($templates, $authorization)(
      new UpdateInterventionTemplateCommand(
        userId: self::USER_ID,
        templateId: self::TEMPLATE_ID,
        name: 'Renamed template',
        description: null,
        type: null,
        priority: null,
        defaultSiteId: null,
        defaultResponsibleId: null,
        duration: null,
        labelIds: null,
        items: null,
        hasName: true,
      ),
    );

    self::assertSame($updated, $result->template);
  }

  private static function command(): UpdateInterventionTemplateCommand
  {
    return new UpdateInterventionTemplateCommand(
      userId: self::USER_ID,
      templateId: self::TEMPLATE_ID,
      name: 'Renamed template',
      description: null,
      type: null,
      priority: null,
      defaultSiteId: null,
      defaultResponsibleId: null,
      duration: null,
      labelIds: null,
      items: null,
      hasName: true,
    );
  }

  private static function view(): InterventionTemplateView
  {
    return new InterventionTemplateView(
      self::TEMPLATE_ID,
      self::ORGANIZATION_ID,
      'Fire safety audit',
      null,
      'inspection_campaign',
      'high',
      null,
      null,
      null,
      [],
      [],
      new DateTimeImmutable(),
      new DateTimeImmutable(),
    );
  }
}
