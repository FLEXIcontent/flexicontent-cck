"""MCP tools for the FLEXIcontent Joomla JSON:API."""

from __future__ import annotations

import base64
import logging
from functools import lru_cache
from pathlib import Path
from typing import Annotated, Any, Literal

from mcp.server.fastmcp import FastMCP
from mcp.types import ToolAnnotations
from pydantic import Field

from .api import (
    FlexiApiError,
    FlexiClient,
    Params,
    compact_item,
    compact_taxonomy,
)
from .cli import build_parser, config_overrides
from .config import Config

LOGGER = logging.getLogger("flexicontent_mcp")

SERVER_INSTRUCTIONS = """
FLEXIcontent content management tools backed by the Joomla JSON:API.

Before creating or editing an item, call flexi_describe to discover the valid
type ids, category ids and custom field names. Use those exact FLEXIcontent ids
in write payloads: tag_ids/category_ids are FLEXIcontent ids, while the read API
also exposes Joomla tag ids in relationships. Prefer small searches, then
flexi_get_item for the full record including flexi_fields.

Types, fields, categories and tags can also be created, updated and deleted
with flexi_create_taxonomy / flexi_update_taxonomy / flexi_delete_taxonomy.
Call flexi_describe(kind="taxonomies") first for the allowed attributes of each
resource and its defaults. Writes respect the Joomla API token's ACL.
Deletion requires confirm=true.

Images and files are uploaded with flexi_upload_file. Pass its field_value in
the 'fields' object of flexi_create_item/flexi_update_item to attach the upload
to an item. An optional field_id (or field_name) applies that field's extension
and size limits and chooses the right storage area.
""".strip()

TaxonomyResource = Literal["categories", "tags", "types", "fields"]

TAXONOMY_SCHEMAS: dict[str, dict[str, Any]] = {
    "types": {
        "required": ["name"],
        "defaults": {"published": 1, "itemscreatable": 0, "access": 1},
        "allowed": [
            "name", "alias", "description", "published", "itemscreatable",
            "access", "attribs", "layouts", "iflayout",
        ],
    },
    "fields": {
        "required": ["label"],
        "defaults": {
            "field_type": "text", "published": 1, "access": 1,
            "ordering": 999, "tid": [],
        },
        "allowed": [
            "field_type", "name", "label", "description", "published", "access",
            "ordering", "issearch", "isadvsearch", "isfilter", "isadvfilter",
            "untranslatable", "formhidden", "valueseditable", "edithelp",
            "positions", "attribs", "tid", "type_ids", "types", "layouts",
            "iflayout",
        ],
    },
    "categories": {
        "required": ["title"],
        "defaults": {"parent_id": 1, "published": 1, "access": 1, "language": "*"},
        "allowed": [
            "title", "alias", "parent_id", "published", "access", "language",
            "note", "description", "metadesc", "metakey", "metadata",
            "params", "layouts",
        ],
    },
    "tags": {
        "required": ["name"],
        "defaults": {"published": 1},
        "allowed": ["name", "alias", "published", "jtag_id"],
    },
}

server = FastMCP("flexicontent", instructions=SERVER_INSTRUCTIONS)


_active_config: Config | None = None


def set_config(config: Config) -> None:
    """Replace the active configuration (used by the CLI and tests)."""
    global _active_config
    _active_config = config
    _client_for.cache_clear()


def get_config() -> Config:
    """Return the active configuration, loading it on first use."""
    global _active_config
    if _active_config is None:
        _active_config = Config.load()
    return _active_config


@lru_cache(maxsize=1)
def _client_for(config: Config) -> FlexiClient:
    return FlexiClient(config)


def get_client() -> FlexiClient:
    """Return the API client for the active configuration."""
    return _client_for(get_config())


def _add(params: Params, key: str, value: Any) -> None:
    if value is not None:
        params.append((key, value))


def _page(limit: int, offset: int) -> tuple[int, int]:
    max_limit = get_config().max_limit
    if limit < 1:
        raise ValueError("limit must be at least 1")
    if offset < 0:
        raise ValueError("offset must be zero or greater")
    return min(limit, max_limit), offset


def _require_writes() -> None:
    if get_config().read_only:
        raise ValueError("Writes are disabled by configuration (read_only=true)")


def _payload(values: dict[str, Any]) -> dict[str, Any]:
    return {key: value for key, value in values.items() if value is not None}


@server.tool(annotations=ToolAnnotations(readOnlyHint=True, openWorldHint=True))
def flexi_search_items(
    query: Annotated[str | None, Field(description="Search in item titles.")] = None,
    type_id: Annotated[int | None, Field(description="FLEXIcontent type id.")] = None,
    category_id: Annotated[int | None, Field(description="FLEXIcontent category id.")] = None,
    tag_id: Annotated[int | None, Field(description="FLEXIcontent tag id.")] = None,
    state: Annotated[int | None, Field(description="Publication state. Managers may use -5, -2, 0, 1.")] = None,
    author_id: Annotated[int | None, Field(description="Joomla author id.")] = None,
    access: Annotated[int | None, Field(description="Joomla access level.")] = None,
    language: Annotated[str | None, Field(description="Language tag, e.g. fr-FR.")] = None,
    field: Annotated[str | None, Field(description="Custom field name or id to filter on.")] = None,
    field_value: Annotated[str | None, Field(description="Value of the custom field filter.")] = None,
    field_match: Annotated[Literal["exact", "like"], Field(description="Custom field match mode.")] = "exact",
    ordering: Annotated[str | None, Field(description="API ordering column, e.g. a.created.")] = None,
    direction: Annotated[Literal["asc", "desc"], Field(description="Sort direction.")] = "asc",
    limit: Annotated[int, Field(description="Maximum number of items to return.")] = 20,
    offset: Annotated[int, Field(description="Number of items to skip.")] = 0,
) -> dict[str, Any]:
    """Search FLEXIcontent items and return a compact list."""
    limit, offset = _page(limit, offset)
    params: Params = []

    _add(params, "filter[search]", query)
    _add(params, "filter[type]", type_id)
    _add(params, "filter[category]", category_id)
    _add(params, "filter[tag]", tag_id)
    _add(params, "filter[state]", state)
    _add(params, "filter[author]", author_id)
    _add(params, "filter[access]", access)
    _add(params, "filter[language]", language)
    _add(params, "filter[field]", field)

    if field_value is not None:
        _add(params, "filter[field_value]", field_value)
        _add(params, "filter[field_match]", field_match)

    _add(params, "list[ordering]", ordering)
    if direction != "asc":
        _add(params, "list[direction]", direction)

    params.append(("page[limit]", limit))
    params.append(("page[offset]", offset))

    response = get_client().list_items(params)
    items = [compact_item(entry) for entry in response.get("data", []) if isinstance(entry, dict)]

    return {
        "items": items,
        "count": len(items),
        "pagination": response.get("meta", {}).get("pagination"),
    }


@server.tool(annotations=ToolAnnotations(readOnlyHint=True, openWorldHint=True))
def flexi_get_item(
    id: Annotated[int, Field(description="FLEXIcontent item id.", ge=1)],
    include_fields: Annotated[bool, Field(description="Include custom flexi_fields values.")] = True,
) -> dict[str, Any]:
    """Get one FLEXIcontent item by id."""
    response = get_client().get_item(id)
    entry = response.get("data")

    if not isinstance(entry, dict):
        raise FlexiApiError(404, f"Item {id} was not found or is not visible")

    return {"item": compact_item(entry, include_fields=include_fields)}


@server.tool(annotations=ToolAnnotations(readOnlyHint=True, openWorldHint=True))
def flexi_list_taxonomies(
    resource: Annotated[
        Literal["categories", "tags", "types", "fields"],
        Field(description="Taxonomy resource to list."),
    ],
    query: Annotated[str | None, Field(description="Search by name, label or alias.")] = None,
    state: Annotated[int | None, Field(description="Publication state.")] = None,
    parent_id: Annotated[int | None, Field(description="Parent category id (categories only).")] = None,
    level: Annotated[int | None, Field(description="Category level (categories only).")] = None,
    language: Annotated[str | None, Field(description="Language tag (categories only).")] = None,
    jtag: Annotated[int | None, Field(description="0/1 Joomla tag bridge filter (tags only).")] = None,
    core: Annotated[int, Field(description="0 custom fields only, 1 core fields only (fields only).")] = 0,
    type_id: Annotated[int | None, Field(description="Fields attached to this type id (fields only).")] = None,
    include_form: Annotated[bool, Field(description="Include form_* pseudo-fields (fields only).")] = False,
    limit: Annotated[int, Field(description="Maximum number of records to return.")] = 50,
    offset: Annotated[int, Field(description="Number of records to skip.")] = 0,
) -> dict[str, Any]:
    """List FLEXIcontent categories, tags, types or fields."""
    limit, offset = _page(limit, offset)
    params: Params = []

    _add(params, "filter[search]", query)
    _add(params, "filter[state]", state)

    if resource == "categories":
        _add(params, "filter[parent_id]", parent_id)
        _add(params, "filter[level]", level)
        _add(params, "filter[language]", language)
    elif resource == "tags":
        _add(params, "filter[jtag]", jtag)
    elif resource == "fields":
        _add(params, "filter[core]", core)
        _add(params, "filter[form]", 1 if include_form else 0)
        _add(params, "filter[type]", type_id)

    params.append(("page[limit]", limit))
    params.append(("page[offset]", offset))

    response = get_client().list_resource(resource, params)
    entries = [
        compact_taxonomy(resource, entry)
        for entry in response.get("data", [])
        if isinstance(entry, dict)
    ]

    return {
        "resource": resource,
        "entries": entries,
        "count": len(entries),
    }


@server.tool(annotations=ToolAnnotations(readOnlyHint=True, openWorldHint=True))
def flexi_describe(
    kind: Annotated[
        Literal["write", "fields", "types", "categories", "taxonomies"],
        Field(description="Which schema bundle to return."),
    ] = "write",
) -> dict[str, Any]:
    """Return ids and schemas needed to build FLEXIcontent write payloads."""
    if kind == "taxonomies":
        return {
            "kind": kind,
            "taxonomies": TAXONOMY_SCHEMAS,
            "note": (
                "Pass the attributes object to flexi_create_taxonomy or "
                "flexi_update_taxonomy. Unknown attributes are ignored by the API."
            ),
        }

    client = get_client()
    limit = get_config().max_limit
    result: dict[str, Any] = {"kind": kind}

    if kind in ("write", "types"):
        response = client.list_resource("types", [("page[limit]", limit)])
        result["types"] = [compact_taxonomy("types", e) for e in response.get("data", [])]

    if kind in ("write", "categories"):
        response = client.list_resource("categories", [("page[limit]", limit)])
        result["categories"] = [compact_taxonomy("categories", e) for e in response.get("data", [])]

    if kind in ("write", "fields"):
        response = client.list_resource(
            "fields",
            [("filter[core]", 0), ("filter[form]", 0), ("page[limit]", limit)],
        )
        result["fields"] = [compact_taxonomy("fields", e) for e in response.get("data", [])]

    result["note"] = (
        "Use FLEXIcontent ids for type_id, catid, categories and tags. "
        "Custom fields are passed by field name in the 'fields' object."
    )
    return result


@server.tool(annotations=ToolAnnotations(idempotentHint=False, openWorldHint=True))
def flexi_create_item(
    title: Annotated[str, Field(description="Item title. Required.", min_length=1)],
    catid: Annotated[int, Field(description="Main FLEXIcontent category id. Required.", ge=1)],
    type_id: Annotated[int, Field(description="FLEXIcontent type id. Required.", ge=1)],
    alias: Annotated[str | None, Field(description="URL alias.")] = None,
    state: Annotated[int | None, Field(description="Publication state.")] = None,
    access: Annotated[int | None, Field(description="Joomla access level.")] = None,
    language: Annotated[str | None, Field(description="Language tag.")] = None,
    created_by: Annotated[int | None, Field(description="Joomla author id.")] = None,
    tags: Annotated[list[int] | None, Field(description="FLEXIcontent tag ids.")] = None,
    categories: Annotated[list[int] | None, Field(description="Additional FLEXIcontent category ids.")] = None,
    fields: Annotated[dict[str, Any] | None, Field(description="Custom field values keyed by field name.")] = None,
    text: Annotated[str | None, Field(description="Body text; split into introtext/fulltext by FLEXIcontent.")] = None,
    introtext: Annotated[str | None, Field(description="Introductory text.")] = None,
    fulltext: Annotated[str | None, Field(description="Text after the readmore marker.")] = None,
    metakey: Annotated[str | None, Field(description="Meta keywords.")] = None,
    metadesc: Annotated[str | None, Field(description="Meta description.")] = None,
    note: Annotated[str | None, Field(description="Admin note.")] = None,
    publish_up: Annotated[str | None, Field(description="Publication start date.")] = None,
    publish_down: Annotated[str | None, Field(description="Publication end date.")] = None,
    featured: Annotated[int | None, Field(description="Featured flag.")] = None,
) -> dict[str, Any]:
    """Create a FLEXIcontent item."""
    _require_writes()

    if not title.strip():
        raise ValueError("title must not be empty")
    if catid <= 0 or type_id <= 0:
        raise ValueError("catid and type_id must be greater than zero")

    payload = _payload({
        "title": title.strip(),
        "catid": catid,
        "type_id": type_id,
        "alias": alias,
        "state": state,
        "access": access,
        "language": language,
        "created_by": created_by,
        "tags": tags,
        "categories": categories,
        "fields": fields,
        "text": text,
        "introtext": introtext,
        "fulltext": fulltext,
        "metakey": metakey,
        "metadesc": metadesc,
        "note": note,
        "publish_up": publish_up,
        "publish_down": publish_down,
        "featured": featured,
    })

    response = get_client().request("POST", "/items", payload=payload)
    entry = response.get("data") if isinstance(response, dict) else None

    if not isinstance(entry, dict):
        raise FlexiApiError(201, "Item was created but the API returned no representation")

    return {"created": compact_item(entry, include_fields=True)}


def _resolve_field_id(client: FlexiClient, field_name: str) -> int:
    response = client.list_resource(
        "fields",
        [("filter[search]", field_name), ("page[limit]", 100)],
    )

    for entry in response.get("data", []):
        attrs = entry.get("attributes", {}) if isinstance(entry, dict) else {}
        if str(attrs.get("name", "")) == field_name or str(attrs.get("label", "")) == field_name:
            return int(attrs["id"])

    raise ValueError(f"FLEXIcontent field {field_name!r} was not found")


@server.tool(annotations=ToolAnnotations(idempotentHint=False, openWorldHint=True))
def flexi_upload_file(
    filename: Annotated[str | None, Field(description="Original file name with extension. Derived from content_path when omitted.")] = None,
    content_base64: Annotated[str | None, Field(description="Base64-encoded file content.")] = None,
    content_path: Annotated[str | None, Field(description="Local file path to upload.")] = None,
    field_id: Annotated[int | None, Field(description="Target FLEXIcontent field id, for field-specific limits and storage.", ge=1)] = None,
    field_name: Annotated[str | None, Field(description="Target field name or label, resolved through the fields API.")] = None,
    item_id: Annotated[int | None, Field(description="Item id owning the value. Required for folder-mode image fields.", ge=1)] = None,
    secure: Annotated[bool | None, Field(description="Store in secure uploads (true) or public medias (false); DB-mode image fields only.")] = None,
    alt: Annotated[str | None, Field(description="Alternative text for image fields.")] = None,
    description: Annotated[str | None, Field(description="Description stored on the FLEXIcontent file record.")] = None,
) -> dict[str, Any]:
    """Upload an image or file into FLEXIcontent storage."""
    _require_writes()

    if (content_base64 is None) == (content_path is None):
        raise ValueError("Provide exactly one of content_base64 or content_path")

    if content_path is not None:
        source = Path(content_path).expanduser()

        if not source.is_file():
            raise ValueError(f"File not found: {content_path}")

        filename = filename or source.name
        content_base64 = base64.b64encode(source.read_bytes()).decode("ascii")
    elif not filename:
        raise ValueError("filename is required when uploading content_base64")

    if not filename or not filename.strip():
        raise ValueError("filename must not be empty")

    if field_id is not None and field_name:
        raise ValueError("Provide either field_id or field_name, not both")

    client = get_client()

    if field_id is None and field_name:
        field_id = _resolve_field_id(client, field_name)

    payload = _payload({
        "filename": filename.strip(),
        "content_base64": content_base64,
        "field_id": field_id,
        "item_id": item_id,
        "secure": secure,
        "alt": alt,
        "description": description,
    })

    response = client.request("POST", "/media", payload=payload)
    entry = response.get("data") if isinstance(response, dict) else None

    if not isinstance(entry, dict):
        raise FlexiApiError(201, "File was uploaded but the API returned no representation")

    attrs = entry.get("attributes", {})
    uploaded = {key: attrs[key] for key in (
        "id", "filename", "original_name", "extension", "size", "mime_type",
        "secure", "storage", "url", "field_id", "field_type", "field_value",
    ) if key in attrs}

    return {
        "uploaded": uploaded,
        "field_value": attrs.get("field_value"),
        "hint": "Use field_value in flexi_create_item/flexi_update_item fields.",
    }


@server.tool(annotations=ToolAnnotations(idempotentHint=True, openWorldHint=True))
def flexi_update_item(
    id: Annotated[int, Field(description="FLEXIcontent item id.", ge=1)],
    title: Annotated[str | None, Field(description="New title.")] = None,
    catid: Annotated[int | None, Field(description="New main FLEXIcontent category id.", ge=1)] = None,
    alias: Annotated[str | None, Field(description="New URL alias.")] = None,
    state: Annotated[int | None, Field(description="New publication state.")] = None,
    access: Annotated[int | None, Field(description="New Joomla access level.")] = None,
    language: Annotated[str | None, Field(description="New language tag.")] = None,
    tags: Annotated[list[int] | None, Field(description="Replace all FLEXIcontent tag ids.")] = None,
    categories: Annotated[list[int] | None, Field(description="Replace additional category ids.")] = None,
    fields: Annotated[dict[str, Any] | None, Field(description="Custom field values to change.")] = None,
    text: Annotated[str | None, Field(description="New body text.")] = None,
    introtext: Annotated[str | None, Field(description="New introtext.")] = None,
    fulltext: Annotated[str | None, Field(description="New fulltext.")] = None,
    metakey: Annotated[str | None, Field(description="New meta keywords.")] = None,
    metadesc: Annotated[str | None, Field(description="New meta description.")] = None,
    note: Annotated[str | None, Field(description="New admin note.")] = None,
    publish_up: Annotated[str | None, Field(description="New publication start date.")] = None,
    publish_down: Annotated[str | None, Field(description="New publication end date.")] = None,
    featured: Annotated[int | None, Field(description="New featured flag.")] = None,
) -> dict[str, Any]:
    """Partially update a FLEXIcontent item."""
    _require_writes()

    payload = _payload({
        "title": title,
        "catid": catid,
        "alias": alias,
        "state": state,
        "access": access,
        "language": language,
        "tags": tags,
        "categories": categories,
        "fields": fields,
        "text": text,
        "introtext": introtext,
        "fulltext": fulltext,
        "metakey": metakey,
        "metadesc": metadesc,
        "note": note,
        "publish_up": publish_up,
        "publish_down": publish_down,
        "featured": featured,
    })

    if not payload:
        raise ValueError("At least one field must be provided for an update")

    client = get_client()
    before_response = client.get_item(id)
    before_entry = before_response.get("data")
    before = compact_item(before_entry, include_fields=True) if isinstance(before_entry, dict) else None

    response = client.request("PATCH", f"/items/{id}", payload=payload)
    entry = response.get("data") if isinstance(response, dict) else None
    after = compact_item(entry, include_fields=True) if isinstance(entry, dict) else None

    return {"before": before, "after": after}


@server.tool(annotations=ToolAnnotations(destructiveHint=True, idempotentHint=True, openWorldHint=True))
def flexi_delete_item(
    id: Annotated[int, Field(description="FLEXIcontent item id.", ge=1)],
    confirm: Annotated[bool, Field(description="Must be true to confirm deletion.")] = False,
) -> dict[str, Any]:
    """Delete a FLEXIcontent item. Requires confirm=true."""
    _require_writes()

    if confirm is not True:
        raise ValueError("Deletion requires confirm=true")

    client = get_client()
    before_response = client.get_item(id)
    before_entry = before_response.get("data")
    before = compact_item(before_entry) if isinstance(before_entry, dict) else None

    client.request("DELETE", f"/items/{id}")

    return {"deleted": id, "before": before}


@server.tool(annotations=ToolAnnotations(readOnlyHint=True, openWorldHint=True))
def flexi_get_taxonomy(
    resource: Annotated[
        TaxonomyResource,
        Field(description="Taxonomy resource to read."),
    ],
    id: Annotated[int, Field(description="FLEXIcontent record id.", ge=1)],
) -> dict[str, Any]:
    """Get one type, field, category or tag by id."""
    response = get_client().get_resource(resource, id)
    entry = response.get("data") if isinstance(response, dict) else None

    if not isinstance(entry, dict):
        raise FlexiApiError(404, f"{resource} record {id} was not found or is not visible")

    return {"resource": resource, "entry": compact_taxonomy(resource, entry)}


def _taxonomy_payload(
    resource: TaxonomyResource,
    attributes: dict[str, Any],
    require_schema: bool,
) -> dict[str, Any]:
    values = {key: value for key, value in attributes.items() if value is not None}

    if not values:
        raise ValueError("At least one attribute must be provided")

    schema = TAXONOMY_SCHEMAS[resource]

    if require_schema:
        singular = {
            "categories": "category",
            "tags": "tag",
            "types": "type",
            "fields": "field",
        }[resource]

        for key in schema["required"]:
            if key not in values or values[key] in (None, ""):
                raise ValueError(f"{key} is required to create a {singular}")

    return values


@server.tool(annotations=ToolAnnotations(idempotentHint=False, openWorldHint=True))
def flexi_create_taxonomy(
    resource: Annotated[
        TaxonomyResource,
        Field(description="Taxonomy resource to create."),
    ],
    attributes: Annotated[
        dict[str, Any],
        Field(description="Attributes to create; see flexi_describe(kind='taxonomies')."),
    ],
) -> dict[str, Any]:
    """Create a FLEXIcontent type, field, category or tag."""
    _require_writes()

    payload = _taxonomy_payload(resource, attributes, require_schema=True)
    response = get_client().request("POST", f"/{resource}", payload=payload)
    entry = response.get("data") if isinstance(response, dict) else None

    if not isinstance(entry, dict):
        raise FlexiApiError(201, f"{resource} record was created but no representation was returned")

    return {"created": compact_taxonomy(resource, entry)}


@server.tool(annotations=ToolAnnotations(idempotentHint=True, openWorldHint=True))
def flexi_update_taxonomy(
    resource: Annotated[
        TaxonomyResource,
        Field(description="Taxonomy resource to update."),
    ],
    id: Annotated[int, Field(description="FLEXIcontent record id.", ge=1)],
    attributes: Annotated[
        dict[str, Any],
        Field(description="Attributes to change; omitted attributes keep their current value."),
    ],
) -> dict[str, Any]:
    """Partially update a FLEXIcontent type, field, category or tag."""
    _require_writes()

    payload = _taxonomy_payload(resource, attributes, require_schema=False)
    client = get_client()

    before_response = client.get_resource(resource, id)
    before_entry = before_response.get("data")
    before = compact_taxonomy(resource, before_entry) if isinstance(before_entry, dict) else None

    response = client.request("PATCH", f"/{resource}/{id}", payload=payload)
    entry = response.get("data") if isinstance(response, dict) else None

    return {
        "before": before,
        "after": compact_taxonomy(resource, entry) if isinstance(entry, dict) else None,
    }


@server.tool(annotations=ToolAnnotations(destructiveHint=True, idempotentHint=True, openWorldHint=True))
def flexi_delete_taxonomy(
    resource: Annotated[
        TaxonomyResource,
        Field(description="Taxonomy resource to delete."),
    ],
    id: Annotated[int, Field(description="FLEXIcontent record id.", ge=1)],
    confirm: Annotated[bool, Field(description="Must be true to confirm deletion.")] = False,
) -> dict[str, Any]:
    """Delete a FLEXIcontent type, field, category or tag. Requires confirm=true."""
    _require_writes()

    if confirm is not True:
        raise ValueError("Deletion requires confirm=true")

    client = get_client()
    before_response = client.get_resource(resource, id)
    before_entry = before_response.get("data")
    before = compact_taxonomy(resource, before_entry) if isinstance(before_entry, dict) else None

    client.request("DELETE", f"/{resource}/{id}")

    return {"deleted": id, "resource": resource, "before": before}


def main(argv: list[str] | None = None) -> None:
    """Parse the CLI, load the configuration and run the MCP server."""
    args = build_parser().parse_args(argv)
    config = Config.load(cli=config_overrides(args))
    set_config(config)

    logging.basicConfig(level=getattr(logging, args.log_level))
    LOGGER.info(
        "Starting FLEXIcontent MCP server (profile=%s, transport=%s)",
        config.profile,
        config.transport,
    )

    if config.transport == "stdio":
        server.run(transport="stdio")
        return

    server.settings.host = config.host
    server.settings.port = config.port

    security = server.settings.transport_security
    if security is not None:
        if config.allowed_hosts:
            security.allowed_hosts = list(config.allowed_hosts)
        if config.allowed_origins:
            security.allowed_origins = list(config.allowed_origins)
        if config.host not in ("127.0.0.1", "localhost", "::1") and not config.allowed_hosts:
            security.allowed_hosts = [f"{config.host}:*", "localhost:*", "127.0.0.1:*"]
            LOGGER.warning(
                "HTTP server bound to %s; configure allowed_hosts or put it behind "
                "an authenticated reverse proxy",
                config.host,
            )

    LOGGER.info(
        "MCP endpoint: http://%s:%s/mcp",
        config.host,
        config.port,
    )
    server.run(transport=config.transport)


if __name__ == "__main__":
    main()
