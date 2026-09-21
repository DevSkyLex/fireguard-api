<?php

declare(strict_types=1);

namespace Organization\Domain\Event\Organization;

use function class_alias;

// Compatibility for serialized events written before the public contract was introduced.
// New publishers and subscribers use Application\Contract\Event.
class_alias(\Organization\Application\Contract\Event\OrganizationSettingsUpdatedEvent::class, __NAMESPACE__ . '\\OrganizationSettingsUpdatedEvent');
