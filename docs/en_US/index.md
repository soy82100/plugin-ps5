# PS5 Plugin

Monitor and control a PlayStation 5 from Jeedom: console state
(on / rest mode / off), current game, remote wake-up and standby.

---

## Features

| Feature | Method | Configuration required |
|---|---|---|
| Console state | local (DDP protocol) | IP address |
| Wake-up | local (pyremoteplay) | PSN pairing (once, via SSH) |
| Standby | local (pyremoteplay) | PSN pairing (once, via SSH) |
| Current game + cover art | PlayStation API (cloud) | npsso token — **optional** |

**Console state works without any additional installation**: it uses Sony's DDP
protocol, implemented directly in PHP. The IP address is all you need.

**Wake-up and standby require prior pairing**, to be done once via SSH. The
procedure is described below.

**Current game is optional** and relies on the PlayStation presence API. Without
a token, the plugin remains entirely local.

---

## Plugin configuration

A single option:

- **Refresh interval (minutes)** — how often the console is polled. Default value:
  1 minute.

Remember to **enable the plugin** after installing it.

---

## Creating a device

Create a device and fill in:

- **IP address**: the IP address of your **PS5 console** (never the Portal's —
  see the dedicated section).
  Assign it a fixed IP in your router: if it changes, the plugin won't find it
  anymore.

- **npsso token**: optional, only needed to display the current game
  (see the dedicated section).

- **User-credential**: **obsolete field**, kept for existing installations. Leave
  it empty.

Once saved, the following commands are created automatically:

| Command | Type | Description |
|---|---|---|
| `refresh` | action | Forces the console to be polled |
| `online` | binary info | 1 = on, 0 = rest mode or off |
| `etat` | text info | "On", "Rest mode", "Off / unreachable" |
| `application` | text info | Current game (requires the npsso token) |
| `jaquette` | text info | Cover art URL of the current game |
| `wake` | action | Wakes up the console |
| `standby` | action | Puts the console into rest mode |

---

## Current game: the npsso token

### Why a token?

Recent PS5 firmwares no longer broadcast the game name on the local network:
the `running-app-name` field of the DDP protocol is no longer populated. This
information is therefore **no longer retrievable locally**, regardless of the
plugin.

The only remaining source is the **PlayStation presence API**, which requires
authenticating with Sony. That is the purpose of the npsso token.

This feature is **entirely optional**. Without a token, the plugin only
communicates with your console, on your local network.

### Retrieving the token

1. In a browser, sign in at **https://my.playstation.com** with the PSN account
   **used on the console**
2. In the **same browser**, open:
   `https://ca.account.sony.com/api/v1/ssocookie`
3. The page displays a short text of the form `{"npsso":"xxxxxxxx..."}`
4. Copy **only** the value between the quotes (64 characters), without the quotes
   or the rest
5. Paste it into the **npsso token** field of the device, then save

> **Lifetime**: this token expires after a few weeks. When the current game stops
> updating, regenerate it by repeating this procedure. The plugin's other
> functions are not affected by its expiration.

> **Privacy**: this token grants access to your PSN account. Do not share it with
> anyone, and never paste it into a public message (forum, ticket, screenshot).

### The game doesn't show up?

- **No profile signed in on the console.** A PS5 powered on at the user selection
  screen is seen as "On" by the plugin, but the PSN API considers it offline.
  Sign in to a profile.
- **Online status hidden.** If the account is set to "Do not show my online
  status" in the PSN privacy settings, the API will always return "offline".
- **Wrong account.** The token must be generated from the account **signed in on
  the console**, not another one.
- **Expired token.** See above.

---

## Wake-up and standby: installation and pairing

### Why a manual step?

Wake-up and standby go through Sony's **Remote Play** protocol, which requires
Jeedom to be *paired* with the console — exactly as a controller or the
PlayStation app would be.

This pairing requires signing in to your PSN account **through a browser**. It
therefore cannot be automated, and must be done **only once**, from the command
line. Once completed, it is permanent: it survives reboots and plugin updates.

Until pairing is done, these two commands have no effect. The rest of the plugin
works normally.

> **Technical note**: previous versions of the plugin used the `playactor` tool.
> That project has been unmaintained since 2022 and its registration now fails
> with a `403 Forbidden` error on recent PS5 firmwares. It has been replaced by
> the `pyremoteplay` Python library. The "User-credential" field has become
> useless: wake-up no longer uses it.

### Prerequisites

- **SSH** access to your Jeedom
- Your **console powered on** — rest mode is not enough for initial pairing
- The credentials of the **PSN account signed in on the console**
- **Remote Play enabled** on the PS5:
  *Settings → System → Remote Play → Enable Remote Play*

### Step 1 — Install pyremoteplay

Connect via SSH, switch to root (`su -`), then:

```bash
sudo apt install -y python3-venv

sudo python3 -m venv /var/www/html/plugins/ps5/resources/python_venv

sudo /var/www/html/plugins/ps5/resources/python_venv/bin/python3 -m pip install \
    "pyee==9.1.1" async_timeout pyremoteplay PSNAWP

sudo chown -R www-data:www-data /var/www/html/plugins/ps5/resources/python_venv
```

> **Important**: the packages must be installed **in a single command**.
> `pyremoteplay` is not compatible with `pyee` version 10 or higher; installing
> them separately would overwrite the correct version.

Verification:

```bash
/var/www/html/plugins/ps5/resources/python_venv/bin/python3 -c "import pyremoteplay; print('OK')"
```

The `av not installed` warning is normal and harmless.

### Step 2 — Pair the console

**With the console powered on**, run:

```bash
su -s /bin/bash -c "HOME=/var/www \
  /var/www/html/plugins/ps5/resources/python_venv/bin/python3 \
  -m pyremoteplay --register 192.168.1.XX" www-data
```

Replacing `192.168.1.XX` with your console's IP.

> The command must be run as **`www-data`**, the user Jeedom runs under. This is
> what ensures the plugin will find the pairing. Do not run it directly as root.

### Step 3 — Sign in to the PSN account

The command displays a long URL starting with
`https://auth.api.sonyentertainmentnetwork.com/...`

1. Copy it and open it in your computer's browser
2. Sign in to your PSN account

The page will then appear to **freeze or stay blank** on an address containing the
word `redirect`. **This is normal, do not close the tab.**

3. Copy the **entire** address shown in the address bar (it contains `?code=...`)
4. Paste it into the terminal, at the `Enter Redirect URL >` prompt

> **Common pitfall**: at this step, the terminal expects the **URL**, not an
> 8-digit code. The code comes at the next step.

> This URL contains a token tied to your PSN account: do not share it with
> anyone.

### Step 4 — Enter the pairing code

The terminal then displays your PSN account and waits for a code.

On the console: *Settings → System → Remote Play → Link Device*

An **8-digit code** is displayed. Enter it into the terminal immediately.

> This code expires very quickly. Only display it when the terminal asks for it,
> and never reuse a previously displayed code.

### Step 5 — Verify

```bash
ls -l /var/www/.pyremoteplay/
```

The `.profile.json` file must be present, and owned by `www-data`. Pairing is
complete: the **Wake up** and **Standby** buttons now work from the dashboard.

Standby takes about ten seconds to run: it establishes a Remote Play session,
sends the command, then verifies that the console has indeed entered rest mode.

---

## Dashboard widget

The plugin provides a custom widget: a stylized PS5 console, a status light that
changes color depending on the state (blinking blue = on, orange = rest mode,
grey = off), the current game, session duration and action buttons.

### Activation

On the device: **Advanced configuration** → **Information** tab → **Options** row
→ check **"Widget template"**.

Without this option, Jeedom displays the standard rendering.

### Use in the Design module

The widget is fully compatible with the Design module: it can be moved, resized
and configured via right-click, like any other device.

When adding it, remember to **enlarge it**: the Design module assigns a reduced
default size to new elements.

---

## Special case: the PlayStation Portal

The **PlayStation Portal** is a Remote Play client: it simply displays a console's
stream. It broadcasts no information on the network and exposes no queryable
service — it is therefore **not supported** by this plugin, and cannot be.

The device must be created with the IP address of the **PS5 console**, never the
Portal's.

**When you play via the Portal, everything works normally**: the console is
powered on, the plugin sees it as "On", and the current game shows up just as if
you were playing on the TV.

However, the plugin **cannot tell which screen** the game is being played on. To
Sony, the Portal is not a gaming device but merely a remote display: the
information is exposed nowhere, neither on the local network nor via the presence
API. A Portal session is indistinguishable from a session on the TV. The same
applies to Remote Play on a smartphone or PC.

---

## Troubleshooting

| Symptom | Likely cause | Solution |
|---|---|---|
| State always "Off / unreachable" | wrong IP, or console on a different network | check the IP, and that Jeedom and the console are on the same network |
| The IP changes regularly | no static lease | assign a fixed IP to the console in your router |
| Wake-up or standby does nothing | pairing not done | see "Wake-up and standby" |
| "pyremoteplay missing" | library not installed | redo step 1 |
| "no PSN account paired" | pairing missing, or done as root | redo step 2 **as `www-data`** |
| Pairing: `must be awake for initial registration` | console in rest mode | power it on completely |
| Pairing: `403 Forbidden` | Remote Play disabled, or wrong PSN account | check the setting on the console and the account used |
| Pairing: `Invalid URL` | PIN code entered instead of the URL | redo step 3 |
| Current game stays empty | npsso token missing, expired, or no profile signed in on the console | see "Current game" |

The plugin logs (**Analysis → Logs → `ps5`**) contain the details of the commands
run and the errors returned. Set the log level to **Debug** in the plugin
configuration for a full diagnosis.

> **Before posting a log on the forum**: make sure it contains neither your npsso
> token nor a pairing URL. Debug mode may reveal them.
