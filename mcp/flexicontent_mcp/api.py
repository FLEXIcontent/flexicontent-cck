"""Thin HTTP client for the FLEXIcontent Joomla JSON:API."""

from __future__ import annotations

import json
import ssl
from typing import Any, Iterable
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode
from urllib.request import Request, urlopen

from .config import Config

Params = list[tuple[str, Any]]


class FlexiApiError(Exception):
    """An error returned by the Joomla API."""

    def __init__(self, status: int, message: str, payload: Any = None) -> None:
        super().__init__(message)
        self.status = status
        self.message = message
        self.payload = payload


def _message_from_body(body: str, status: int, payload: Any) -> str:
    if isinstance(payload, dict):
        errors = payload.get("errors")
        if isinstance(errors, list):
            messages = []
            for error in errors:
                if not isinstance(error, dict):
                    continue
                detail = error.get("detail") or error.get("title")
                if detail:
                    messages.append(str(detail))
            if messages:
                return "; ".join(messages)
        for key in ("message", "error"):
            if payload.get(key):
                return str(payload[key])
    body = body.strip()
    if body:
        return body[:500]
    return f"HTTP {status}"


def _decode(raw: bytes) -> Any:
    if not raw:
        return None
    try:
        return json.loads(raw.decode("utf-8"))
    except (UnicodeDecodeError, json.JSONDecodeError):
        return None


class FlexiClient:
    """Call the FLEXIcontent JSON:API with a Joomla API token."""

    def __init__(self, config: Config) -> None:
        self.config = config
        self._ssl_context = None
        if not config.verify_ssl:
            context = ssl.create_default_context()
            context.check_hostname = False
            context.verify_mode = ssl.CERT_NONE
            self._ssl_context = context

    def request(
        self,
        method: str,
        path: str,
        params: Params | None = None,
        payload: dict[str, Any] | None = None,
    ) -> Any:
        """Perform an API request and return decoded JSON, if any."""
        url = self.config.base_url + path
        if params:
            url += "?" + urlencode(params, doseq=True)

        data = None
        headers = {
            "Accept": "application/vnd.api+json",
            "Authorization": f"Bearer {self.config.token}",
        }
        if payload is not None:
            data = json.dumps(payload).encode("utf-8")
            headers["Content-Type"] = "application/json"

        request = Request(url, data=data, headers=headers, method=method)

        try:
            with urlopen(
                request,
                timeout=self.config.timeout,
                context=self._ssl_context,
            ) as response:
                return _decode(response.read())
        except HTTPError as exc:
            body = exc.read().decode("utf-8", errors="replace")
            decoded = _decode(body.encode("utf-8"))
            raise FlexiApiError(
                exc.code,
                _message_from_body(body, exc.code, decoded),
                decoded,
            ) from exc
        except URLError as exc:
            raise FlexiApiError(
                0,
                f"Cannot reach FLEXIcontent API at {self.config.base_url}: {exc.reason}",
            ) from exc

    def list_items(self, params: Params) -> dict[str, Any]:
        """Get an item list, treating an out-of-range offset as an empty list."""
        try:
            return self.request("GET", "/items", params) or {"data": []}
        except FlexiApiError as exc:
            if exc.status == 404 and _offset(params) > 0:
                return {"data": []}
            raise

    def list_resource(self, resource: str, params: Params) -> dict[str, Any]:
        """Get a taxonomy list, treating an out-of-range offset as empty."""
        try:
            return self.request("GET", f"/{resource}", params) or {"data": []}
        except FlexiApiError as exc:
            if exc.status == 404 and _offset(params) > 0:
                return {"data": []}
            raise

    def get_item(self, item_id: int, params: Params | None = None) -> dict[str, Any]:
        """Get one item by id."""
        return self.request("GET", f"/items/{int(item_id)}", params) or {}


def _offset(params: Iterable[tuple[str, Any]]) -> int:
    """Return the requested page offset, if any."""
    for key, value in params:
        if key == "page[offset]":
            try:
                return int(value)
            except (TypeError, ValueError):
                return 0
    return 0


def attributes(entry: dict[str, Any]) -> dict[str, Any]:
    """Return the attributes of one JSON:API resource."""
    value = entry.get("attributes") if isinstance(entry, dict) else None
    return value if isinstance(value, dict) else {}


def compact_item(entry: dict[str, Any], include_fields: bool = False) -> dict[str, Any]:
    """Reduce an item resource to the fields an agent usually needs."""
    attrs = attributes(entry)
    keys = (
        "id",
        "title",
        "state",
        "type_id",
        "type_name",
        "catid",
        "category_title",
        "tag_ids",
        "created",
        "modified",
    )
    result = {key: attrs[key] for key in keys if key in attrs}
    if include_fields and "flexi_fields" in attrs:
        result["flexi_fields"] = attrs["flexi_fields"]
    return result


def compact_taxonomy(resource: str, entry: dict[str, Any]) -> dict[str, Any]:
    """Reduce a taxonomy resource to a compact representation."""
    attrs = attributes(entry)
    if resource == "fields":
        keys = ("id", "name", "label", "field_type", "iscore", "published", "access")
    else:
        keys = ("id", "name", "title", "alias", "published", "state", "item_count")
    return {key: attrs[key] for key in keys if key in attrs}
