"""Layered configuration for the FLEXIcontent MCP server.

Priority (highest first): command line, environment variables, profile from the
TOML file, built-in defaults. The Joomla API token can be supplied directly or
resolved from an environment variable, a file or a command, so no secret needs
to be written in an MCP client configuration file.
"""

from __future__ import annotations

import logging
import os
import shlex
import subprocess
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any

try:  # Python >= 3.11
    import tomllib
except ModuleNotFoundError:  # Python 3.10
    import tomli as tomllib  # type: ignore[no-redef]

LOGGER = logging.getLogger("flexicontent_mcp.config")

TRANSPORTS = ("stdio", "sse", "streamable-http")

PROFILE_KEYS = {
    "base_url",
    "token",
    "token_env",
    "token_file",
    "token_command",
    "read_only",
    "verify_ssl",
    "timeout",
    "max_limit",
    "transport",
    "host",
    "port",
    "allowed_hosts",
    "allowed_origins",
}


def _bool(value: Any, label: str) -> bool:
    """Convert a configuration value to a boolean."""
    if isinstance(value, bool):
        return value
    if isinstance(value, (int, float)):
        return bool(value)
    if isinstance(value, str) and value.strip():
        normalised = value.strip().lower()
        if normalised in {"1", "true", "yes", "on"}:
            return True
        if normalised in {"0", "false", "no", "off"}:
            return False
    raise RuntimeError(f"{label} must be a boolean, got {value!r}")


def _positive_int(value: Any, label: str) -> int:
    """Convert a configuration value to a strictly positive integer."""
    try:
        number = int(value)
    except (TypeError, ValueError) as exc:
        raise RuntimeError(f"{label} must be an integer, got {value!r}") from exc
    if number <= 0:
        raise RuntimeError(f"{label} must be greater than zero, got {number}")
    return number


def _positive_float(value: Any, label: str) -> float:
    """Convert a configuration value to a strictly positive float."""
    try:
        number = float(value)
    except (TypeError, ValueError) as exc:
        raise RuntimeError(f"{label} must be a number, got {value!r}") from exc
    if number <= 0:
        raise RuntimeError(f"{label} must be greater than zero, got {number}")
    return number


def _first(*values: Any) -> Any:
    """Return the first non-empty value."""
    for value in values:
        if value is not None and value != "":
            return value
    return None


def _host_list(value: Any, label: str) -> tuple[str, ...]:
    """Parse a comma-separated list of hosts or origins."""
    if isinstance(value, str):
        return tuple(item.strip() for item in value.split(",") if item.strip())
    if isinstance(value, (list, tuple)):
        return tuple(str(item).strip() for item in value if str(item).strip())
    raise RuntimeError(f"{label} must be a comma-separated list, got {value!r}")


def _config_path(explicit: str | None = None) -> Path:
    """Resolve the TOML configuration path."""
    if explicit:
        return Path(explicit).expanduser()

    from_env = os.environ.get("FLEXI_CONFIG")
    if from_env:
        return Path(from_env).expanduser()

    xdg = os.environ.get("XDG_CONFIG_HOME")
    base = Path(xdg).expanduser() if xdg else Path.home() / ".config"
    return base / "flexicontent-mcp" / "config.toml"


def _load_document(path: Path) -> dict[str, Any]:
    """Read the TOML document, returning an empty one when absent."""
    try:
        with path.open("rb") as handle:
            document = tomllib.load(handle)
    except FileNotFoundError:
        return {}
    except tomllib.TOMLDecodeError as exc:
        raise RuntimeError(f"Invalid TOML configuration {path}: {exc}") from exc

    if not isinstance(document, dict):
        raise RuntimeError(f"Invalid configuration {path}: expected a TOML table")
    return document


def _read_token_file(raw_path: Any, label: str, base_dir: Path | None = None) -> str:
    """Read a token from a file (relative paths follow the config file)."""
    path = Path(str(raw_path)).expanduser()
    if not path.is_absolute() and base_dir is not None:
        path = base_dir / path
    try:
        token = path.read_text(encoding="utf-8").strip()
    except OSError as exc:
        raise RuntimeError(f"{label} cannot be read: {exc}") from exc
    if not token:
        raise RuntimeError(f"{label} is empty: {path}")
    return token


def _run_token_command(raw_command: Any, label: str) -> str:
    """Run a command that prints the token on stdout (argv, not a shell)."""
    argv = shlex.split(str(raw_command))
    if not argv:
        raise RuntimeError(f"{label} is empty")
    try:
        result = subprocess.run(
            argv,
            capture_output=True,
            text=True,
            check=True,
            timeout=30,
        )
    except (OSError, subprocess.SubprocessError) as exc:
        raise RuntimeError(f"{label} failed: {exc}") from exc

    token = result.stdout.strip()
    if not token:
        raise RuntimeError(f"{label} returned an empty token")
    return token


def _resolve_token(
    cli: dict[str, Any],
    env: dict[str, Any],
    profile: dict[str, Any],
    base_dir: Path | None = None,
) -> str:
    """Resolve the API token from the highest-priority configured source."""
    groups = (
        ("command line", cli),
        ("environment", env),
        ("profile", profile),
    )

    for label, group in groups:
        env_name = group.get("token_env")
        token_file = group.get("token_file")
        token_command = group.get("token_command")
        direct = group.get("token")

        if env_name:
            value = os.environ.get(str(env_name), "").strip()
            if not value:
                raise RuntimeError(f"{label}: token_env {str(env_name)!r} is not set")
            return value
        if token_file:
            return _read_token_file(token_file, f"{label}: token_file", base_dir)
        if token_command:
            return _run_token_command(token_command, f"{label}: token_command")
        if direct:
            if label == "profile":
                LOGGER.warning("API token is stored in the config file profile")
            return str(direct).strip()

    raise RuntimeError(
        "An API token is required: use FLEXI_API_TOKEN, --token-env, "
        "--token-file, --token-command or the profile's token_env/token_file/"
        "token_command setting"
    )


@dataclass(frozen=True)
class Config:
    """Runtime configuration."""

    base_url: str
    token: str = field(repr=False)
    read_only: bool = False
    verify_ssl: bool = True
    timeout: float = 30.0
    max_limit: int = 100
    profile: str = "default"
    transport: str = "stdio"
    host: str = "127.0.0.1"
    port: int = 8000
    allowed_hosts: tuple[str, ...] = ()
    allowed_origins: tuple[str, ...] = ()

    @classmethod
    def from_env(cls) -> "Config":
        """Build the configuration using only the environment (legacy API)."""
        return cls.load(config_path=os.devnull)

    @classmethod
    def load(
        cls,
        *,
        profile: str | None = None,
        config_path: str | None = None,
        cli: dict[str, Any] | None = None,
    ) -> "Config":
        """Build the configuration from CLI, environment, TOML and defaults."""
        cli = cli or {}
        config_file = _config_path(config_path or cli.get("config"))
        document = _load_document(config_file)
        profiles = document.get("profiles", {})

        profile_name = str(_first(
            profile,
            cli.get("profile"),
            os.environ.get("FLEXI_PROFILE"),
            document.get("default_profile"),
            "default",
        ))

        if not isinstance(profiles, dict):
            raise RuntimeError("Configuration key 'profiles' must be a TOML table")
        if profiles and profile_name not in profiles:
            raise RuntimeError(
                f"Profile {profile_name!r} was not found in the configuration file; "
                f"available profiles: {', '.join(sorted(map(str, profiles)))}"
            )

        section = profiles.get(profile_name, {})
        if not isinstance(section, dict):
            raise RuntimeError(f"Profile {profile_name!r} must be a TOML table")

        unknown = set(section) - PROFILE_KEYS
        if unknown:
            raise RuntimeError(
                f"Unknown keys in profile {profile_name!r}: {', '.join(sorted(map(str, unknown)))}"
            )

        env_sources = {
            "token_env": os.environ.get("FLEXI_TOKEN_ENV"),
            "token_file": os.environ.get("FLEXI_TOKEN_FILE"),
            "token_command": os.environ.get("FLEXI_TOKEN_COMMAND"),
            "token": os.environ.get("FLEXI_API_TOKEN"),
        }

        base_url = str(_first(
            cli.get("base_url"),
            os.environ.get("FLEXI_BASE_URL"),
            section.get("base_url"),
            "",
        )).strip().rstrip("/")
        if not base_url:
            raise RuntimeError(
                "FLEXI_BASE_URL (or profile base_url) is required, e.g. "
                "https://example.ddev.site/api/index.php/v1/flexicontent"
            )

        read_only = _pick(cli, "read_only", "FLEXI_READONLY", section, "read_only", False, _bool)
        verify_ssl = _pick(cli, "verify_ssl", "FLEXI_VERIFY_SSL", section, "verify_ssl", True, _bool)
        timeout = _pick(cli, "timeout", "FLEXI_TIMEOUT", section, "timeout", 30.0, _positive_float)
        max_limit = _pick(cli, "max_limit", "FLEXI_MAX_LIMIT", section, "max_limit", 100, _positive_int)
        port = _pick(cli, "port", "FLEXI_PORT", section, "port", 8000, _positive_int)

        transport = str(_first(
            cli.get("transport"),
            os.environ.get("FLEXI_TRANSPORT"),
            section.get("transport"),
            "stdio",
        ))
        if transport not in TRANSPORTS:
            raise RuntimeError(
                f"Invalid transport {transport!r}; expected one of {', '.join(TRANSPORTS)}"
            )
        if port > 65535:
            raise RuntimeError(f"port must be between 1 and 65535, got {port}")

        return cls(
            base_url=base_url,
            token=_resolve_token(cli, env_sources, section, config_file.parent),
            read_only=read_only,
            verify_ssl=verify_ssl,
            timeout=timeout,
            max_limit=max_limit,
            profile=profile_name,
            transport=transport,
            host=str(_first(
                cli.get("host"),
                os.environ.get("FLEXI_HOST"),
                section.get("host"),
                "127.0.0.1",
            )),
            port=port,
            allowed_hosts=_pick(
                cli, "allowed_hosts", "FLEXI_ALLOWED_HOSTS", section,
                "allowed_hosts", (), _host_list,
            ),
            allowed_origins=_pick(
                cli, "allowed_origins", "FLEXI_ALLOWED_ORIGINS", section,
                "allowed_origins", (), _host_list,
            ),
        )


def _pick(
    cli: dict[str, Any],
    cli_key: str,
    env_name: str,
    section: dict[str, Any],
    section_key: str,
    default: Any,
    convert: Any,
) -> Any:
    """Pick one value using the CLI > env > profile > defaults priority."""
    if cli.get(cli_key) is not None:
        return convert(cli[cli_key], cli_key)
    env_value = os.environ.get(env_name)
    if env_value is not None and env_value.strip():
        return convert(env_value, env_name)
    if section_key in section:
        return convert(section[section_key], f"profile.{section_key}")
    return default
