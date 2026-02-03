# ATS Moknah - Article to Speech WordPress Plugin

Convert your WordPress articles into engaging AI-powered speech with text highlighting.

## Description

ATS Moknah (Article to Speech) is a WordPress plugin that transforms your written content into high-quality AI voice narration using the Moknah TTS API. Each post can be converted to speech with synchronized text highlighting, providing enhanced accessibility and a better listening experience for your readers.

With **over 95 different AI voices** supporting **multiple Arabic dialects** and **multiple languages**, you can deliver content that resonates with your diverse audience in their preferred voice and language.

---
## Features

- 🎙️ **AI-Powered Text-to-Speech** - Convert articles to natural-sounding speech
- 🌍 **95+ AI Voices** - Choose from a vast library of professional AI voices
- 🗣️ **Multiple Arabic Dialects** - Support for various Arabic regional dialects
- 🌐 **Multi-Language Support** - Generate speech in multiple languages
- 📝 **Text Highlighting** - Synchronized text highlighting follows the audio
- ⚙️ **Per-Post Control** - Enable/disable speech generation for individual posts
- 🔐 **Secure API Integration** - Uses the Moknah TTS API for reliable voice generation
- 🎨 **Frontend Display** - Automatically displays audio player on enabled posts

---
## Requirements

- **WordPress**: 5.8 or higher
- **PHP**: 7.4 or higher
- **Moknah API Key**: Required (see below)

---
## Installation

1. Download the plugin files
2. Upload the `ats-moknah` folder to `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to **Settings → ATS Moknah** to configure

---
## Getting Your API Key

To use ATS Moknah, you need an API key from Moknah.io:

📧 **Email**: sales@moknah.io  
**Subject**: API Key Request for ATS Moknah Plugin

Include your website URL and intended usage in your request.

---
## Configuration

### 1. Enter Your API Key

1. Go to **WordPress Dashboard → Settings → ATS Moknah**
2. Enter your Moknah API key in the provided field
3. Click **Save Changes**

### 2. Configure Plugin Settings

- Choose from **95+ AI voices**
- Configure callback URL for API responses
- Customize frontend display options

---
## Usage

### Enabling Speech for a Post

1. Create or edit a post
2. In the post editor, locate the **ATS Moknah** meta box
3. Check the option to **Enable Speech Generation** for this post
4. Enable **AI Preprocessing** if you want the plugin to clean and optimize the text for better speech quality
5. Select your preferred **voice**
6. Click **Generate Audio** to send the article content to the Moknah TTS API for processing
7. You will be notified once the audio is generated and ready for playback by email if you allow it in settings
> Audio generation may take a few moments depending on article length and API load.

### Audio Player

Once generated, an audio player with text highlighting will automatically appear on your post's frontend, allowing visitors to:
- Play/pause the audio narration
- See synchronized text highlighting as the audio plays
- Enjoy an accessible reading experience

---
## File Structure

```
ats-moknah/
├── assets/                                 # CSS, JS
├── includes/
│   ├── class-ats-moknah-admin.php         # Admin interface
│   ├── class-ats-moknah-client.php        # API client
│   ├── class-ats-moknah-callback.php      # Callback handler
│   └── class-ats-moknah-frontend.php      # Frontend display
├── vendor/                                 # Composer dependencies
├── composer.phar                           # Composer executable
├── ats-moknah.php                          # Main plugin file
├── composer.json                           # Composer configuration
├── composer.lock                           # Composer lock file
├── LICENSE                                 # License file
├── README.md                               # Plugin documentation
└── mk-mp-player-template.html              # Audio player template
```

---
## Supporting Multilingual Posts in ATS Moknah

ATS Moknah does **not automatically manage multilingual posts**.  
Language detection and multilingual management are the responsibility of the site and its developers.

> ⚠️ Conflicts only occur if multiple language versions of a post share the same post ID.  
> To prevent overwriting audio, generate a **unique TTS identifier per language**.

### Implementation Note

When generating TTS, pass your **unique identifier** instead of `$post_id` in the `class-ats-moknah-client.php` file at `line 262`:

```php
$unique_id = $post_id . '-' . $lang; // recommended unique TTS ID
self::generateTTS($unique_id);
```

The same identifier will then be received in the callback as ```articleId```:

```php
$data['articleId']
```

This ensures that the generated audio is correctly mapped to the respective language version of the post.

---
## Frequently Asked Questions

### How much does the API cost?
Contact sales@moknah.io for pricing information and plans.

### Can I use this on multiple sites?
API key usage terms depend on your agreement with Moknah. Contact sales@moknah.io for multi-site licensing.

### Does it work with all post types?
The plugin is designed for standard WordPress posts. Custom post type support may vary.

### Can I customize the audio player design?
Yes, the frontend display can be customized through CSS and WordPress hooks.

### What languages are supported?
ATS Moknah supports **multiple languages** with **over 95 different AI voices**. We offer extensive support for **Arabic dialects** including Gulf, Egyptian, Levantine, Maghrebi, and more. Contact sales@moknah.io for a complete list of available languages, dialects, and voices.

### Can I use different voices for different posts?
Yes! Each post can be configured with its own voice, language, and dialect settings.

### Is there a limit on article length?
Limitations depend on your API plan. Contact Moknah for details.

---
## Support

For technical support, API questions, or feature requests:

- **Email**: sales@moknah.io
- **Website**: https://moknah.io/

---
## License

This plugin is licensed under the GPLv2 or later.

```text
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

---
## Credits

**Author**: Moknah.io  
**Author URI**: https://moknah.io/  
**Plugin URI**: https://moknah.io/

---
## Changelog

### Version 1.0
- Initial release
- Article to speech conversion
- 95+ AI voices available
- Multiple Arabic dialects support
- Multi-language support
- Text highlighting synchronization
- Per-post generation control
- Admin settings panel
- Frontend audio player integration

---
## Data & Privacy

This plugin sends post content to the Moknah TTS API to generate audio.  
No content is stored by ATS Moknah beyond what is required for audio generation.

Please review Moknah’s [privacy policy](https://moknah.io/en/privacy/) before using the service.

---
**Made with ❤️ by [Moknah.io](https://moknah.io/)**