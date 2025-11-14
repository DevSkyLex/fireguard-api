---
trigger: model_decision
description: Activated when creating API Platform DTOs, Resources, or Providers.
---

# Presentation Rules (API Platform)
**Activation Mode:** Model Decision

## Purpose
Expose resources, not entities. Map Input DTOs to Commands; map Query Results to Output DTOs.

## Structure
- `Api/Dto/*Input.php`, `*Output.php`
- `Api/Resource/*Resource.php` (metadata only)
- `Api/State/*Processor.php`, `*Provider.php`
- Optional: `Api/Security/Authorization/*` voters/checkers

## Rules
- Never return Domain or Doctrine objects; only Output DTOs.
- Put Symfony `Assert\*` constraints on *Input* only.
- Business authorization at Application level; Presentation calls it.

## Example (Processor)
```php
final class RegisterUserProcessor implements ProcessorInterface {
    public function __construct(private MessageBusInterface $bus){}
    public function process($data, Operation $op, array $uriVars = [], array $ctx = []) {
        $cmd = new RegisterUserCommand($data->email, $data->givenName, $data->familyName);
        return $this->bus->dispatch($cmd);
    }
}
```
