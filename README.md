# About & usage
LushendaBot is a Discord bot which allows users to upload a .zip file of their local `new_adhoc_dumps` folder for
comparison with existing values. The `new_adhoc_dumps` folder is automatically generated when playing
Dragon Quest X while running DQXClarity.

The purpose of uploading these zip files are to continue to build a database of all adhoc in-game dialog.

"adhoc" is the terminology used by the DQXClarity team to represent all dialog which could be considered static, such
as NPCs with specific functions.

# How to run the bot
Run `composer install` to get vendor folder
Create an `env.php` file by doing `cp env-template.php env.php` and fill out the variables
`php bot.php` after adding it to your discord server