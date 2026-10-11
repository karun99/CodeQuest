#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');
const readline = require('readline');

const ROOT = path.resolve(__dirname, '..');
const PROTOCOL_VERSION = '2024-11-05';
const SERVER_NAME = 'codequest';

let pkg = { name: 'codequest', version: '0.1.0', description: '' };
try {
  pkg = Object.assign(pkg, JSON.parse(fs.readFileSync(path.join(ROOT, 'package.json'), 'utf8')));
} catch (e) {}

const LEVELS = ['aptitude', 'programming', 'debugging'];
const QTYPES = ['mcq', 'fill', 'code_choice'];

function readText(rel) {
  try {
    return fs.readFileSync(path.join(ROOT, rel), 'utf8');
  } catch (e) {
    return null;
  }
}

function parseSchema() {
  const sql = readText('schema.sql') || '';
  const tables = [];
  const re = /CREATE TABLE(?: IF NOT EXISTS)? +`?([A-Za-z0-9_]+)`? *\(([\s\S]*?)\) *ENGINE/gi;
  let m;
  while ((m = re.exec(sql)) !== null) {
    const name = m[1];
    const body = m[2];
    const columns = [];
    for (const raw of body.split('\n')) {
      const line = raw.trim();
      if (!line) continue;
      if (/^(PRIMARY KEY|UNIQUE KEY|KEY|FOREIGN KEY|CONSTRAINT|INDEX)\b/i.test(line)) continue;
      const cm = /^`?([A-Za-z_][A-Za-z0-9_]*)`?\s+([A-Za-z][A-Za-z0-9_]*(?:\([^)]*\))?)/.exec(line);
      if (cm) columns.push({ name: cm[1], type: cm[2] });
    }
    tables.push({ name, columns });
  }
  return tables;
}

function listDocs() {
  const dir = path.join(ROOT, 'docs');
  try {
    return fs.readdirSync(dir)
      .filter((f) => /\.(md|markdown|txt)$/i.test(f))
      .sort();
  } catch (e) {
    return [];
  }
}

const TOOLS = [
  {
    name: 'codequest_info',
    description: 'Project metadata for CodeQuest (name, version, description, tech stack).',
    inputSchema: { type: 'object', properties: {}, additionalProperties: false },
  },
  {
    name: 'codequest_schema',
    description: 'Tables and columns defined in schema.sql. Optionally filter to one table.',
    inputSchema: {
      type: 'object',
      properties: { table: { type: 'string', description: 'Return only this table.' } },
      additionalProperties: false,
    },
  },
  {
    name: 'codequest_exam_levels',
    description: 'Assessment levels and question types CodeQuest supports.',
    inputSchema: { type: 'object', properties: {}, additionalProperties: false },
  },
  {
    name: 'codequest_list_docs',
    description: 'List documentation files bundled in the docs/ directory.',
    inputSchema: { type: 'object', properties: {}, additionalProperties: false },
  },
  {
    name: 'codequest_get_doc',
    description: 'Return the contents of a documentation file from docs/.',
    inputSchema: {
      type: 'object',
      properties: { name: { type: 'string', description: 'File name, e.g. installation.md' } },
      required: ['name'],
      additionalProperties: false,
    },
  },
  {
    name: 'codequest_health',
    description: 'Server health and repository availability check.',
    inputSchema: { type: 'object', properties: {}, additionalProperties: false },
  },
];

function text(s) {
  return { content: [{ type: 'text', text: s }] };
}

function toolResult(name, args) {
  args = args || {};
  switch (name) {
    case 'codequest_info':
      return text(JSON.stringify({
        name: pkg.name,
        version: pkg.version,
        description: pkg.description,
        repository: 'https://github.com/karun99/CodeQuest',
        stack: ['PHP 8.2', 'MySQL', 'Docker', 'Node.js tooling'],
        levels: LEVELS,
        question_types: QTYPES,
      }, null, 2));

    case 'codequest_schema': {
      let tables = parseSchema();
      if (args.table) {
        tables = tables.filter((t) => t.name.toLowerCase() === String(args.table).toLowerCase());
      }
      if (tables.length === 0) return text('No matching tables found in schema.sql.');
      return text(tables.map((t) => {
        const cols = t.columns.map((c) => '  - ' + c.name + ' ' + c.type).join('\n');
        return t.name + ':\n' + cols;
      }).join('\n\n'));
    }

    case 'codequest_exam_levels':
      return text(JSON.stringify({
        levels: LEVELS,
        question_types: QTYPES,
        difficulties: ['easy', 'medium', 'hard'],
        pass_default_percent: 40,
      }, null, 2));

    case 'codequest_list_docs': {
      const docs = listDocs();
      return text(docs.length ? docs.join('\n') : 'No documentation files found.');
    }

    case 'codequest_get_doc': {
      const name = String(args.name || '');
      const safe = path.basename(name);
      if (!/\.(md|markdown|txt)$/i.test(safe)) return text('Only .md, .markdown or .txt files are readable.');
      const body = readText(path.join('docs', safe));
      return text(body === null ? 'Doc not found: ' + safe : body);
    }

    case 'codequest_health': {
      const hasSchema = readText('schema.sql') !== null;
      const tables = parseSchema();
      return text(JSON.stringify({
        status: 'ok',
        server: SERVER_NAME,
        version: pkg.version,
        schema_present: hasSchema,
        tables: tables.length,
        docs: listDocs().length,
      }, null, 2));
    }

    default:
      return null;
  }
}

function send(obj) {
  process.stdout.write(JSON.stringify(obj) + '\n');
}

function reply(id, result) {
  send({ jsonrpc: '2.0', id, result });
}

function replyError(id, code, message) {
  send({ jsonrpc: '2.0', id, error: { code, message } });
}

function handle(msg) {
  const { id, method, params } = msg;
  const isNotification = id === undefined || id === null;

  if (method === 'initialize') {
    return reply(id, {
      protocolVersion: PROTOCOL_VERSION,
      capabilities: { tools: {} },
      serverInfo: { name: SERVER_NAME, version: pkg.version },
    });
  }
  if (method === 'notifications/initialized' || method === 'initialized') return;
  if (method === 'ping') return reply(id, {});
  if (method === 'tools/list') return reply(id, { tools: TOOLS });
  if (method === 'tools/call') {
    const name = params && params.name;
    const out = toolResult(name, params && params.arguments);
    if (out === null) return replyError(id, -32602, 'Unknown tool: ' + name);
    return reply(id, out);
  }
  if (isNotification) return;
  replyError(id, -32601, 'Method not found: ' + method);
}

const rl = readline.createInterface({ input: process.stdin });
rl.on('line', (line) => {
  const t = line.trim();
  if (!t) return;
  let msg;
  try {
    msg = JSON.parse(t);
  } catch (e) {
    replyError(null, -32700, 'Parse error');
    return;
  }
  try {
    handle(msg);
  } catch (e) {
    replyError(msg && msg.id, -32603, 'Internal error: ' + e.message);
  }
});
rl.on('close', () => process.exit(0));
