ChatManager
===========

ChatManager is a simple plugin that is used to manage your server's chat.
​
The plugin includes:
- Muting players
- Auto broadcaster
- Prefixes for players (with ranks like owner, moderator, donater etc)
- Limit messages by length (minimum and maximum length)
- MultiChat (separate chat for each world) (can be disabled)
- Mute chat COMING SOON
- Blacklist words (block words)
- Prevent spam by blocking the same message being sent twice
- Delay between messages
- Split messages COMING SOON

Once you install the plugin, there will be 3 configs:
- config.yml:
- Main config, including all prefixes for ranks, console chat messages, enable multi-chat, etc.
- auto-broadcast.yml:
- Config used for auto-broadcasting, including messages, delay between each messages, console messages, etc...
- blocked-words.yml:
- This config has all the black-listed words.

Instructions:
Commands:
- /mute <player> <minutes> (CASE SENSITIVE!) (works as a switch)
- /muted (shows all currently muted players)
- /unmuteall (unmutes all players) (once the server stops, all players are automatically unmuted too)
Configs:
- config.yml:
The config is pretty self explanatory, but at the bottom you will see:

in-ranks:
ranks:
moderator: []
admin: []
etc...
in the brackets [] you can enter player names, so they will be in the rank.
example: admin: ["Lambo","PEMapModder","swegit","yolo"]
so those players will be in the rank admin.

- The other configs are also self explanatory.

Enjoy the plugin!

Github: https://github.com/Lambo16/ChatManager/blob/master/ChatManager.php
