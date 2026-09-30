"""Exercise active references rather than treating examples as current dependencies."""
from pathlib import Path
import tempfile
import unittest

from check_references import check_documents, instruction_documents


class InstructionReferenceTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.root = Path(self.directory.name)
        (self.root / '.codex').mkdir()
        self.document = self.root / '.codex/workflow.md'
        self.names = {'fg-api-security-auditor', 'fg-api-review'}

    def check(self, text):
        self.document.write_text(text, encoding='utf-8')
        return check_documents(self.root, [self.document], self.names)

    def test_old_agent_and_deleted_resolver_report_source_line(self):
        for reference in ['fg-security-auditor', '.codex/agent-profiles.toml', '.codex/scripts/resolve_agent.py']:
            with self.subTest(reference=reference), self.assertRaises(ValueError) as failure:
                self.check('# Current instructions\nRead `' + reference + '`.\n')
            self.assertIn(f'.codex/workflow.md:2: {reference}', str(failure.exception))

    def test_inline_and_bare_active_references_are_checked(self):
        target = self.root / '.codex/current.toml'
        target.write_text('name="current"', encoding='utf-8')
        self.assertEqual(self.check('Use fg-api-security-auditor; read `.codex/current.toml`.')['references'], 2)
        target.unlink()
        with self.assertRaisesRegex(ValueError, 'current.toml'):
            self.check('Read .codex/current.toml.')

    def test_relative_markdown_destinations_do_not_become_root_paths(self):
        self.assertEqual(self.check('[Guide](../.agents/skills/fg-api-tests/references/testing.md)')['references'], 0)
        self.assertEqual(self.check('[Evaluator](scripts/session_evidence.py)')['references'], 0)

    def test_placeholders_ignored_configuration_examples_and_history(self):
        text = ('Use `.codex/config.toml`, `.codex/agents/<role>.toml`, `fg-api-*`.\n'
                '<!-- tooling-references: example -->\n'
                '```md\nRead .codex/deleted.py and fg-old-builder.\n```\n'
                '<!-- /tooling-references -->\n'
                '<!-- tooling-references: historical -->\n'
                'Former `fg-api-removed-skill`; `.codex/old.toml`.\n'
                '<!-- /tooling-references -->\n'
                '<!-- tooling-references: example -->\nfg-example-builder\n<!-- /tooling-references -->\n'
                'Use `fg-api-review`.')
        self.assertEqual(self.check(text)['references'], 1)

    def test_fenced_active_commands_and_non_tooling_resources_are_checked(self):
        for text, reference, number in [
                ('```sh\npython .codex/scripts/resolve_agent.py\n```', '.codex/scripts/resolve_agent.py', 2),
                ('Read `docs/guides/missing.md`.', 'docs/guides/missing.md', 1),
                ('<!-- An ordinary comment\nfg-old-builder\n-->\nUse fg-api-review.', None, 4)]:
            with self.subTest(text=text):
                if reference is None:
                    self.assertEqual(self.check(text)['references'], 1)
                else:
                    with self.assertRaisesRegex(ValueError, f'workflow.md:{number}: {reference}'):
                        self.check(text)

    def test_bad_exclusion_cannot_silently_hide_rest_of_document(self):
        for text in ['<!-- tooling-references: historical -->\nMissing end.',
                     '<!-- /tooling-references -->',
                     '<!-- tooling-references: example -->\n<!-- tooling-references: historical -->']:
            with self.subTest(text=text), self.assertRaises(ValueError):
                self.check(text)

    def test_resource_cannot_escape_through_symlink(self):
        target = self.root.parent / (self.root.name + '-external.md')
        target.write_text('External', encoding='utf-8')
        self.addCleanup(target.unlink)
        with self.assertRaisesRegex(ValueError, 'external.md'):
            self.check('Read `.codex/../../' + target.name + '`.')
        link = self.root / '.codex/escape.md'
        try:
            link.symlink_to(target)
        except OSError:
            return  # Traversal is covered above; CI also exercises symlinks where supported.
        with self.assertRaisesRegex(ValueError, 'escape.md'):
            self.check('Read `.codex/escape.md`.')

    def test_inventory_covers_prompts_skills_and_entrypoints(self):
        for relative in ['AGENTS.md', 'CLAUDE.md', '.codex/agents/role.toml',
                         '.agents/skills/fg-api-review/SKILL.md', '.agents/skills/fg-api-review/references/details.md']:
            target = self.root / relative
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_text('Use fg-api-review.', encoding='utf-8')
        documents = instruction_documents(self.root)
        self.assertEqual(len(documents), 5)
        self.assertEqual(check_documents(self.root, documents, self.names)['references'], 5)
