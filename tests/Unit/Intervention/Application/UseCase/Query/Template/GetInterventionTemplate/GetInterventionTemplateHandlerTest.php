<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Query\Template\GetInterventionTemplate;

use DateTimeImmutable;
use Intervention\Application\Contract\Template\InterventionTemplateView;
use Intervention\Application\Port\Outbound\InterventionTemplatePort;
use Intervention\Application\UseCase\Query\Template\GetInterventionTemplate\{GetInterventionTemplateHandler, GetInterventionTemplateQuery, GetInterventionTemplateResult};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetInterventionTemplateHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetInterventionTemplateHandler::class)]
final class GetInterventionTemplateHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c11';

  private const string TEMPLATE_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c12';

  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c14';

  #[Test]
  public function itThrowsWhenTheTemplateCannotBeFound(): void
  {
    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn(null);
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);

    $this->expectException(InterventionNotFoundException::class);

    new GetInterventionTemplateHandler($templates, $authorization)(self::query());
  }

  #[Test]
  public function itRejectsAUserMissingTheReadPermission(): void
  {
    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn(self::view());
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $this->expectException(InterventionAccessDeniedException::class);

    new GetInterventionTemplateHandler($templates, $authorization)(self::query());
  }

  #[Test]
  public function itReturnsTheTemplateForAnAuthorizedUser(): void
  {
    $view = self::view();

    $templates = $this->createStub(InterventionTemplatePort::class);
    $templates->method('find')->willReturn($view);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $result = new GetInterventionTemplateHandler($templates, $authorization)(self::query());

    self::assertInstanceOf(GetInterventionTemplateResult::class, $result);
    self::assertSame($view, $result->template);
  }

  private static function query(): GetInterventionTemplateQuery
  {
    return new GetInterventionTemplateQuery(
      userId: self::USER_ID,
      templateId: self::TEMPLATE_ID,
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
