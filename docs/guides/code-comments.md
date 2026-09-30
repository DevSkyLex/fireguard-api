# Code comments and PHPDoc

This is the shared convention for human authors, Codex and Claude. Architecture,
security and module contracts remain authoritative. Apply the same principles as
the frontend: explain purpose and caller constraints, preserve verified metadata,
and keep the declaration titles and tag groups below. The formatter enforces this
contract; its default ordering must not replace the project convention.

## Content

Write documentation in English. Start with a declaration title such as
`Class NotificationId`, `Interface NotificationPort`, `Method fromString` or
`Function formatShortcut`. Keep one blank line between the title and the prose.
Use one or two sentences about ownership, intent, an invariant or a non-obvious
caller constraint; keep the title separate instead of merging or deleting it.
Do not narrate statements.
Document properties, methods and functions at their actual declarations, before
attributes when present. Keep existing `// #region` markers and add `Properties`,
`Constructor` and `Methods` regions around the corresponding class member groups.
Include the member's docblock inside its region. Omit empty groups, keep regions
balanced and preserve declaration order; close and reopen a group when interleaved
members require it. Promoted properties stay in the constructor group and are
documented through its parameter tags. When a docblock uses `@description`, put
that tag alone on its line and start the description on the following line.
Separate documented members with one blank line before the next docblock; the
first member after a class opening or a region marker needs no leading blank line.
Remove duplicate or empty regions. Use one docblock per declaration and inspect
the final source for misplaced blocks, redundant groups and inaccurate prose.
A short inline comment is appropriate for a deliberate
ordering constraint or workaround; move longer rationale to the declaration's
PHPDoc or `MODULE.md`.

Preserve existing `@version`, `@since` and `@author` values. Do not invent a release,
credit the maintenance agent as an author, or overwrite another contributor.
Recover missing metadata from verified history or report that it is unknown.
Preserve static-analysis annotations, templates, shapes, suppressions and examples.
They can affect tooling even when PHP does not execute them.

## Declaration contract

See [complete examples by declaration](code-comment-examples.md) for classes,
properties, constructors, methods, constants, enums/cases, ports and generics.
Use their applicable tags without copying unverified metadata.

| Declaration | Required documentation |
| --- | --- |
| Class, interface, enum, trait | Declaration title and purpose; `@category` and existing `@version` together; separate `@author` group |
| Method or function | Declaration title and purpose; `@access` matching visibility and existing `@since` together; `@param` per parameter; separate `@return` including `void` |
| Property or constant | Declaration title; purpose when not obvious; `@var` for shapes, generics or other information beyond its PHP type |
| Port or contract | Caller guarantees, failure conditions and side effects; `@throws` when relevant |

Parameter names and types must agree with the signature. Describe units, limits,
nullability semantics or ownership rather than restating the type. Do not change a
signature or inferred static-analysis type as part of a comment cleanup.

```php
/**
 * Class NotificationId
 *
 * Identifies a notification using the shared UUID representation.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NotificationId extends Uuid
{
  // #region Methods
  /**
   * Method fromString
   *
   * Creates a notification identifier from its UUID representation.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value the UUID representation
   *
   * @return self the notification identifier
   */
  public static function fromString(string $value): self
  {
    return new self($value);
  }
  // #endregion
}
```

## Formatting and verification

PHP-CS-Fixer uses explicit ordering and separation groups: `@category`/`@version`
together; `@access`/`@since` together before parameters; author metadata separately;
parameters together; return and throws in separate groups. Keep a blank line
between groups. Tags are left-aligned, indentation is two spaces and line endings
are LF. Both formatter profiles preserve declaration titles without adding a
period and retain `@access` and descriptive typed tags. It still includes code-changing
rules, so use the dedicated comment-only configuration for documentation work:

```powershell
make docs-lint DOC_PATHS=src/Notification/Domain/ValueObject/NotificationId.php
make docs-fix DOC_PATHS=src/Notification/Domain/ValueObject/NotificationId.php
```

Explicit files are mandatory; directories and global rewrites are outside a
bounded comment assignment. `make cs-lint` remains the complete style gate in CI.
After a comment-only fix, compare PHP tokens excluding whitespace and comments,
and inspect annotations for semantic changes. Run additional analysis only when
annotations affecting PHPStan or Doctrine have changed. Do not prepare databases
or run business suites for prose alone.

Migrate assigned files progressively. The comment maintainer can clarify prose,
correct tags against source and run these tools; architectural or behavior changes
belong to another task. Report executed checks and unrelated failures honestly.
