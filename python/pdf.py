# PDF export for a topic + its posts/replies, generated entirely in Python (xhtml2pdf).

import io
import re
from datetime import datetime
from typing import Any

from flask import render_template_string
from xhtml2pdf import pisa

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
        <div class="attachment">Attachment: {{ post.Attachment.split("/")[-1] }}</div>
        {% endif %}
        {% for reply in post.Replies %}
        <div class="reply">
            <div class="msg-header">
                <span class="avatar">{{ (reply.AuthorName or "S")[0]|upper }}</span>
                <span class="author-name">{{ reply.AuthorName or "Student" }}</span>
                {% if reply.PostedAt %}<span class="timestamp">{{ reply.PostedAt }}</span>{% endif %}
            </div>
            <div class="content">{{ reply.ReplyContent or "" }}</div>
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
