"""Command line interface shared by the console script and run.py."""

from __future__ import annotations

import argparse
from typing import Any

from .config import TRANSPORTS

LOG_LEVELS = ("DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL")


def build_parser() -> argparse.ArgumentParser:
    """Build the argument parser."""
    parser = argparse.ArgumentParser(
        prog="flexicontent-mcp",
        description="Expose the FLEXIcontent Joomla JSON:API over MCP.",
    )

    parser.add_argument(
        "--config",
        metavar="PATH",
        help="TOML config file (default: $XDG_CONFIG_HOME/flexicontent-mcp/config.toml)",
    )
    parser.add_argument(
        "--profile",
        metavar="NAME",
        help="Profile to use in the config file (default: default_profile or 'default')",
    )
    parser.add_argument(
        "--base-url",
        metavar="URL",
        help="Joomla FLEXIcontent API base URL (overrides env and config)",
    )

    token = parser.add_argument_group("token sources (highest priority first)")
    token.add_argument("--token-env", metavar="NAME", help="Name of the env var holding the token")
    token.add_argument("--token-file", metavar="PATH", help="File containing the token")
    token.add_argument(
        "--token-command",
        metavar="COMMAND",
        help="Command printing the token on stdout (argv, not a shell)",
    )
    token.add_argument("--token", metavar="VALUE", help=argparse.SUPPRESS)

    runtime = parser.add_argument_group("runtime")
    runtime.add_argument(
        "--read-only",
        action=argparse.BooleanOptionalAction,
        default=None,
        help="Disable all write tools (--no-read-only re-enables them)",
    )
    runtime.add_argument(
        "--verify-ssl",
        action=argparse.BooleanOptionalAction,
        default=None,
        help="Verify the Joomla TLS certificate (--no-verify-ssl for DDEV)",
    )
    runtime.add_argument("--timeout", type=float, metavar="SECONDS", help="HTTP timeout")
    runtime.add_argument("--max-limit", type=int, metavar="N", help="Maximum list page size")
    runtime.add_argument(
        "--transport",
        choices=TRANSPORTS,
        help="MCP transport: stdio (default), sse or streamable-http",
    )
    runtime.add_argument("--host", metavar="ADDRESS", help="HTTP bind address")
    runtime.add_argument("--port", type=int, metavar="PORT", help="HTTP bind port")
    runtime.add_argument(
        "--allowed-host",
        dest="allowed_hosts",
        action="append",
        metavar="PATTERN",
        help="Allowed HTTP Host header, e.g. mcp.example.com:* (repeatable)",
    )
    runtime.add_argument(
        "--allowed-origin",
        dest="allowed_origins",
        action="append",
        metavar="PATTERN",
        help="Allowed HTTP Origin, e.g. https://mcp.example.com (repeatable)",
    )
    runtime.add_argument(
        "--log-level",
        choices=LOG_LEVELS,
        default="INFO",
        help="Log level on stderr (default: INFO)",
    )

    return parser


def config_overrides(args: argparse.Namespace) -> dict[str, Any]:
    """Return only the options explicitly provided on the command line."""
    keys = (
        "config",
        "profile",
        "base_url",
        "token_env",
        "token_file",
        "token_command",
        "token",
        "read_only",
        "verify_ssl",
        "timeout",
        "max_limit",
        "transport",
        "host",
        "port",
        "allowed_hosts",
        "allowed_origins",
    )
    values = vars(args)
    return {key: values[key] for key in keys if values.get(key) is not None}
