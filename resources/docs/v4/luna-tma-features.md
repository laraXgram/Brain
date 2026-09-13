# Luna: Telegram Features

<a name="introduction"></a>
## Introduction

Beyond [authentication, theme, and buttons](/v4/luna-tma), the Telegram WebApp SDK exposes a large surface of device and platform features — storage, biometrics, location, motion sensors, haptics, popups, sharing, payments, and viewport control. Luna wraps every one of them in a small, **promise-based, SSR-safe** helper so you can `await` them and call them without checking `window.Telegram` first.

All helpers are exported from `@laraxgram/luna` with a `telegram*` prefix (the core also exports the unprefixed objects). Each is a thin wrapper over the native SDK, so [Telegram's WebApp documentation](https://core.telegram.org/bots/webapps) is the authoritative reference for behavior and platform support.

> [!NOTE]
> Every helper is safe to call outside Telegram: fire-and-forget helpers no-op, and promise helpers resolve to a sensible empty value. You still handle rejections with `.catch()` where the native call can fail (permission denied, unsupported platform).

<a name="cloud-storage"></a>
## CloudStorage

`telegramCloudStorage` persists small key/value string data in the user's Telegram cloud, synced across their devices. Ideal for drafts and preferences:

```js
import { telegramCloudStorage } from '@laraxgram/luna'

await telegramCloudStorage.setItem('bio-draft', text)
const draft = await telegramCloudStorage.getItem('bio-draft') // '' if unset
const keys = await telegramCloudStorage.getKeys()
await telegramCloudStorage.removeItem('bio-draft')
```

A typical draft-restore on a form page:

```jsx
useEffect(() => {
    telegramCloudStorage
        .getItem('bio-draft')
        .then((draft) => draft && form.setData('bio', draft))
        .catch(() => {})
}, [])
```

<a name="device-storage"></a>
### Device & Secure Storage

Two more storage backends mirror the same API but store locally on the device:

```js
import { telegramDeviceStorage, telegramSecureStorage } from '@laraxgram/luna'

// Plain per-device storage.
await telegramDeviceStorage.setItem('theme', 'dark')
await telegramDeviceStorage.clear()

// Encrypted, hardware-backed storage (returns a richer value on read).
await telegramSecureStorage.setItem('token', secret)
const value = await telegramSecureStorage.getItem('token')
```

<a name="biometrics"></a>
## Biometrics

`telegramBiometric` gates actions behind the device's fingerprint or face authentication:

```js
import { telegramBiometric } from '@laraxgram/luna'

if (telegramBiometric.isAvailable()) {
    const granted = await telegramBiometric.requestAccess({ reason: 'Unlock your vault' })

    if (granted) {
        const result = await telegramBiometric.authenticate({ reason: 'Confirm payment' })
        if (result.ok) {
            // result.token — the biometric token, if the device provides one.
        }
    }
}
```

<a name="location"></a>
## Location

`telegramLocation` reads the device location after the user grants access:

```js
import { telegramLocation } from '@laraxgram/luna'

await telegramLocation.init()

if (telegramLocation.isAvailable()) {
    const loc = await telegramLocation.getLocation() // LocationData | null
    // loc?.latitude, loc?.longitude, …
} else {
    telegramLocation.openSettings() // Deep-link to the permission screen.
}
```

<a name="sensors"></a>
## Motion Sensors

Three motion sensors follow a `start` / `read` / `stop` pattern. Start streaming, poll the latest reading in a loop or effect, and stop when done:

```js
import {
    telegramAccelerometer,
    telegramGyroscope,
    telegramDeviceOrientation,
} from '@laraxgram/luna'

await telegramAccelerometer.start({ refresh_rate: 60 })
const { x, y, z } = telegramAccelerometer.read() // Vector3
await telegramAccelerometer.stop()

await telegramGyroscope.start()
const rotation = telegramGyroscope.read()

await telegramDeviceOrientation.start()
const orientation = telegramDeviceOrientation.read() // { alpha, beta, gamma }
```

<a name="haptics"></a>
## Haptic Feedback

`telegramHaptic` triggers native vibration feedback — fire-and-forget, SSR-safe. Use it to make interactions feel physical:

```js
import { telegramHaptic } from '@laraxgram/luna'

telegramHaptic.impact('light')        // 'light' | 'medium' | 'heavy' | 'rigid' | 'soft'
telegramHaptic.notification('success')// 'success' | 'warning' | 'error'
telegramHaptic.selection()            // On a value change in a picker.
```

A natural place to fire haptics is a form submit — see the [MainButton example](/v4/luna-tma#main-button-and-forms).

<a name="popups"></a>
## Popups, Alerts & QR

Native dialogs resolve with the user's choice:

```js
import { telegramPopup, telegramScanQr, telegramReadClipboard } from '@laraxgram/luna'

// Returns the id of the pressed button (or null if dismissed).
const pressed = await telegramPopup.show({
    title: 'Delete?',
    message: 'This cannot be undone.',
    buttons: [
        { id: 'ok', type: 'destructive', text: 'Delete' },
        { id: 'cancel', type: 'cancel' },
    ],
})

// Open the QR scanner; resolves with the scanned text (or null).
const code = await telegramScanQr({ text: 'Scan a ticket' })

// Read the clipboard (resolves null if unavailable/denied).
const clip = await telegramReadClipboard()
```

<a name="sharing"></a>
## Sharing & Requests

`telegramShare` opens native share flows; `telegramRequest` asks the user for access to their data:

```js
import {
    telegramShare,
    telegramRequest,
    telegramSetEmojiStatus,
    telegramDownloadFile,
    telegramSwitchInlineQuery,
} from '@laraxgram/luna'

// Share a prepared inline message by id (from Luna::shareMessage on the server).
await telegramShare.message(preparedMessageId)

// Open the story editor (fire-and-forget).
telegramShare.toStory('https://example.com/card.png', { text: 'My score!' })

// Ask the user for access.
await telegramRequest.writeAccess()          // permission to DM the user
await telegramRequest.contact()              // share their phone/contact
await telegramRequest.emojiStatusAccess()
await telegramRequest.chat(requestId)

// Set the user's emoji status (after emojiStatusAccess).
await telegramSetEmojiStatus(customEmojiId)

// Download a file to the device.
await telegramDownloadFile({ url, file_name: 'invoice.pdf' })

// Switch to inline mode in a chosen chat.
telegramSwitchInlineQuery('search term', ['users', 'groups'])
```

The `preparedMessageId` comes from [`Luna::shareMessage()`](/v4/luna-tma#bot-actions) on the server, which prepares the inline message and returns its id.

<a name="invoices"></a>
## Payments

`telegramOpenInvoice` opens Telegram's native payment sheet for an invoice link and resolves with the final status:

```js
import { telegramOpenInvoice } from '@laraxgram/luna'

// The invoice URL comes from Luna::invoiceLink(...) on the server.
const status = await telegramOpenInvoice(invoiceUrl)
// status: 'paid' | 'cancelled' | 'failed' | 'pending'

if (status === 'paid') {
    router.reload({ only: ['balance'] })
}
```

Generate the invoice link server-side with [`Luna::invoiceLink()`](/v4/luna-tma#bot-actions).

<a name="viewport"></a>
## Viewport & Home Screen

Control fullscreen mode, screen orientation, and the add-to-home-screen prompt:

```js
import {
    telegramRequestFullscreen,
    telegramExitFullscreen,
    telegramLockOrientation,
    telegramUnlockOrientation,
    telegramAddToHomeScreen,
    telegramCheckHomeScreenStatus,
} from '@laraxgram/luna'

await telegramRequestFullscreen()
await telegramExitFullscreen()

telegramLockOrientation()
telegramUnlockOrientation()

const status = await telegramCheckHomeScreenStatus() // e.g. 'added' | 'missed' | …
if (status !== 'added') {
    await telegramAddToHomeScreen()
}
```

For reactive viewport height and safe-area insets, use [`useTelegramViewport`](/v4/luna-tma#theme-and-viewport) instead of polling.

<a name="native-transport"></a>
## Native Transport (TMA-first escape hatch)

Most Mini Apps talk to a Luna backend over normal [visits](/v4/luna-routing). For a purely client-driven app that hands data back to the *bot* (not an HTTP server), the lightweight native transport sends data straight through Telegram and closes the app:

```js
import { telegramSendData, telegramCloseMiniApp } from '@laraxgram/luna'

// Sends up to 4096 bytes to the bot as a service message, then Telegram
// closes the Mini App. Only works for apps opened from a keyboard button.
telegramSendData({ choice: 'yes', amount: 10 })

// Close the Mini App programmatically.
telegramCloseMiniApp()
```

> [!NOTE]
> `telegramSendData` is an opt-in escape hatch for simple keyboard-button apps. It bypasses your Luna backend entirely, so there's no server-side validation of the result — the bot receives the raw data. Prefer normal Luna visits (which are authenticated by the [`telegram` middleware](/v4/luna-tma#the-telegram-middleware)) for anything non-trivial.

<a name="testing-with-the-mock"></a>
## Testing with the Mock

Outside Telegram there is no `window.Telegram.WebApp`, so device features no-op. For development and tests, install a mock WebApp that satisfies the theme, viewport, buttons, events, storage, and dialog APIs Luna uses:

```js
import { installTelegramMock } from '@laraxgram/luna'

installTelegramMock({
    platform: 'ios',
    themeParams: { bg_color: '#ffffff', text_color: '#000000' },
    initDataUnsafe: {
        user: { id: 1, first_name: 'Ada' },
    },
})
```

> [!WARNING]
> The mock is for local development and testing only. It fabricates client-side state and provides **no** security. `initData` is always empty for a mock — the real security boundary is server-side [init-data validation](/v4/luna-tma#authentication), which a mock cannot and must not satisfy. Never rely on a mock to represent an authenticated user in production.

<a name="framework-notes"></a>
## Framework Notes

Every helper in this chapter is framework-agnostic — the same `telegram*` imports work in React, Vue, and Svelte, because they operate on the native SDK directly rather than on framework state. Use them alongside the [reactive hooks](/v4/luna-tma#theme-and-viewport) (`useTelegram`, `useTelegramFormButton`, `useTelegramBackButton`, …), which *are* framework-specific and live in `@laraxgram/react`, `@laraxgram/vue3`, and `@laraxgram/svelte`.

<a name="wrap-up"></a>
## Wrap-Up

That completes the Luna documentation:

<div class="content-list" markdown="1">

- **[Luna](/v4/luna)** — introduction, installation, and how it works.
- **[Pages & Props](/v4/luna-pages)** — rendering and data.
- **[Routing & Visits](/v4/luna-routing)** — navigation.
- **[Forms](/v4/luna-forms)** — sending data back.
- **[Frontend Setup](/v4/luna-frontend)** — frameworks, layouts, Vite, SSR.
- **[Telegram Mini Apps](/v4/luna-tma)** — auth, theme, and native buttons.
- **Telegram Features** — the device-feature surface (this page).

</div>
