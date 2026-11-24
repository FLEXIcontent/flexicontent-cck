from __future__ import annotations

import os
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

from flexicontent_mcp.config import Config

BASE_ENV = {
    "FLEXI_BASE_URL": "https://example.test/api/index.php/v1/flexicontent/",
    "FLEXI_API_TOKEN": "env-token",
}


class ConfigTests(unittest.TestCase):
    def test_env_only_configuration_keeps_legacy_variables(self) -> None:
        with patch.dict(os.environ, {
            **BASE_ENV,
            "FLEXI_READONLY": "1",
            "FLEXI_VERIFY_SSL": "0",
            "FLEXI_TIMEOUT": "7.5",
            "FLEXI_MAX_LIMIT": "12",
        }, clear=True):
            config = Config.from_env()

        self.assertEqual(config.base_url, "https://example.test/api/index.php/v1/flexicontent")
        self.assertEqual(config.token, "env-token")
        self.assertTrue(config.read_only)
        self.assertFalse(config.verify_ssl)
        self.assertEqual(config.timeout, 7.5)
        self.assertEqual(config.max_limit, 12)
        self.assertEqual(config.transport, "stdio")
        self.assertNotIn("env-token", repr(config))

    def test_from_env_ignores_any_toml_profile(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "config.toml"
            path.write_text(
                '[profiles.default]\nbase_url = "https://from-file.test/api"\n',
                encoding="utf-8",
            )
            with patch.dict(os.environ, {
                **BASE_ENV,
                "FLEXI_CONFIG": str(path),
            }, clear=True):
                config = Config.from_env()

        self.assertEqual(config.base_url, "https://example.test/api/index.php/v1/flexicontent")

    def test_profile_is_loaded_from_toml(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "config.toml"
            path.write_text(
                'default_profile = "prod"\n'
                '[profiles.dev]\n'
                'base_url = "https://dev.test/api"\n'
                'token_env = "DEV_TOKEN"\n'
                'verify_ssl = false\n'
                '[profiles.prod]\n'
                'base_url = "https://prod.test/api"\n'
                'token_file = "token.txt"\n'
                'read_only = true\n'
                'transport = "streamable-http"\n'
                'port = 8123\n',
                encoding="utf-8",
            )
            (Path(tmp) / "token.txt").write_text("prod-token\n", encoding="utf-8")

            with patch.dict(os.environ, {
                "FLEXI_CONFIG": str(path),
                "DEV_TOKEN": "dev-token",
            }, clear=True):
                prod = Config.load()
                dev = Config.load(profile="dev")

            self.assertEqual(prod.profile, "prod")
            self.assertEqual(prod.token, "prod-token")
            self.assertTrue(prod.read_only)
            self.assertEqual(prod.transport, "streamable-http")
            self.assertEqual(prod.port, 8123)
            self.assertEqual(dev.base_url, "https://dev.test/api")
            self.assertEqual(dev.token, "dev-token")
            self.assertFalse(dev.verify_ssl)

    def test_cli_overrides_env_and_profile(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "config.toml"
            path.write_text(
                '[profiles.dev]\n'
                'base_url = "https://dev.test/api"\n'
                'token_env = "DEV_TOKEN"\n'
                'read_only = true\n',
                encoding="utf-8",
            )

            with patch.dict(os.environ, {
                "FLEXI_CONFIG": str(path),
                "FLEXI_API_TOKEN": "env-token",
                "DEV_TOKEN": "dev-token",
            }, clear=True):
                config = Config.load(profile="dev", cli={
                    "base_url": "https://cli.test/api",
                    "token_env": "DEV_TOKEN",
                    "read_only": False,
                    "max_limit": 5,
                })

            self.assertEqual(config.base_url, "https://cli.test/api")
            self.assertEqual(config.token, "dev-token")
            self.assertFalse(config.read_only)
            self.assertEqual(config.max_limit, 5)

    def test_env_token_beats_a_literal_profile_token(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "config.toml"
            path.write_text(
                '[profiles.dev]\nbase_url = "https://dev.test/api"\ntoken = "profile-token"\n',
                encoding="utf-8",
            )
            with patch.dict(os.environ, {
                "FLEXI_CONFIG": str(path),
                "FLEXI_API_TOKEN": "env-token",
            }, clear=True):
                config = Config.load(profile="dev")

        self.assertEqual(config.token, "env-token")

    def test_token_command_is_executed_without_a_shell(self) -> None:
        command = f'{sys.executable} -c "print(\'from-command\')"'
        with patch.dict(os.environ, {
            **BASE_ENV,
            "FLEXI_TOKEN_COMMAND": command,
        }, clear=True):
            config = Config.load()

        self.assertEqual(config.token, "from-command")

    def test_missing_token_env_is_reported(self) -> None:
        with patch.dict(os.environ, {
            "FLEXI_BASE_URL": "https://example.test/api",
            "FLEXI_TOKEN_ENV": "MISSING_TOKEN_VAR",
        }, clear=True):
            with self.assertRaisesRegex(RuntimeError, "MISSING_TOKEN_VAR"):
                Config.load()

    def test_unknown_profile_and_key_are_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "config.toml"
            path.write_text('[profiles.dev]\nbase_url = "https://dev.test/api"\n',
                            encoding="utf-8")

            with patch.dict(os.environ, {"FLEXI_CONFIG": str(path)}, clear=True):
                with self.assertRaisesRegex(RuntimeError, "not found"):
                    Config.load(profile="nope")

            path.write_text(
                '[profiles.dev]\nbase_url = "https://dev.test/api"\ntypo = 1\n',
                encoding="utf-8",
            )
            with patch.dict(os.environ, {"FLEXI_CONFIG": str(path)}, clear=True):
                with self.assertRaisesRegex(RuntimeError, "typo"):
                    Config.load(profile="dev")

    def test_transport_and_port_are_validated(self) -> None:
        with patch.dict(os.environ, BASE_ENV, clear=True):
            with self.assertRaisesRegex(RuntimeError, "Invalid transport"):
                Config.load(cli={"transport": "http"})
            with self.assertRaisesRegex(RuntimeError, "65535"):
                Config.load(cli={"port": 70000})


if __name__ == "__main__":
    unittest.main()
