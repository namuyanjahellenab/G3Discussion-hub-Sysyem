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
}
