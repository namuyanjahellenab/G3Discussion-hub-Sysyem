package com.discussionhub.client.utils;

// Avatar-circle initials shared by every screen: first letter of the first
// name + first letter of the last name (e.g. "Muyingo Star" -> "MS"), not
// just the first word - matches Str::initials() on the web side exactly.
public final class TextUtil {

    private TextUtil() {
    }

    public static String initials(String name) {
        if (name == null) return "?";
        String[] parts = name.trim().split("\\s+");
        if (parts.length == 0 || parts[0].isEmpty()) return "?";

        StringBuilder sb = new StringBuilder();
        sb.append(Character.toUpperCase(parts[0].charAt(0)));
        for (int i = parts.length - 1; i > 0; i--) {
            if (!parts[i].isEmpty()) {
                sb.append(Character.toUpperCase(parts[i].charAt(0)));
                break;
            }
        }
        return sb.toString();
    }

    /** "2 days ago" / "3 hours ago" / "just now" - matches Laravel's
     *  diffForHumans() style already seen everywhere online (topic list's
     *  "last activity X ago", notifications, etc). The offline topic list
     *  cache only has a raw CreatedAt column, so this is what recovers that
     *  same look from it instead of showing the raw ISO timestamp as-is. */
    public static String timeAgo(String isoTimestamp) {
        if (isoTimestamp == null || isoTimestamp.isBlank()) return "";
        try {
            java.time.Instant then = java.time.Instant.parse(isoTimestamp);
            long seconds = Math.max(0, java.time.Duration.between(then, java.time.Instant.now()).getSeconds());

            if (seconds < 60) return "just now";
            long minutes = seconds / 60;
            if (minutes < 60) return minutes + (minutes == 1 ? " minute ago" : " minutes ago");
            long hours = minutes / 60;
            if (hours < 24) return hours + (hours == 1 ? " hour ago" : " hours ago");
            long days = hours / 24;
            if (days < 7) return days + (days == 1 ? " day ago" : " days ago");
            long weeks = days / 7;
            if (weeks < 5) return weeks + (weeks == 1 ? " week ago" : " weeks ago");
            long months = days / 30;
            if (months < 12) return months + (months == 1 ? " month ago" : " months ago");
            long years = days / 365;
            return years + (years == 1 ? " year ago" : " years ago");
        } catch (Exception e) {
            return isoTimestamp;
        }
    }
}
