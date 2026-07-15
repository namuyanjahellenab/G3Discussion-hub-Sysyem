# Database access layer for the ML gateway.
# Reuses the same DB_* variables from Laravel's .env.
# Returns None if the DB is unreachable/query failed, [] if the query succeeded but found nothing.

import logging
import os
from typing import Any

import pymysql
import pymysql.cursors
from pymysql.connections import Connection

from config import DB_CONNECT_TIMEOUT_SECONDS, GROUP_ACTIVITY_WEIGHT, GROUP_SUGGESTION_LIMIT, RECENT_ACTIVITY_LIMIT, TRENDING_WINDOW_DAYS

_logger = logging.getLogger(__name__)


def _db_connect() -> Connection:
    return pymysql.connect(
        host=os.environ.get("DB_HOST", "127.0.0.1"),
        port=int(os.environ.get("DB_PORT", 3306)),
        user=os.environ.get("DB_USERNAME", "root"),
        password=os.environ.get("DB_PASSWORD", ""),
        database=os.environ.get("DB_DATABASE", "discussionhub"),
        cursorclass=pymysql.cursors.DictCursor,
        connect_timeout=DB_CONNECT_TIMEOUT_SECONDS,
    )


def _run_query(sql: str, params: tuple | None = None) -> list[dict[str, Any]] | None:
    # Run a SELECT and return its rows, or None if the DB is unreachable/errors.
    try:
        conn = _db_connect()
    except Exception:
        _logger.warning("ML gateway DB connection failed", exc_info=True)
        return None
    try:
        with conn.cursor() as cursor:
            cursor.execute(sql, params or ())
            return cursor.fetchall()
    except Exception:
        _logger.warning("ML gateway DB query failed", exc_info=True)
        return None
    finally:
        conn.close()


def _fetch_live_categories() -> list[dict[str, Any]] | None:
    # Category -> concatenated real Topic titles, for enriching the catalog.
    return _run_query(
        "SELECT Category, GROUP_CONCAT(Title SEPARATOR ' ') AS Text FROM `Topic` "
        "WHERE Category IS NOT NULL AND Category <> '' GROUP BY Category"
    )


def _fetch_user_recent_text(user_id: Any) -> list[str] | None:
    # A user's own recent Post/Reply text, most-recent-first. None = DB unreachable, [] = no history yet.
    if not user_id:
        return None
    posts = _run_query(
        "SELECT Content AS Text FROM `Post` WHERE UserID = %s AND Content IS NOT NULL AND Content <> '' "
        "ORDER BY CreatedAt DESC LIMIT %s",
        (user_id, RECENT_ACTIVITY_LIMIT),
    )
    if posts is None:
        return None
    replies = _run_query(
        "SELECT ReplyContent AS Text FROM `Reply` WHERE UserID = %s AND ReplyContent IS NOT NULL AND ReplyContent <> '' "
        "ORDER BY CreatedAt DESC LIMIT %s",
        (user_id, RECENT_ACTIVITY_LIMIT),
    )
    if replies is None:
        return None
    return [row["Text"] for row in posts] + [row["Text"] for row in replies]


def _fetch_trending_categories(group_ids: list[Any]) -> list[dict[str, Any]] | None:
    # Categories ranked by recent engagement, scoped to group_ids, falling back to platform-wide.
    base_sql = """
        SELECT t.Category AS Category,
               COUNT(DISTINCT p.PostID) + COUNT(DISTINCT r.ReplyID) AS Engagement
        FROM `Topic` t
        LEFT JOIN `Post` p ON p.TopicID = t.TopicID AND p.CreatedAt >= NOW() - INTERVAL {window} DAY
        LEFT JOIN `Reply` r ON r.PostID = p.PostID AND r.CreatedAt >= NOW() - INTERVAL {window} DAY
        WHERE t.Category IS NOT NULL AND t.Category <> '' {group_filter}
        GROUP BY t.Category
        HAVING Engagement > 0
        ORDER BY Engagement DESC
    """.format(window=TRENDING_WINDOW_DAYS, group_filter="{group_filter}")

    if group_ids:
        placeholders = ",".join(["%s"] * len(group_ids))
        rows = _run_query(base_sql.format(group_filter=f"AND t.GroupID IN ({placeholders})"), tuple(group_ids))
        if rows is None:
            return None
        if rows:
            return rows

    return _run_query(base_sql.format(group_filter=""))


def _fetch_group_suggestions(user_id: Any, exclude_group_ids: list[Any]) -> list[dict[str, Any]] | None:
    # Groups the user hasn't joined, ranked by member count + recent activity.
    exclude_ids = {gid for gid in (exclude_group_ids or []) if gid is not None}
    if not exclude_ids and user_id:
        joined = _run_query("SELECT GroupID FROM `groupstudent` WHERE UserID = %s", (user_id,))
        if joined is None:
            return None
        exclude_ids = {row["GroupID"] for row in joined}

    base_sql = """
        SELECT g.GroupID AS GroupID, g.GroupName AS GroupName,
               COUNT(DISTINCT gs.StudentID) AS MemberCount,
               COUNT(DISTINCT p.PostID) + COUNT(DISTINCT r.ReplyID) AS RecentActivity
        FROM `Group` g
        LEFT JOIN `groupstudent` gs ON gs.GroupID = g.GroupID
        LEFT JOIN `Topic` t ON t.GroupID = g.GroupID
        LEFT JOIN `Post` p ON p.TopicID = t.TopicID AND p.CreatedAt >= NOW() - INTERVAL {window} DAY
        LEFT JOIN `Reply` r ON r.PostID = p.PostID AND r.CreatedAt >= NOW() - INTERVAL {window} DAY
        {exclude_filter}
        GROUP BY g.GroupID, g.GroupName
        ORDER BY (COUNT(DISTINCT gs.StudentID) + {weight} * (COUNT(DISTINCT p.PostID) + COUNT(DISTINCT r.ReplyID))) DESC
        LIMIT {limit}
    """.format(
        window=TRENDING_WINDOW_DAYS,
        weight=GROUP_ACTIVITY_WEIGHT,
        limit=GROUP_SUGGESTION_LIMIT,
        exclude_filter="{exclude_filter}",
    )

    if exclude_ids:
        placeholders = ",".join(["%s"] * len(exclude_ids))
        return _run_query(base_sql.format(exclude_filter=f"WHERE g.GroupID NOT IN ({placeholders})"), tuple(exclude_ids))
    return _run_query(base_sql.format(exclude_filter=""))


def _fetch_topic_export_data(topic_id: Any) -> dict[str, Any] | None:
    # Topic + its posts (each with nested replies), for PDF export. None = DB unreachable.
    topic_rows = _run_query(
        "SELECT t.TopicID, t.Title, g.GroupName FROM `Topic` t "
        "LEFT JOIN `Group` g ON g.GroupID = t.GroupID WHERE t.TopicID = %s",
        (topic_id,),
    )
    if topic_rows is None:
        return None
    if not topic_rows:
        return {"topic": None, "posts": []}

    posts = _run_query(
        "SELECT p.PostID, p.Content, p.Attachment, u.UserName AS AuthorName "
        "FROM `Post` p LEFT JOIN `User` u ON u.UserID = p.UserID "
        "WHERE p.TopicID = %s ORDER BY p.CreatedAt",
        (topic_id,),
    )
    if posts is None:
        return None

    post_ids = [post["PostID"] for post in posts]
    replies_by_post: dict[Any, list[dict[str, Any]]] = {post_id: [] for post_id in post_ids}
    if post_ids:
        placeholders = ",".join(["%s"] * len(post_ids))
        replies = _run_query(
            "SELECT r.PostID, r.ReplyContent, u.UserName AS AuthorName "
            "FROM `Reply` r LEFT JOIN `User` u ON u.UserID = r.UserID "
            f"WHERE r.PostID IN ({placeholders}) ORDER BY r.CreatedAt",
            tuple(post_ids),
        )
        if replies is None:
            return None
        for reply in replies:
            replies_by_post[reply["PostID"]].append(reply)

    for post in posts:
        post["Replies"] = replies_by_post[post["PostID"]]

    return {"topic": topic_rows[0], "posts": posts}


def _fetch_topic_share_data(topic_id: Any) -> dict[str, Any] | None:
    # Topic title + reply count, for building a social-share snippet. None = DB unreachable.
    topic_rows = _run_query("SELECT Title FROM `Topic` WHERE TopicID = %s", (topic_id,))
    if topic_rows is None:
        return None
    if not topic_rows:
        return {"title": None, "reply_count": 0}

    counts = _run_query(
        "SELECT "
        "(SELECT COUNT(*) FROM `Post` WHERE TopicID = %s) AS PostCount, "
        "(SELECT COUNT(*) FROM `Reply` r JOIN `Post` p ON p.PostID = r.PostID WHERE p.TopicID = %s) AS ReplyCount",
        (topic_id, topic_id),
    )
    if counts is None:
        return None

    row = counts[0] if counts else {"PostCount": 0, "ReplyCount": 0}
    reply_count = max(0, (row["PostCount"] or 0) - 1) + (row["ReplyCount"] or 0)
    return {"title": topic_rows[0]["Title"], "reply_count": reply_count}
