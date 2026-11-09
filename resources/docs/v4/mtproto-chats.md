# MTProto: Chats & Channels

- [Introduction](#introduction)
- [Reading Chats](#reading-chats)
    - [Chat & Member Info](#chat-member-info)
    - [Iterating Dialogs, History & Participants](#iterating)
- [Creating Chats](#creating-chats)
- [Managing Members](#managing-members)
    - [Banning & Restricting](#banning-restricting)
    - [Admins](#admins)
    - [Adding & Removing](#adding-removing)
- [Chat Settings](#chat-settings)
- [Invite Links](#invite-links)
- [Pinning Messages](#pinning-messages)
- [Forum Topics](#forum-topics)
- [Communities](#communities)
- [Your Profile](#your-profile)
- [Boosts](#boosts)

<a name="introduction"></a>
## Introduction

MTProto exposes the full account surface for managing groups, supergroups, and channels — far beyond what the Bot API allows. Every method here is called on the `ClientRequest` handed to your [listeners](/v4/mtproto-listening) and controllers, and each accepts [peer values](/v4/mtproto-requests#addressing-peers) in the convenient forms (`'@username'`, id, `'me'`, or a peer object). Replies route back to the originating session automatically.

Inside a listener closure:

```php
use LaraGram\MTProto\Facades\Client;
use LaraGram\MTProto\Foundation\ClientRequest;

Client::onCommand('ban', function (ClientRequest $request) {
    $request->banChatMember(peer: $request->chatId(), user: $request->message->from_id->user_id);
});
```

Or a controller method — bind it with `Client::onCommand('ban', [GroupController::class, 'ban'])`:

```php
use LaraGram\MTProto\Foundation\ClientRequest;

class GroupController
{
    public function ban(ClientRequest $request)
    {
        $request->banChatMember(peer: $request->chatId(), user: $request->message->from_id->user_id);
    }
}
```

The examples below use `$request` — the object your handler or controller receives.

<a name="reading-chats"></a>
## Reading Chats

<a name="chat-member-info"></a>
### Chat & Member Info

```php
$full   = $request->getFullChat(peer: '@laragram');           // Full chat/channel info
$member = $request->getChatMember(peer: '@laragram', user: '@durov');
```

<a name="iterating"></a>
### Iterating Dialogs, History & Participants

Large collections are exposed as memory-friendly generators that page through Telegram transparently, so you can `foreach` over an entire chat list, history, or member roster without managing offsets:

```php
// Every dialog (chat) the account is in
foreach ($request->iterateDialogs() as $dialog) {
    // ...
}

// A chat's full message history
foreach ($request->iterateHistory(peer: '@laragram') as $message) {
    // ...
}

// A chat's participants
foreach ($request->iterateParticipants(peer: '@laragram') as $participant) {
    // ...
}
```

For a single page instead of a generator, use `getParticipants(peer: ..., limit: ...)`. Basic groups support the `recent` and `admins` filters.

The same style covers the rest of a chat's collections:

```php
$request->iterateJoinRequests(peer: '@group');                  // Pending join requests
$request->iterateForumTopics(peer: '@group');                   // Forum topics
$request->iterateAdminLog(peer: '@group', query: 'ban');        // Recent admin actions
$request->iterateCommonChats(user: '@alice');                   // Chats you share with a user
$request->iterateBlockedUsers();                                // This account's block list
```

Every iterator accepts `limit:` and `pageSize:`.

<a name="creating-chats"></a>
## Creating Chats

```php
$request->createGroup(title: 'Project Team', users: ['@alice', '@bob']);
$request->createSupergroup(title: 'Community', about: 'Say hi 👋');
$request->createChannel(title: 'Announcements', about: 'Official updates');
```

<a name="managing-members"></a>
## Managing Members

<a name="banning-restricting"></a>
### Banning & Restricting

```php
$request->banChatMember(peer: '@group', user: '@spammer');
$request->banChatMember(peer: '@group', user: 123, until: time() + 3600); // 1-hour ban
$request->unbanChatMember(peer: '@group', user: 123);
$request->kickChatMember(peer: '@group', user: 123);   // Remove without a permanent ban

$request->restrictChatMember(
    peer: '@group',
    user: 123,
    restrictions: ['send_media' => true, 'send_polls' => true],
    until: time() + 86400,
);
```

<a name="admins"></a>
### Admins

```php
$request->promoteChatMember(
    peer: '@group',
    user: '@moderator',
    rights: ['delete_messages' => true, 'ban_users' => true],
    rank: 'Moderator',
);

$request->demoteChatMember(peer: '@group', user: '@moderator');
```

<a name="adding-removing"></a>
### Adding & Removing

```php
$request->addChatMembers(peer: '@group', users: ['@alice', '@bob']);
$request->joinChat(peer: '@laragram');
$request->leaveChat(peer: '@laragram');
$request->deleteChat(peer: '@oldgroup');                       // Basic groups, supergroups and channels

$request->setChatMemberRank(peer: '@group', user: '@alice', rank: 'Mentor');
$request->deleteChatMemberMessages(peer: '@group', user: '@spammer');
$request->convertToSupergroup(peer: '@basicgroup');
```

<a name="chat-settings"></a>
## Chat Settings

```php
$request->setChatTitle(peer: '@group', title: 'New Title');
$request->setChatDescription(peer: '@group', about: 'A fresh description.');
$request->setChatPhoto(peer: '@group', path: '/path/to/photo.jpg');
$request->deleteChatPhoto(peer: '@group');
$request->blockUser(peer: '@spammer');
$request->unblockUser(peer: '@spammer');

// What members may NOT do by default (the same names as restrictChatMember)
$request->setChatPermissions(peer: '@group', restrictions: ['send_media', 'send_polls']);
$request->setSlowMode(peer: '@group', seconds: 30);       // 0 turns slow mode off
$request->setChatProtectedContent(peer: '@channel');     // no forwarding or saving

// Hand a group or channel you own to someone else (needs your 2FA password)
$request->transferChatOwnership(peer: '@channel', user: '@heir', password: 'my 2FA password');
```

Archive and mute chats, or get the public link of a supergroup or channel message:

```php
$request->archiveChats(peers: ['@news', '@offtopic']);
$request->unarchiveChats(peers: '@news');

$request->muteChat(peer: '@noisy');                       // forever
$request->muteChat(peer: '@noisy', until: now()->addHours(8)->timestamp);
$request->unmuteChat(peer: '@noisy');

$link = $request->getMessageLink(peer: '@channel', msgId: 42); // https://t.me/channel/42
```

<a name="invite-links"></a>
## Invite Links

```php
$link = $request->exportInviteLink(peer: '@group');                       // Primary link
$temp = $request->exportInviteLink(peer: '@group', params: ['usage_limit' => 10, 'expire_date' => time() + 3600]);

$links = $request->getInviteLinks(peer: '@group');
$request->editInviteLink(peer: '@group', link: $temp['link'], params: ['usage_limit' => 20]);
$request->revokeInviteLink(peer: '@group', link: $temp['link']);
$request->deleteInviteLink(peer: '@group', link: $temp['link']);

$preview = $request->checkInviteLink('https://t.me/+AbCdEf');   // Inspect a chat without joining
```

Handle requests to join a chat that requires approval:

```php
foreach ($request->iterateJoinRequests(peer: '@group') as $joinRequest) {
    $request->approveJoinRequest(peer: '@group', user: $joinRequest['user_id']);
}

$request->declineJoinRequest(peer: '@group', user: '@spammer');
$request->approveAllJoinRequests(peer: '@group');
$request->declineAllJoinRequests(peer: '@group', link: $temp['link']);  // Only requests from this link
```

<a name="pinning-messages"></a>
## Pinning Messages

```php
$request->pinMessage(peer: '@group', id: 500, silent: true);
$request->unpinMessage(peer: '@group', id: 500);
$request->unpinAllMessages(peer: '@group');
```

<a name="forum-topics"></a>
## Forum Topics

Supergroups with topics enabled ("forums") are fully supported:

```php
$request->toggleForum(peer: '@group', enabled: true);

$topic = $request->createForumTopic(peer: '@group', title: 'Feature Requests');
$request->editForumTopic(peer: '@group', topicId: $topic['id'], params: ['title' => 'Ideas']);
$request->closeForumTopic(peer: '@group', topicId: $topic['id']);
$request->hideForumTopic(peer: '@group', topicId: $topic['id']);
$request->pinForumTopic(peer: '@group', topicId: $topic['id']);
$request->deleteForumTopic(peer: '@group', topicId: $topic['id']);

$topics = $request->getForumTopics(peer: '@group');
```

Topics show as a list by default. Pass `tabs: true` or `sidebar: true` to `toggleForum` to use another layout; the two cannot be combined. `Client::onEditedForumTopic()` reports changes to a topic's title, icon, or closed and hidden state.

To post into a topic, use `sendTopicMessage`:

```php
$request->sendTopicMessage(peer: '@group', topicId: $topic['id'], message: 'Posted in a topic');
```

<a name="communities"></a>
## Communities

Telegram Communities (grouped chats) are managed through their own methods:

```php
$request->createCommunity(title: 'My Community', peer: '@main_channel');
$joined = $request->getJoinedCommunities();
$request->toggleCommunityCollapsed(/* ... */);
$request->approveCommunityPeerLink(/* ... */);
```

<a name="your-profile"></a>
## Your Profile

Manage the logged-in account's own profile:

```php
$request->updateProfile(params: ['first_name' => 'John', 'about' => 'Built with LaraGram']);
$request->setUsername(username: 'john_laragram');
$request->setProfilePhoto(path: '/path/to/avatar.jpg');
$request->deleteProfilePhotos(photos: $photoIds);
$request->setEmojiStatus(emoji: 5368324170671202286);
$request->setOnline(online: true);

// Contacts
$request->getContacts();
$request->addContact(user: '@alice', firstName: 'Alice', phone: '+12025550123');
$request->deleteContacts(users: '@alice');
$request->importContact(phone: '+12025550123', firstName: 'Alice');

// Privacy
$rules = $request->getPrivacyRule('last_seen');
$request->setPrivacyRule('phone_number', 'contacts');
```

Privacy settings are `last_seen`, `chat_invites`, `calls`, `p2p_calls`, `forwards`, `profile_photo`, `phone_number`, `added_by_phone`, `voice_messages`, `about`, `birthday`, `star_gifts_auto_save`, `no_paid_messages` and `saved_music`. Rules are `everyone`, `contacts`, `close_friends`, `nobody`, or a list of `InputPrivacyRule` objects.

> [!WARNING]
> Importing many phone numbers can get an account banned. Only add numbers the account owner actually knows.

<a name="boosts"></a>
## Boosts

Channel boost status and the account's own boosts (a Telegram Premium feature) are available via:

```php
$status  = $request->getBoostsStatus(peer: '@channel');
$mine    = $request->getMyBoosts();
$applied = $request->getUserBoosts(peer: '@channel', user: '@durov');
```

Next: uploading and downloading files in the [Media](/v4/mtproto-media) reference.
