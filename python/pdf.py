# PDF export for a topic + its posts/replies, generated entirely in Python -
# rendered by headless Chromium via Playwright (the same engine/print
# pipeline a real browser's "Print to PDF" uses), for far more accurate
# CSS/layout fidelity than a non-browser HTML-to-PDF converter gives.

import base64
import io
import logging
import os
import re
from datetime import datetime
from typing import Any

from flask import render_template
from PIL import Image
from playwright.sync_api import sync_playwright

_logger = logging.getLogger(__name__)

# Laravel's public storage root - python/ and storage/ are sibling
# directories sharing the same disk, so images are read straight off disk
# and inlined as base64 rather than fetched back over HTTP from Laravel.
_STORAGE_ROOT = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "storage", "app", "public")

_MIME_TYPES = {
    "png": "image/png",
    "jpg": "image/jpeg",
    "jpeg": "image/jpeg",
    "gif": "image/gif",
    "webp": "image/webp",
}

# The PDF only ever displays an embedded image at up to 320x260 (see the
# .attachment-image CSS rule below) - embedding the original, possibly
# multi-megabyte upload at full resolution just bloats the PDF for no
# visual benefit. Downscaled + re-compressed the same way
# AttachmentUploader.php already shrinks images on upload, just done again
# here in case an old attachment predates that (or came in larger).
_PDF_IMAGE_MAX_DIMENSION = 480
_PDF_IMAGE_JPEG_QUALITY = 70


def _display_attachment_name(attachment_path: str) -> str:
    # Mirrors AttachmentUploader::displayName() (PHP) - strips the
    # "{uniqid}--" collision-avoidance prefix so the PDF shows the same
    # clean original filename the web/desktop apps already show.
    base = attachment_path.rsplit("/", 1)[-1]
    return base.split("--", 1)[-1] if "--" in base else base


def _attachment_image_data_uri(attachment_path: str, attachment_type: str | None) -> str | None:
    # Returns a data: URI the PDF can render inline, or None if this
    # attachment isn't an embeddable image or the file couldn't be read -
    # callers fall back to a plain text mention in either case, so a single
    # missing/corrupt file never breaks the whole export.
    if attachment_type != "image":
        return None

    ext = attachment_path.rsplit(".", 1)[-1].lower() if "." in attachment_path else ""
    if ext not in _MIME_TYPES:
        return None

    full_path = os.path.join(_STORAGE_ROOT, *attachment_path.split("/"))
    try:
        with Image.open(full_path) as image:
            image.load()  # force the read now, inside the try, before resize/convert touch it

            long_edge = max(image.width, image.height)
            if long_edge > _PDF_IMAGE_MAX_DIMENSION:
                scale = _PDF_IMAGE_MAX_DIMENSION / long_edge
                image = image.resize(
                    (round(image.width * scale), round(image.height * scale)),
                    Image.LANCZOS,
                )

            # Always re-encoded as JPEG, same as AttachmentUploader.php's own
            # resize step - one predictable, small, well-compressed format
            # regardless of what was uploaded, instead of carrying PNG/GIF/
            # WebP's usually-larger encoding into the PDF for no visual gain.
            if image.mode not in ("RGB", "L"):
                flattened = Image.new("RGB", image.size, "#FFFFFF")
                flattened.paste(image, mask=image.split()[-1] if image.mode == "RGBA" else None)
                image = flattened

            buffer = io.BytesIO()
            image.save(buffer, format="JPEG", quality=_PDF_IMAGE_JPEG_QUALITY, optimize=True)
            encoded = base64.b64encode(buffer.getvalue()).decode("ascii")
        return f"data:image/jpeg;base64,{encoded}"
    except (OSError, ValueError) as e:
        _logger.warning("PDF export: could not read/resize attachment %s: %s", attachment_path, e)
        return None


def _attach_display_fields(item: dict[str, Any]) -> None:
    # Enriches a post/reply dict in place with the two fields the template
    # actually renders, so the template itself never touches the filesystem.
    if not item.get("Attachment"):
        return
    item["AttachmentName"] = _display_attachment_name(item["Attachment"])
    item["AttachmentImageDataUri"] = _attachment_image_data_uri(item["Attachment"], item.get("AttachmentType"))


def _slugify(text: str) -> str:
    # Turns a topic title into a safe PDF filename fragment, e.g.
    # "Merge Sort Help!" -> "merge-sort-help". Falls back to "discussion"
    # if nothing alphanumeric survives (e.g. an emoji-only title).
    slug = re.sub(r"[^a-z0-9]+", "-", text.lower()).strip("-")
    return slug or "discussion"


def _build_topic_pdf(topic: dict[str, Any], posts: list[dict[str, Any]]) -> bytes | None:
    for post in posts:
        _attach_display_fields(post)
        for reply in post.get("Replies", []):
            _attach_display_fields(reply)

    html = render_template(
        "topic_export.html",
        topic=topic,
        posts=posts,
        generated_at=datetime.now().strftime("%Y-%m-%d %H:%M"),
    )

    # Fresh, disposable headless Chromium per export, printed to PDF the same
    # way Chrome's own "Print to PDF" would - the inner try/finally keeps a
    # failed export from ever leaving an orphaned Chromium process behind.
    try:
        with sync_playwright() as p:
            browser = p.chromium.launch()
            try:
                page = browser.new_page()
                page.set_content(html, wait_until="load")
                pdf_bytes = page.pdf(
                    format="A4",
                    margin={"top": "1.6cm", "bottom": "1.6cm", "left": "1.6cm", "right": "1.6cm"},
                    print_background=True,  # otherwise Chromium's print mode drops all background colors/borders
                )
            finally:
                browser.close()
        return pdf_bytes
    except Exception as e:
        # Broad on purpose - a launch failure or malformed page shouldn't
        # crash the Flask worker over one bad export.
        _logger.warning("PDF export: Chromium rendering failed: %s", e)
        return None
