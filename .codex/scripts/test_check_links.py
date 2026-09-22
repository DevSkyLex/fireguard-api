from pathlib import Path
import tempfile
import unittest

from check_links import check_links, link_targets


class MarkdownLinkTests(unittest.TestCase):
    def test_examples_comments_and_remote_links_are_not_local_checks(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / '.codex').mkdir()
            (root / '.codex/README.md').write_text(
                '```md\n[example](missing.md)\n```\n'
                '`[example](missing.md)`\n<!-- [example](missing.md) -->\n'
                '<!--\n[example](missing.md)\n-->\n'
                '[web](https://example.com/missing.md)\n[anchor](#section)\n',
                encoding='utf-8')
            self.assertEqual(check_links(root)['local_links'], 0)

    def test_relative_encoded_reference_and_angle_bracket_links(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / '.codex').mkdir()
            (root / 'With spaces.md').write_text('# Target', encoding='utf-8')
            (root / '.codex/README.md').write_text(
                '[one](../With%20spaces.md#target)\n'
                '[two](<../With spaces.md>)\n'
                '[three]: ../With%20spaces.md "Target"\n', encoding='utf-8')
            self.assertEqual(check_links(root)['local_links'], 3)

    def test_broken_link_reports_source_and_line(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / 'AGENTS.md').write_text('# Entry\n[bad](missing.md)\n', encoding='utf-8')
            with self.assertRaisesRegex(ValueError, r'AGENTS.md:2: missing.md'):
                check_links(root)

    def test_firstparty_references_included_and_vendors_excluded(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            authored = root / '.agents/skills/fg-example/references'
            authored.mkdir(parents=True)
            (authored / 'guide.md').write_text('[missing](lost.md)', encoding='utf-8')
            vendor = root / '.agents/skills/vendor'
            vendor.mkdir()
            (vendor / 'SKILL.md').write_text('[vendor](not-local.md)', encoding='utf-8')
            with self.assertRaisesRegex(ValueError, 'guide.md:1: lost.md'):
                check_links(root)
            (authored / 'lost.md').write_text('Present', encoding='utf-8')
            self.assertEqual(check_links(root)['documents'], 2)

    def test_tilde_fences_and_link_titles(self):
        self.assertEqual(list(link_targets('~~~md\n[x](missing.md)\n~~~\n[x](ok.md "Title")')),
                         [(4, 'ok.md')])


if __name__ == '__main__':
    unittest.main()
