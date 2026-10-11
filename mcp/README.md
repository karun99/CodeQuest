# CodeQuest MCP Server

A dependency-free [Model Context Protocol](https://modelcontextprotocol.io) server that
exposes read-only tools over the CodeQuest repository. It speaks JSON-RPC 2.0 over stdio
and requires only Node.js 18+ (no database or package install).

## Run

```bash
node mcp/server.js
```

or

```bash
npm run mcp
```

## Tools

| Tool | Description |
|------|-------------|
| `codequest_info` | Project metadata (name, version, stack, levels). |
| `codequest_schema` | Tables and columns parsed from `schema.sql` (optional `table` filter). |
| `codequest_exam_levels` | Assessment levels, question types and difficulties. |
| `codequest_list_docs` | List bundled documentation files. |
| `codequest_get_doc` | Return a documentation file from `docs/`. |
| `codequest_health` | Server and repository health check. |

## Client configuration

```json
{
  "mcpServers": {
    "codequest": {
      "command": "node",
      "args": ["mcp/server.js"]
    }
  }
}
```

This server is listed on [glama.ai](https://glama.ai) via [`glama.json`](../glama.json).
