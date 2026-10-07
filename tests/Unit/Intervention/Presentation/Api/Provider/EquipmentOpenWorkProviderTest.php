<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Provider;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\Contract\Resource\InterventionEquipmentWork;
use Intervention\Application\UseCase\Query\Equipment\ListEquipmentOpenWork\{ListEquipmentOpenWorkQuery, ListEquipmentOpenWorkResult};
use Intervention\Presentation\Api\Provider\EquipmentOpenWorkProvider;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;

/** Verifies the public lookup projection is derived from the authorized query result. */
final class EquipmentOpenWorkProviderTest extends TestCase
{
  public function testItMapsExistingWorkIntoNavigationFields(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser('user', 'user@example.com', 'hashed', ['ROLE_USER']));
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::once())->method('ask')->with(self::callback(static fn (ListEquipmentOpenWorkQuery $query): bool => 'user' === $query->userId && 'organization' === $query->organizationId && 'equipment' === $query->equipmentId))->willReturn(new ListEquipmentOpenWorkResult([new InterventionEquipmentWork('order', 42, 'Repair', 'in_progress', 'task', 'repair', 'planned')]));
    $result = new EquipmentOpenWorkProvider($queries, $security)->provide(new GetCollection(), ['organizationId' => 'organization', 'equipmentId' => 'equipment']);
    self::assertSame('order', $result[0]->interventionId);
    self::assertSame(42, $result[0]->number);
    self::assertSame('repair', $result[0]->action);
    self::assertSame('planned', $result[0]->workItemStatus);
  }
}
