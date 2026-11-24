from __future__ import annotations

import unittest
from unittest.mock import patch

from flexicontent_mcp import server
from flexicontent_mcp.cli import build_parser, config_overrides

BASE_ENV = {
    "FLEXI_BASE_URL": "https://example.test/api/index.php/v1/flexicontent",
    "FLEXI_API_TOKEN": "token",
}


class CliTests(unittest.TestCase):
    def test_parser_only_returns_explicit_overrides(self) -> None:
        args = build_parser().parse_args([
            "--profile", "dev",
            "--no-read-only",
            "--transport", "streamable-http",
            "--port", "8123",
            "--allowed-host", "mcp.example.com:*",
        ])

        overrides = config_overrides(args)
        self.assertEqual(overrides["profile"], "dev")
        self.assertIs(overrides["read_only"], False)
        self.assertEqual(overrides["transport"], "streamable-http")
        self.assertEqual(overrides["port"], 8123)
        self.assertEqual(overrides["allowed_hosts"], ["mcp.example.com:*"])
        self.assertNotIn("host", overrides)
        self.assertNotIn("base_url", overrides)

    def test_main_runs_stdio_by_default(self) -> None:
        with (
            patch.dict("os.environ", BASE_ENV, clear=True),
            patch.object(server, "set_config") as set_config,
            patch.object(server.server, "run") as run,
        ):
            server.main([])

        run.assert_called_once_with(transport="stdio")
        self.assertEqual(set_config.call_args.args[0].token, "token")

    def test_main_configures_http_transport(self) -> None:
        settings = server.server.settings
        security = settings.transport_security
        saved = (settings.host, settings.port, list(security.allowed_hosts))
        self.addCleanup(
            lambda: (
                setattr(settings, "host", saved[0]),
                setattr(settings, "port", saved[1]),
                setattr(security, "allowed_hosts", saved[2]),
            )
        )

        with (
            patch.dict("os.environ", BASE_ENV, clear=True),
            patch.object(server, "set_config"),
            patch.object(server.server, "run") as run,
        ):
            server.main([
                "--transport", "streamable-http",
                "--host", "0.0.0.0",
                "--port", "8123",
                "--allowed-host", "mcp.example.com:*",
            ])

        run.assert_called_once_with(transport="streamable-http")
        self.assertEqual(server.server.settings.host, "0.0.0.0")
        self.assertEqual(server.server.settings.port, 8123)
        self.assertEqual(
            server.server.settings.transport_security.allowed_hosts,
            ["mcp.example.com:*"],
        )


if __name__ == "__main__":
    unittest.main()
