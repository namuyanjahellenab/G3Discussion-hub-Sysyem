# PDF export for a topic + its posts/replies, generated entirely in Python (xhtml2pdf).

import base64
import io
import logging
import os
import re
from datetime import datetime
from typing import Any

from flask import render_template_string
from xhtml2pdf import pisa

_logger = logging.getLogger(__name__)

# Laravel's public storage root (storage/app/public) - python/ and storage/
# are sibling directories under the same project root, and both processes
# share this same disk (confirmed by db.py connecting to the same DB via
# 127.0.0.1 by default), so reading the file straight off disk and inlining
# it as a base64 data URI is simpler and more robust than having Python make
# an HTTP round-trip back to the Laravel app it was called from - no need
# for the app's base URL, no auth, no risk of the web server being busy.
_STORAGE_ROOT = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "storage", "app", "public")

_MIME_TYPES = {
    "png": "image/png",
    "jpg": "image/jpeg",
    "jpeg": "image/jpeg",
    "gif": "image/gif",
    "webp": "image/webp",
}


def _display_attachment_name(attachment_path: str) -> str:
    # Mirrors AttachmentUploader::displayName() (PHP) - strips the
    # "{uniqid}--" collision-avoidance prefix so the PDF shows the same
    # clean original filename the web/desktop apps already show.
    base = attachment_path.rsplit("/", 1)[-1]
    return base.split("--", 1)[-1] if "--" in base else base


def _attachment_image_data_uri(attachment_path: str, attachment_type: str | None) -> str | None:
    # Returns a data: URI xhtml2pdf can render inline, or None if this
    # attachment isn't an embeddable image or the file couldn't be read -
    # callers fall back to a plain text mention in either case, so a single
    # missing/corrupt file never breaks the whole export.
    if attachment_type != "image":
        return None

    ext = attachment_path.rsplit(".", 1)[-1].lower() if "." in attachment_path else ""
    mime = _MIME_TYPES.get(ext)
    if mime is None:
        return None

    full_path = os.path.join(_STORAGE_ROOT, *attachment_path.split("/"))
    try:
        with open(full_path, "rb") as f:
            encoded = base64.b64encode(f.read()).decode("ascii")
        return f"data:{mime};base64,{encoded}"
    except OSError as e:
        _logger.warning("PDF export: could not read attachment %s: %s", attachment_path, e)
        return None


def _attach_display_fields(item: dict[str, Any]) -> None:
    # Enriches a post/reply dict in place with the two fields the template
    # actually renders, so the template itself never touches the filesystem.
    if not item.get("Attachment"):
        return
    item["AttachmentName"] = _display_attachment_name(item["Attachment"])
    item["AttachmentImageDataUri"] = _attachment_image_data_uri(item["Attachment"], item.get("AttachmentType"))


_TOPIC_PDF_TEMPLATE = """
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #222; }
    .header { border-bottom: 2px solid #0052CC; padding-bottom: 8px; margin-bottom: 4px; }
    .header h2 { margin: 0 0 4px 0; color: #0052CC; }
    .header .meta { color: #666; font-size: 10px; margin-bottom: 2px; }
    .post { border: 1px solid #ddd; border-radius: 4px; padding: 10px; margin-top: 14px; }
    .msg-header { margin-bottom: 6px; }
    .avatar { display: inline-block; width: 20px; height: 20px; border-radius: 10px;
              background-color: #0052CC; color: #ffffff; text-align: center;
              font-size: 10px; font-weight: bold; line-height: 20px; }
    .author-name { font-weight: bold; color: #111; font-size: 12px; }
    .badge-op { color: #0052CC; font-size: 9px; font-weight: bold; margin-left: 6px; }
    .timestamp { color: #999; font-size: 9px; margin-left: 6px; }
    .content { margin-top: 6px; white-space: pre-wrap; line-height: 1.5; }
    .attachment { margin-top: 6px; color: #666; font-size: 10px; }
    .attachment-image { margin-top: 8px; max-width: 320px; max-height: 260px; border: 1px solid #ddd; border-radius: 4px; }
    .reply { margin-top: 10px; margin-left: 20px; padding: 8px 10px; background: #f5f5f5; border-radius: 4px; }
    .reply .author-name { font-size: 11px; }
    .reply .avatar { width: 16px; height: 16px; border-radius: 8px; line-height: 16px;
                      font-size: 9px; background-color: #6b7280; }
</style>
</head>
<body>
    <div class="header">
        <h2>{{ topic.Title or "Discussion Export" }}</h2>
        <div class="meta">Group: {{ topic.GroupName or "Discussion Hub" }}</div>
        <div class="meta">Generated: {{ generated_at }}</div>
    </div>
    {% for post in posts %}
    <div class="post">
        <div class="msg-header">
            <span class="avatar">{{ (post.AuthorName or "S")[0]|upper }}</span>
            <span class="author-name">{{ post.AuthorName or "Student" }}</span>
            <span class="badge-op">ORIGINAL POST</span>
            {% if post.PostedAt %}<span class="timestamp">{{ post.PostedAt }}</span>{% endif %}
        </div>
        <div class="content">{{ post.Content or "" }}</div>
        {% if post.Attachment %}
            {% if post.AttachmentImageDataUri %}
            <img class="attachment-image" src="{{ post.AttachmentImageDataUri }}">
            {% else %}
            <div class="attachment">Attachment: {{ post.AttachmentName }}</div>
            {% endif %}
        {% endif %}
        {% for reply in post.Replies %}
        <div class="reply">
            <div class="msg-header">
                <span class="avatar">{{ (reply.AuthorName or "S")[0]|upper }}</span>
                <span class="author-name">{{ reply.AuthorName or "Student" }}</span>
                {% if reply.PostedAt %}<span class="timestamp">{{ reply.PostedAt }}</span>{% endif %}
            </div>
            <div class="content">{{ reply.ReplyContent or "" }}</div>
            {% if reply.Attachment %}
                {% if reply.AttachmentImageDataUri %}
                <img class="attachment-image" src="{{ reply.AttachmentImageDataUri }}">
                {% else %}
                <div class="attachment">Attachment: {{ reply.AttachmentName }}</div>
                {% endif %}
            {% endif %}
        </div>
        {% endfor %}
    </div>
    {% endfor %}
</body>
</html>
"""


def _slugify(text: str) -> str:
    # Turns a topic title into a safe PDF filename fragment, e.g.
    # "Merge Sort Help!" -> "merge-sort-help". Falls back to "discussion"
    # if nothing alphanumeric survives (e.g. an emoji-only title).
    slug = re.sub(r"[^a-z0-9]+", "-", text.lower()).strip("-")
    return slug or "discussion"


def _build_topic_pdf(topic: dict[str, Any], posts: list[dict[str, Any]]) -> bytes | None:
    # Render the topic + its posts/replies to PDF bytes, or None if generation failed.
    for post in posts:
        _attach_display_fields(post)
        for reply in post.get("Replies", []):
            _attach_display_fields(reply)

    html = render_template_string(
        _TOPIC_PDF_TEMPLATE,
        topic=topic,
        posts=posts,
        generated_at=datetime.now().strftime("%Y-%m-%d %H:%M"),
    )

    buffer = io.BytesIO()
    result = pisa.CreatePDF(html, dest=buffer)
    if result.err:
        return None
    return buffer.getvalue()
