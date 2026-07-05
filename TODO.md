# TODO - Messages page WhatsApp-like behavior

## Milestone 1: AJAX send + JSON response
- [x] Update `resources/views/messages/index.blade.php` submit handler to use Fetch/FormData and remove fake/local message append.
- [ ] Update `DiscussionHubPageController@storeMessage` to return JSON (no redirect) with HTML (or enough data for the frontend) for the inserted Post.


## Milestone 2: Polling updates
- [ ] Add endpoint to fetch messages newer than a given PostID/timestamp.
- [ ] Add JS polling every 3s; dedupe by PostID; append only missing messages.

## Milestone 3: Edge cases / UX
- [ ] Validation + upload error rendering in the UI without reload.
- [ ] Ensure reply banner disappears after successful send.
- [ ] Clear attached file selection + preview after successful send.

