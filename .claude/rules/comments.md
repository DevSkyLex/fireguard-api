---
paths:
  - 'src/**/*.php'
---

# Comments and docblocks

Read [the shared comment convention](../../docs/guides/code-comments.md) before
editing authored source comments. It owns content, required tags, formatter
behavior, scoped commands and progressive migration. Preserve executable code
and verified metadata; do not copy legacy shorthand or invent historical tags.

Document properties, methods and functions at their declarations. Use balanced
`Properties`, `Constructor` and `Methods` regions for the class groups that exist.
When a docblock uses `@description`, put the tag alone on its line and prose on
the following line.
Separate documented members with one blank line, except the first member in a
class or region. Remove duplicate/empty regions and duplicate docblocks; inspect
the final declarations and prose after formatting.
