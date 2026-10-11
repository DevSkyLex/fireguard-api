<?php

declare(strict_types=1);

namespace Tests\Support;

use Equipment\Application\Contract\Equipment\CanonicalEquipmentView;
use Equipment\Application\UseCase\Query\Equipment\GetCanonicalEquipment\{GetCanonicalEquipmentQuery, GetCanonicalEquipmentResult};
use Equipment\Application\UseCase\Query\Equipment\GetEquipment\GetEquipmentResult;
use Equipment\Application\UseCase\Query\Equipment\GetEquipmentDetail\{GetEquipmentDetailQuery, GetEquipmentDetailResult};
use Equipment\Presentation\Api\Factory\EquipmentOutputFactory;
use Equipment\Presentation\Api\Service\EquipmentDetailReader;
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
  public static function equipment(?object $result): EquipmentDetailReader
  {
    return new EquipmentDetailReader(self::queries($result), new EquipmentOutputFactory());
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
        $reflection = new ReflectionClass(GetEquipmentResult::class);
        $arguments = [];
        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
          $arguments[] = array_key_exists($parameter->name, $values) ? $values[$parameter->name] : $parameter->getDefaultValue();
        }

        $projection = $reflection->newInstanceArgs($arguments);
        if ($query instanceof GetEquipmentDetailQuery) {
          assert(is_string($values['equipmentId']));

          return new GetEquipmentDetailResult($projection, new CanonicalEquipmentView($values['equipmentId'], $values['organizationId'], 'published', null, 7));
        }

        return $projection;
      }
    };
  }
}
