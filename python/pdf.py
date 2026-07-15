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
    .header h2 { margin-bottom: 2px; }
    .header .meta { color: #666; font-size: 10px; margin-bottom: 2px; }
    .post { border: 1px solid #ddd; border-radius: 4px; padding: 10px; margin-top: 12px; }
    .post .author { font-weight: bold; }
    .post .content { margin-top: 4px; white-space: pre-wrap; }
    .post .attachment { margin-top: 4px; color: #666; font-size: 10px; }
    .reply { margin-top: 8px; margin-left: 20px; padding: 6px 10px; background: #f5f5f5; border-radius: 4px; }
    .reply .author { font-weight: bold; font-size: 11px; }
    .reply .content { margin-top: 2px; white-space: pre-wrap; }
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
        <div class="author">{{ post.AuthorName or "Student" }}</div>
        <div class="content">{{ post.Content or "" }}</div>
        {% if post.Attachment %}
        <div class="attachment">Attachment: {{ post.Attachment.split("/")[-1] }}</div>
        {% endif %}
        {% for reply in post.Replies %}
        <div class="reply">
            <div class="author">{{ reply.AuthorName or "Student" }}</div>
            <div class="content">{{ reply.ReplyContent or "" }}</div>
        </div>
        {% endfor %}
    </div>
    {% endfor %}
</body>
</html>
"""


def _slugify(text: str) -> str:
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
