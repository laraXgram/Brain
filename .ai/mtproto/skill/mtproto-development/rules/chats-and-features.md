# MTProto Chats, Channels and Account Features

All methods below are available on `$request` in handlers and controllers, and on `Client::session('name')` elsewhere. Account-changing actions (joining, leaving, banning, profile edits, gifts) act on a real account: only perform them when the user asked for that behavior.

## Reading Chats

```php
$full = $request->getFullChat(peer: '@laragram');
$member = $request->getChatMember(peer: '@laragram', user: '@durov');

foreach ($request->iterateDialogs() as $dialog) { /* ... */ }
foreach ($request->iterateHistory(peer: '@laragram') as $message) { /* ... */ }
foreach ($request->iterateParticipants(peer: '@laragram') as $participant) { /* ... */ }

$page = $request->getParticipants(peer: '@laragram', limit: 100);
```

- The `iterate*` methods are generators that page transparently; stop early (`break`) once you have what you need instead of walking a whole history.
- Iterating is still rate limited. Run long exports in a queued job or a command, never inside a webhook request, and prefer `client:export` / takeout for archiving (below).

## Creating and Managing Chats

```php
$request->createSupergroup(title: 'Community', about: 'Say hi');
$request->createChannel(title: 'Announcements', about: 'Official updates');

$request->banChatMember(peer: '@group', user: '@spammer', until: time() + 3600);
$request->unbanChatMember(peer: '@group', user: 123);
$request->kickChatMember(peer: '@group', user: 123);
$request->restrictChatMember(peer: '@group', user: 123, restrictions: ['send_media' => true], until: time() + 86400);

$request->promoteChatMember(peer: '@group', user: '@moderator', rights: ['delete_messages' => true, 'ban_users' => true], rank: 'Moderator');
$request->demoteChatMember(peer: '@group', user: '@moderator');

$request->setChatTitle(peer: '@group', title: 'New Title');
$request->setChatDescription(peer: '@group', about: 'A fresh description.');
$request->setChatPhoto(peer: '@group', path: storage_path('app/group.jpg'));
```

Also available: `addChatMembers`, `joinChat`, `leaveChat`, `deleteChat`, `blockUser` / `unblockUser`.

## Invite Links, Pins and Forum Topics

```php
$link = $request->exportInviteLink(peer: '@group', params: ['usage_limit' => 10, 'expire_date' => time() + 3600]);
$request->revokeInviteLink(peer: '@group', link: $link['link']);

$request->pinMessage(peer: '@group', id: 500, silent: true);
$request->unpinAllMessages(peer: '@group');

$request->toggleForum(peer: '@group', enabled: true);
$topic = $request->createForumTopic(peer: '@group', title: 'Feature Requests');
$request->sendMessage(peer: '@group', message: 'Posted in a topic', top_msg_id: $topic['id']);
```

Topic management: `editForumTopic`, `closeForumTopic`, `hideForumTopic`, `pinForumTopic`, `deleteForumTopic`, `getForumTopics`. Communities: `createCommunity`, `getJoinedCommunities`, ....

## Profile and Contacts

`updateProfile(params: [...])`, `setUsername`, `setProfilePhoto(path:)`, `deleteProfilePhotos`, `setEmojiStatus`, `setOnline`, `getContacts`, `addContact`, `deleteContacts`. Boosts: `getBoostsStatus(peer:)`, `getMyBoosts()`, `getUserBoosts(peer:, user:)`.

## Reactions, Polls, Drafts and Checklists

```php
$request->sendReaction(peer: $chatId, msgId: 500, reaction: '🔥');
$request->sendReaction(peer: $chatId, msgId: 500, reaction: null); // remove
$request->voteInPoll(peer: $chatId, msgId: 500, options: 0);
$request->sendChecklist(peer: $chatId, title: 'Release', items: ['Write docs', 'Tag release']);
$request->saveDraft(peer: $chatId, message: 'Unsent thought');
```

## Secret Chats

```php
$chat = $request->startSecretChat(userId: 123456789);
$request->sendSecretMessage(chatId: $chat['id'], text: 'End-to-end encrypted', ttl: 30);

Client::onEncryption(fn (ClientRequest $request) => $request->client()->handleSecretEncryption($request->toArray()));

Client::onEncryptedMessage(function (ClientRequest $request) {
    $decrypted = $request->client()->decryptSecret($request->toArray());
});
```

Secret chats are bound to the session that created them; deleting or re-authenticating the session loses them. Never log decrypted content.

## Takeout and Export

`php laragram client:export --session=default --peer=@saved --limit=1000 --into=storage/exports` exports data through Telegram's takeout API. In code, wrap bulk reads in `$request->takeout(function ($takeoutId) use ($request) { foreach ($request->iterateTakeoutHistory($takeoutId, peer: '@group') as $message) { ... } })` so they count against the export budget instead of normal limits.

## Stars, Gifts and Paid Features

`getStarsStatus(peer: 'me')`, `getStarGifts()`, `saveStarGift`, `convertStarGift`, `transferStarGift(gift:, to:)`, and `sendPaidReaction(peer:, msgId:, count:)`. These spend or move real value: never call them without explicit user intent.

## Bot Controls and Business Messages

For bot sessions: `setBotCommands(commands: [...])`, `setBotMenuButton`, `setBotInfo(params: [...])`, `setDefaultAdminRights`. For Telegram Business connections, handle `Client::onBusinessMessage` and `Client::onBusinessConnect`. Ephemeral messages: `sendEphemeral`, `editEphemeral`, `deleteEphemeral`.
