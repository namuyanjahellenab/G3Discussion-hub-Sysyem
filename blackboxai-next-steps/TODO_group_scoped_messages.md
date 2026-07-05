# Next steps: Group-scoped Messages (WhatsApp-like)

## Why this is needed
- Current DB schema does **not** store `GroupID` on `Topic`.
- Message/thread filtering in `DiscussionHubPageController@messages` therefore cannot reliably persist messages per-group across sidebar navigation.

## Implement these changes
1. **Migration**: add `GroupID` to `Topic` table.
2. **Topic model**: update relationships if needed.
3. **Topic creation**: ensure `GroupID` is set when creating topics.
4. **Controller**: in `DiscussionHubPageController@messages`:
   - filter posts via `post.topic.group_id` (or `topic.GroupID = group_id`)
   - enforce that selected `group_id` belongs to the authenticated user (`GroupStudent`).
5. **Message creation**: in `storeMessage`:
   - require/validate that `topic_id` belongs to `group_id` when `group_id` is provided.
6. **Replies**:
   - verify reply loading so UI can display “who replied”.
7. **UI**:
   - ensure the “Messages” page keeps the selected `group_id/topic_id` in query params across navigation.

## Completion checklist
- [ ] After sending to Algorithms, leaving to Networks, and returning, Algorithms messages still show.
- [ ] After sending to Networks, leaving to Algorithms, and returning, Networks messages still show.
- [ ] Reply bubbles correctly show reply author.

