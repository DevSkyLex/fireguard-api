import GithubSlugger from 'github-slugger';
import MarkdownIt from 'markdown-it';

const markdown = new MarkdownIt({ html: true, linkify: false });

function inlineText(tokens = []) {
  return tokens
    .map((token) => {
      if (token.type === 'image') return inlineText(token.children);
      if (['text', 'code_inline'].includes(token.type)) return token.content;
      if (['softbreak', 'hardbreak'].includes(token.type)) return ' ';
      return '';
    })
    .join('');
}

export function parseMarkdown(source) {
  const environment = {};
  const tokens = markdown.parse(source, environment);
  const slugger = new GithubSlugger();
  const anchors = new Set();
  const links = [];
  const diagrams = [];
  const exampleLines = new Set();
  function html(content, line) {
    for (const match of content.matchAll(/\b(?:id|name)\s*=\s*["']([^"']+)["']/gi))
      anchors.add(match[1]);
    for (const match of content.matchAll(/\b(?:href|src)\s*=\s*["']([^"']+)["']/gi)) {
      links.push({ target: markdown.utils.unescapeAll(match[1]), line });
    }
  }
  for (let index = 0; index < tokens.length; index += 1) {
    const token = tokens[index];
    const line = (token.map?.[0] ?? 0) + 1;
    if (token.type === 'heading_open')
      anchors.add(slugger.slug(inlineText(tokens[index + 1]?.children)));
    if (['fence', 'code_block'].includes(token.type)) {
      for (let position = token.map[0]; position < token.map[1]; position += 1)
        exampleLines.add(position);
      if (token.type === 'fence' && token.info.trim().split(/\s+/)[0] === 'mermaid') {
        diagrams.push({ source: token.content, line });
      }
    }
    if (token.type === 'html_block') html(token.content.replaceAll(/<!--[\s\S]*?-->/g, ''), line);
    if (token.type === 'inline') {
      let currentLine = line;
      for (const child of token.children ?? []) {
        if (child.type === 'link_open')
          links.push({ target: child.attrGet('href'), line: currentLine });
        if (child.type === 'image') links.push({ target: child.attrGet('src'), line: currentLine });
        if (child.type === 'html_inline') html(child.content, currentLine);
        if (['softbreak', 'hardbreak'].includes(child.type)) currentLine += 1;
      }
    }
  }
  // Validate declared references even when no paragraph currently consumes them.
  source
    .replaceAll(/<!--[\s\S]*?-->/g, (comment) => comment.replace(/[^\n]/g, ' '))
    .split('\n')
    .forEach((line, index) => {
      if (exampleLines.has(index)) return;
      const match = /^ {0,3}\[[^\]]+\]:\s*(?:<([^>]+)>|(\S+))/.exec(line);
      if (match) links.push({ target: match[1] ?? match[2], line: index + 1 });
    });
  return { anchors, links, diagrams };
}
