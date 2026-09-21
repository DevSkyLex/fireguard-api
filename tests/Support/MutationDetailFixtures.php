<?php

declare(strict_types=1);

namespace Tests\Support;

use Equipment\Application\Contract\Equipment\CanonicalEquipmentView;
use Equipment\Application\UseCase\Query\Equipment\GetCanonicalEquipment\{GetCanonicalEquipmentQuery, GetCanonicalEquipmentResult};
use Equipment\Application\UseCase\Query\Equipment\GetEquipment\GetEquipmentResult;
use Equipment\Presentation\Api\Factory\{EquipmentDetailOutputFactory, EquipmentOutputFactory};
use Facility\Application\Contract\Facility\CanonicalFacilityView;
use Facility\Application\UseCase\Query\Facility\GetCanonicalFacility\{GetCanonicalFacilityQuery, GetCanonicalFacilityResult};
use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Facility\Presentation\Api\Factory\FacilityDetailOutputFactory;
use LogicException;
use ReflectionClass;
use Shared\Application\Message\{QueryMessage, ResultMessage};
use Shared\Application\Port\Inbound\QueryBusPort;

use function array_key_exists;
use function assert;
use function get_object_vars;
use function is_string;

/** Reuses processor fixtures for the persisted detail query; canonical revision is deliberately seven. */
final class MutationDetailFixtures
{
  public static function equipment(?object $result): EquipmentDetailOutputFactory
  {
    return new EquipmentDetailOutputFactory(self::queries($result), new EquipmentOutputFactory());
  }

  public static function facility(?object $result): FacilityDetailOutputFactory
  {
    return new FacilityDetailOutputFactory(self::queries($result));
  }

  private static function queries(?object $result): QueryBusPort
  {
    return new class ($result) implements QueryBusPort {
      public function __construct(private readonly ?object $result)
      {
      }

      public function ask(QueryMessage $query): ResultMessage
      {
        if (null === $this->result) {
          throw new LogicException('Unexpected detail read on a refused mutation.');
        }
        $values = get_object_vars($this->result);
        assert(is_string($values['organizationId']));
        if ($query instanceof GetCanonicalEquipmentQuery) {
          assert(is_string($values['equipmentId']));

          return new GetCanonicalEquipmentResult(new CanonicalEquipmentView($values['equipmentId'], $values['organizationId'], 'published', null, 7));
        }
        if ($query instanceof GetCanonicalFacilityQuery) {
          assert(is_string($values['facilityId']));

          return new GetCanonicalFacilityResult(new CanonicalFacilityView($values['facilityId'], $values['organizationId'], 'published', null, 7));
        }
        $reflection = new ReflectionClass(isset($values['equipmentId']) ? GetEquipmentResult::class : GetFacilityResult::class);
        $arguments = [];
        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
          $arguments[] = array_key_exists($parameter->name, $values) ? $values[$parameter->name] : $parameter->getDefaultValue();
        }

        return $reflection->newInstanceArgs($arguments);
      }
    };
  }
}
