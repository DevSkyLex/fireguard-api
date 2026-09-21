<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Application\UseCase\Query\GetImportTemplate;

use Import\Application\UseCase\Query\GetImportTemplate\{GetImportTemplateHandler, GetImportTemplateQuery};
use Import\Domain\Exception\ImportJobNotFoundException;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\TestCase;

final class GetImportTemplateHandlerTest extends TestCase
{
  public function testFacilityTemplateRequiresWriteAndReturnsOnlyTheHeader(): void
  {
    $auth = $this->createMock(OrganizationAuthorizationPort::class);
    $auth->expects(self::once())->method('resolveAccess')->with('actor', 'org', 'organization.facilities.write')->willReturn(OrganizationAccessDecision::GRANTED);
    $result = new GetImportTemplateHandler($auth)(new GetImportTemplateQuery('actor', 'org', 'facility'));
    self::assertSame("type,name,code,address,latitude,longitude,parentCode\r\n", $result->content);
  }

  public function testAnotherOrganizationIsHidden(): void
  {
    $auth = $this->createStub(OrganizationAuthorizationPort::class);
    $auth->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);
    $this->expectException(ImportJobNotFoundException::class);
    new GetImportTemplateHandler($auth)(new GetImportTemplateQuery('actor', 'org', 'member'));
  }
}
