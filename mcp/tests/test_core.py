from __future__ import annotations

import unittest
from unittest.mock import Mock, patch

from flexicontent_mcp.api import FlexiApiError, FlexiClient, _message_from_body
from flexicontent_mcp.config import Config
from flexicontent_mcp.server import (
    _page,
    flexi_delete_item,
    flexi_search_items,
)

TEST_CONFIG = Config(
    base_url="https://example.test/api/index.php/v1/flexicontent",
    token="test-token",
    read_only=False,
    verify_ssl=True,
    timeout=5.0,
    max_limit=25,
)


class PageTests(unittest.TestCase):
    def test_limit_is_clamped_and_offset_is_kept(self) -> None:
        with patch("flexicontent_mcp.server.get_config", return_value=TEST_CONFIG):
            self.assertEqual(_page(1000, 4), (25, 4))

    def test_invalid_pagination_is_rejected(self) -> None:
        with patch("flexicontent_mcp.server.get_config", return_value=TEST_CONFIG):
            with self.assertRaises(ValueError):
                _page(0, 0)
            with self.assertRaises(ValueError):
                _page(10, -1)


class SearchTests(unittest.TestCase):
    def test_query_mapping_and_compaction(self) -> None:
        client = Mock()
        client.list_items.return_value = {
            "data": [
                {
                    "id": "7",
                    "attributes": {
                        "id": 7,
                        "title": "Appartement",
                        "state": 1,
                        "type_name": "Estate",
                    },
                }
            ],
            "meta": {"pagination": {"total": 1}},
        }

        with (
            patch("flexicontent_mcp.server.get_config", return_value=TEST_CONFIG),
            patch("flexicontent_mcp.server.get_client", return_value=client),
        ):
            result = flexi_search_items(
                query="appartement",
                type_id=3,
                field="field23",
                field_value="860000",
                field_match="like",
                limit=500,
            )

        params = client.list_items.call_args.args[0]
        self.assertIn(("filter[search]", "appartement"), params)
        self.assertIn(("filter[type]", 3), params)
        self.assertIn(("filter[field_value]", "860000"), params)
        self.assertIn(("filter[field_match]", "like"), params)
        self.assertIn(("page[limit]", 25), params)
        self.assertEqual(result["count"], 1)
        self.assertEqual(result["items"][0]["title"], "Appartement")


class WriteGuardTests(unittest.TestCase):
    def test_delete_requires_confirmation(self) -> None:
        with (
            patch("flexicontent_mcp.server.get_config", return_value=TEST_CONFIG),
            patch("flexicontent_mcp.server.get_client") as get_client,
        ):
            with self.assertRaises(ValueError):
                flexi_delete_item(id=7, confirm=False)
            get_client.assert_not_called()


class ErrorTests(unittest.TestCase):
    def test_jsonapi_error_messages_are_extracted(self) -> None:
        payload = {"errors": [{"title": "Forbidden", "detail": "Not allowed"}]}
        self.assertEqual(_message_from_body("{}", 403, payload), "Not allowed")

    def test_http_error_becomes_api_error(self) -> None:
        from urllib.error import HTTPError

        body = b'{"errors":[{"detail":"Invalid payload"}]}'
        error = HTTPError("https://example.test", 422, "Unprocessable", {}, None)
        error.read = lambda: body  # type: ignore[method-assign]

        client = FlexiClient(TEST_CONFIG)
        with patch("flexicontent_mcp.api.urlopen", side_effect=error):
            with self.assertRaises(FlexiApiError) as ctx:
                client.request("POST", "/items", payload={})

        self.assertEqual(ctx.exception.status, 422)
        self.assertEqual(ctx.exception.message, "Invalid payload")


if __name__ == "__main__":
    unittest.main()
