=== ATS Moknah - Article to Speech ===
Contributors: moknah
Tags: text-to-speech, tts, accessibility, audio, ai-voice, arabic, multilingual
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Convert WordPress articles into engaging AI-powered speech with text highlighting using 95+ voices in multiple languages and Arabic dialects.

== Description ==

ATS Moknah (Article to Speech) transforms your written content into high-quality AI voice narration using the Moknah TTS API. Each post can be converted to speech with synchronized text highlighting, providing enhanced accessibility and a better listening experience for your readers.

With **over 95 different AI voices** supporting **multiple Arabic dialects** and **multiple languages**, you can deliver content that resonates with your diverse audience in their preferred voice and language.

= Key Features =

* 🎙️ **AI-Powered Text-to-Speech** - Convert articles to natural-sounding speech
* 🌍 **95+ AI Voices** - Choose from a vast library of professional AI voices
* 🗣️ **Multiple Arabic Dialects** - Support for various Arabic regional dialects (Gulf, Egyptian, Levantine, Maghrebi, and more)
* 🌐 **Multi-Language Support** - Generate speech in multiple languages
* 📝 **Text Highlighting** - Synchronized text highlighting follows the audio
* ⚙️ **Per-Post Control** - Enable/disable speech generation for individual posts
* 🤖 **AI Preprocessing** - Clean and optimize text for better speech quality
* 🔐 **Secure API Integration** - Uses the Moknah TTS API for reliable voice generation
* 🎨 **Frontend Display** - Automatically displays audio player on enabled posts
* 📧 **Email Notifications** - Get notified when audio generation is complete

= How It Works =

1. Install and activate the plugin
2. Get your API key from Moknah.io (contact sales@moknah.io)
3. Configure your settings and choose from 95+ voices
4. Enable speech generation for individual posts
5. Audio player automatically appears on the frontend

= Perfect For =

* News websites and blogs
* Educational content
* Accessibility compliance
* Multilingual websites
* Arabic content creators
* Content publishers targeting diverse audiences

= API Key Required =

To use ATS Moknah, you need an API key from Moknah.io.

**Email**: sales@moknah.io
**Subject**: API Key Request for ATS Moknah Plugin

Include your website URL and intended usage in your request.

= Privacy & Data =

This plugin sends post content to the Moknah TTS API to generate audio. No content is stored by ATS Moknah beyond what is required for audio generation.

Please review [Moknah's privacy policy](https://moknah.io/en/privacy/) before using the service.

= Support =

For technical support, API questions, or feature requests:

* **Email**: sales@moknah.io
* **Website**: [https://moknah.io/](https://moknah.io/)

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Go to Plugins → Add New
3. Search for "ATS Moknah"
4. Click "Install Now" and then "Activate"
5. Navigate to Settings → ATS Moknah to configure

= Manual Installation =

1. Download the plugin files
2. Upload the `ats-moknah` folder to `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to Settings → ATS Moknah to configure

= Configuration =

1. Go to **WordPress Dashboard → Settings → ATS Moknah**
2. Enter your Moknah API key in the provided field
3. Choose from **95+ AI voices**
4. Configure callback URL for API responses
5. Customize frontend display options
6. Click **Save Changes**

== Frequently Asked Questions ==

= Do I need an API key? =

Yes, you need a Moknah API key to use this plugin. Contact sales@moknah.io to request your API key.

= How much does the API cost? =

Contact sales@moknah.io for pricing information and plans.

= What languages and dialects are supported? =

ATS Moknah supports multiple languages with over 95 different AI voices. We offer extensive support for Arabic dialects including Gulf, Egyptian, Levantine, Maghrebi, and more. Contact sales@moknah.io for a complete list of available languages, dialects, and voices.

= Can I use different voices for different posts? =

Yes! Each post can be configured with its own voice, language, and dialect settings.

= Does it work with all post types? =

The plugin is designed for standard WordPress posts. Custom post type support may vary.

= Can I use this on multiple sites? =

API key usage terms depend on your agreement with Moknah. Contact sales@moknah.io for multi-site licensing.

= How do I enable speech for a post? =

1. Create or edit a post
2. Locate the **ATS Moknah** meta box in the post editor
3. Check the option to **Enable Speech Generation**
4. Enable **AI Preprocessing** if desired
5. Select your preferred **voice**
6. Click **Generate Audio**
7. You'll be notified by email when the audio is ready (if enabled in settings)

= Can I customize the audio player design? =

Yes, the frontend display can be customized through CSS and WordPress hooks.

= Is there a limit on article length? =

Limitations depend on your API plan. Contact Moknah for details.

= How does the plugin handle multilingual posts? =

ATS Moknah does not automatically manage multilingual posts. Language detection and multilingual management are the responsibility of the site and its developers.

To prevent audio conflicts when multiple language versions share the same post ID, generate a unique TTS identifier per language. See the documentation for implementation details.

= Where is the audio stored? =

Audio files are generated by the Moknah TTS API and delivered via callback. Storage details depend on your API configuration.

= How long does audio generation take? =

Audio generation time depends on article length and API load. You'll receive an email notification when generation is complete (if enabled).

== Screenshots ==

1. Admin settings page - Configure API key and voice settings
2. Post editor meta box - Enable speech generation per post
3. Frontend audio player - Synchronized text highlighting
4. Voice selection interface - Choose from 95+ AI voices

== Changelog ==

= 1.0 =
* Initial release
* Article to speech conversion
* 95+ AI voices available
* Multiple Arabic dialects support
* Multi-language support
* Text highlighting synchronization
* Per-post generation control
* AI preprocessing option
* Email notification system
* Admin settings panel
* Frontend audio player integration

== Upgrade Notice ==

= 1.0 =
Initial release of ATS Moknah - Article to Speech plugin.

== Additional Information ==

= Multilingual Implementation Note =

ATS Moknah does **not automatically manage multilingual posts**.
Language detection and multilingual management are the responsibility of the site and its developers.

> ⚠️ Conflicts only occur if multiple language versions of a post share the same post ID.
> To prevent overwriting audio, generate a **unique TTS identifier per language**.

= Credits =

**Author**: Moknah.io
**Author URI**: [https://moknah.io/](https://moknah.io/)
**Plugin URI**: [https://moknah.io/](https://moknah.io/)

Made with ❤️ by [Moknah.io](https://moknah.io/)