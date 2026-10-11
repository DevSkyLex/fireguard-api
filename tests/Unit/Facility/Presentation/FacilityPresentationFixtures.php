<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation;

use Facility\Application\UseCase\Query\Facility\GetFacility\{GetFacilityQuery, GetFacilityResult};
use LogicException;
use ReflectionClass;
use Shared\Application\Message\{QueryMessage, ResultMessage};
use Shared\Application\Port\Inbound\QueryBusPort;

use function array_key_exists;
use function get_object_vars;

/** Reuses mutation snapshots to assert that Presentation reads persisted detail through the typed bus. */
final class FacilityPresentationFixtures
{
  public static function detailQueries(?object $result): QueryBusPort
  {
    return new class ($result) implements QueryBusPort {
      public function __construct(private readonly ?object $result)
      {
      }

      public function ask(QueryMessage $query): ResultMessage
      {
        if (!$query instanceof GetFacilityQuery || null === $this->result) {
          throw new LogicException('Unexpected detail query on a refused mutation.');
        }
        $values = get_object_vars($this->result);
        $values['revision'] = 7;
        $reflection = new ReflectionClass(GetFacilityResult::class);
        $arguments = [];
        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
          $arguments[] = array_key_exists($parameter->name, $values) ? $values[$parameter->name] : $parameter->getDefaultValue();
        }

        return $reflection->newInstanceArgs($arguments);
      }
    };
  }
}
