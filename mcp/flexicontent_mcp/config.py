"""Environment configuration for the FLEXIcontent MCP server."""

from __future__ import annotations

import os
from dataclasses import dataclass


def _bool(name: str, default: bool = False) -> bool:
    """Read a boolean environment variable."""
    value = os.environ.get(name)
    if value is None or value.strip() == "":
        return default
    return value.strip().lower() in {"1", "true", "yes", "on"}


def _positive_int(name: str, default: int) -> int:
    """Read a strictly positive integer environment variable."""
    raw = os.environ.get(name)
    if raw is None or raw.strip() == "":
        return default
    try:
        value = int(raw)
    except ValueError as exc:
        raise RuntimeError(f"{name} must be an integer, got {raw!r}") from exc
    if value <= 0:
        raise RuntimeError(f"{name} must be greater than zero, got {value}")
    return value


def _positive_float(name: str, default: float) -> float:
    """Read a strictly positive float environment variable."""
    raw = os.environ.get(name)
    if raw is None or raw.strip() == "":
        return default
    try:
        value = float(raw)
    except ValueError as exc:
        raise RuntimeError(f"{name} must be a number, got {raw!r}") from exc
    if value <= 0:
        raise RuntimeError(f"{name} must be greater than zero, got {value}")
    return value


@dataclass(frozen=True)
class Config:
    """Runtime configuration."""

    base_url: str
    token: str
    read_only: bool
    verify_ssl: bool
    timeout: float
    max_limit: int

    @classmethod
    def from_env(cls) -> "Config":
        """Build the configuration from environment variables."""
        base_url = os.environ.get("FLEXI_BASE_URL", "").strip().rstrip("/")
        token = os.environ.get("FLEXI_API_TOKEN", "").strip()

        if not base_url:
            raise RuntimeError(
                "FLEXI_BASE_URL is required, e.g. "
                "https://example.ddev.site/api/index.php/v1/flexicontent"
            )
        if not token:
            raise RuntimeError("FLEXI_API_TOKEN is required")

        return cls(
            base_url=base_url,
            token=token,
            read_only=_bool("FLEXI_READONLY"),
            verify_ssl=_bool("FLEXI_VERIFY_SSL", default=True),
            timeout=_positive_float("FLEXI_TIMEOUT", 30.0),
            max_limit=_positive_int("FLEXI_MAX_LIMIT", 100),
        )
