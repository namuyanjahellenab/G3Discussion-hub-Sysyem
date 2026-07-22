# Builds social-share URLs and text for a topic.
# Flask owns this so Laravel/Blade never has to construct share URLs itself.

from typing import Any
from urllib.parse import quote


def _build_share_links(topic_id: Any, title: str, reply_count: int, base_url: str) -> dict[str, Any]:
    # Build a canonical share URL, a pre-filled text snippet, and ready-to-use links.
    share_url = f"{base_url}/topics/{topic_id}"
    reply_word = "reply" if reply_count == 1 else "replies"
    share_text = f'Check out "{title}" ({reply_count} {reply_word}) on Discussion Hub'

    encoded_text = quote(share_text)
    encoded_url = quote(share_url, safe="")

    return {
        "TopicID": topic_id,
        "ShareUrl": share_url,
        "ShareText": share_text,
        "Links": {
            "whatsapp": f"https://wa.me/?text={quote(share_text + ' ' + share_url)}",
            "twitter": f"https://twitter.com/intent/tweet?text={encoded_text}&url={encoded_url}",
            "facebook": f"https://www.facebook.com/sharer/sharer.php?u={encoded_url}",
            "email": f"mailto:?subject={quote(title)}&body={quote(share_text + ' ' + share_url)}",
        },
    }
