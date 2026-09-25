<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Exception;

use RuntimeException;

/** A persisted equipment record has no organization. */
final class EquipmentOrganizationMissingException extends RuntimeException
{
}
