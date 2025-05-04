Telegram Contact Form Plugin
Overview
The Telegram Contact Form plugin is a lightweight WordPress plugin that creates a customizable contact form. Submissions are stored in the WordPress database and can be sent as notifications to a specified Telegram chat. The plugin includes spam protection options and basic analytics.
Features

Customizable Form Fields: Enable or disable fields (Name, Email, Phone, Telegram Username, Message) and set them as required or optional.
Telegram Integration: Send form submissions to a Telegram chat using a bot token and chat ID.
Spam Protection: 
Integration with CleanTalk (requires an API key).
Built-in simple math captcha.
Option to enable/disable both protection methods.


Analytics: View total submissions, submissions in the last 7 days, average submissions per day, and a table of submissions per day for the last 30 days.
Styling Options: Enqueue basic styles or add custom CSS.
Admin Interface: Manage requests, settings, and analytics from the WordPress admin dashboard.

Installation

Upload the telegram-contact-form folder to the /wp-content/plugins/ directory.
Activate the plugin through the 'Plugins' menu in WordPress.
Configure the plugin settings under "Telegram Form" > "Settings" in the admin menu.

Configuration
Settings

Telegram Bot Token: Obtain from @BotFather on Telegram.
Telegram Chat ID: Get by sending a message to your bot and checking the API response.
Enable Telegram Notifications: Toggle to send submissions to Telegram.
Form Fields: Customize which fields to show and whether they are required.
Styling Options: Enable basic styles or add custom CSS.
Spam Protection: Enable CleanTalk (requires API key) or built-in captcha.
Test Telegram Notification: Send a test message to verify Telegram setup.

Telegram Setup

Open Telegram and search for @BotFather.
Send /newbot to create a bot and get a token.
Start a chat with your bot and send a message.
Use https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getUpdates to find your Chat ID.

Usage

Add the shortcode [telegram_contact_form] to any page or post to display the form.
Submissions are saved to the database and sent to Telegram if enabled.
View and manage submissions under "Telegram Form" > "Requests".
Check analytics under "Telegram Form" > "Analytics".

Requirements

WordPress 4.7 or higher.
PHP 5.6 or higher with session support.
CleanTalk API key (optional for spam protection).


Support
For issues or questions, please contact the plugin author via the WordPress plugin repository or open an issue on the GitHub page (if available).
License
This plugin is released under the GPL-2.0+ license.
