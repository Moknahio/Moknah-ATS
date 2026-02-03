# Moknah ATS - Article to Speech for WordPress

Convert your blog posts into natural-sounding audio with an interactive highlighter. Moknah ATS is a powerful WordPress plugin that brings accessibility and engagement to your content.

## Features

- 🎙️ **Automatic Audio Generation** - Convert any post to speech with a single click
- ✨ **Interactive Highlighter** - Synchronized text highlighting as the audio plays
- 📱 **Responsive Player** - Works seamlessly on desktop, tablet, and mobile devices
- 🔐 **Per-Post Control** - Enable/disable audio generation for individual posts
- 🌐 **Professional Audio** - High-quality speech synthesis
- ♿ **Improved Accessibility** - Make your content accessible to all users
- 📊 **User-Friendly** - Simple interface for both admins and readers

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- An active Moknah ATS API key

## Installation

### Step 1: Download and Install

1. Download the plugin from the repository
2. Extract the plugin files to `/wp-content/plugins/moknah-ats/` directory
3. Activate the plugin through the WordPress admin dashboard under **Plugins > Installed Plugins**

### Step 2: Install Dependencies

The plugin uses Composer for dependency management. Run the following command in the plugin directory:

```bash
composer install
```

### Step 3: Request API Key

To use the Article to Speech functionality, you need to request an API key:

1. **Email:** [sales@moknah.io](mailto:sales@moknah.io)
2. **Subject:** "ATS API Key Request"
3. **Include in your email:**
   - Your website URL
   - Website name
   - Expected monthly post count
   - Any additional requirements

Once approved, you'll receive your API key via email.

## Configuration

### Adding Your API Key

1. Go to **WordPress Admin Dashboard**
2. Navigate to **Settings > Moknah ATS**
3. Paste your API key in the designated field
4. Click **Save Changes**

Your plugin is now ready to use!

## Usage

### For WordPress Administrators

#### Enable Audio for a Post

1. Edit or create a new post
2. In the post editor, you'll find the **Moknah ATS** section
3. Check the box labeled **"Generate Audio for this Post"**
4. Update/Publish the post
5. The system will automatically generate audio for your content

#### Access Plugin Settings

1. Go to **Settings > Moknah ATS**
2. Configure:
   - API Key
   - Default voice settings
   - Audio quality preferences
   - Display preferences

### For Website Visitors

1. **Locate the Player** - On posts with audio enabled, the audio player appears below the post title
2. **Play Audio** - Click the play button to start listening
3. **Read Along** - The text automatically highlights as the audio plays
4. **Control Playback** - Use standard player controls:
   - Play/Pause
   - Volume adjustment
   - Progress bar navigation
   - Playback speed (if available)

## Plugin Structure

```
moknah-ats/
├── ats-moknah.php              # Main plugin file
├── README.md                     # This file
├── LICENSE                       # Plugin license
├── composer.json                 # PHP dependencies
├── assets/                       # CSS, JavaScript, and images
├── includes/                     # Plugin includes and utilities
└── mk-mp-player-template.html   # Audio player template
```

## Frequently Asked Questions

### How long does it take to generate audio?

Audio generation typically completes within 5-30 seconds depending on post length. Longer posts may take slightly more time.

### Can I disable audio for specific posts?

Yes! Simply uncheck the **"Generate Audio for this Post"** checkbox in the post editor before publishing.

### What audio formats are supported?

The plugin generates audio in standard web formats optimized for streaming across all devices.

### Can I use this on multiple websites?

API keys are typically tied to a single domain. For multiple sites, please request additional keys by contacting sales@moknah.io.

### What if audio generation fails?

1. Verify your API key is correct and active
2. Check your internet connection
3. Ensure the post has adequate text content
4. Contact support@moknah.io if the issue persists

### Is there a character limit for posts?

While there's no hard limit, extremely long posts (10,000+ words) may take longer to process. Consider breaking longer content into sections.

## Troubleshooting

### API Key Not Working

- Double-check that your API key is entered correctly (no extra spaces)
- Ensure the key hasn't expired or been revoked
- Contact sales@moknah.io to verify your key status

### Audio Not Generating

1. Ensure **"Generate Audio for this Post"** is checked
2. Clear WordPress cache if using a caching plugin
3. Check browser console for any JavaScript errors (F12)
4. Ensure adequate post content (minimum recommended: 100 words)

### Player Not Displaying

1. Verify the post has audio enabled
2. Check that audio generation completed successfully
3. Clear browser cache
4. Try a different browser
5. Ensure JavaScript is enabled in your browser

### Performance Issues

- The plugin is optimized for performance
- If experiencing slowness, check with your hosting provider
- Disable other heavy plugins temporarily to identify conflicts

## Support

For technical support, feature requests, or issues:

📧 **Email:** support@moknah.io  
💼 **Sales Inquiries:** sales@moknah.io  
📱 **Website:** Visit our support portal for additional resources

## Security

- API keys are stored securely in WordPress
- Never share your API key publicly
- Regenerate your key if you suspect compromise
- Contact support immediately if you notice unauthorized usage

## Browser Compatibility

The plugin works on:
- ✅ Chrome/Edge (latest versions)
- ✅ Firefox (latest versions)
- ✅ Safari (latest versions)
- ✅ Mobile browsers (iOS Safari, Chrome Mobile)

## Performance Notes

- Audio files are cached for improved performance
- Player loads asynchronously to avoid impacting page speed
- No significant impact on website performance

## License

This plugin is licensed under the GPL License. See the LICENSE file for details.

## Changelog

### Version 1.0.0
- Initial release
- Core Article to Speech functionality
- Interactive highlighter
- Per-post audio control
- Settings page integration

## Contributing

While this is a commercial plugin, bug reports and feature suggestions are welcome! Please contact our support team.

## About Moknah

Moknah provides cutting-edge Article to Speech technology designed to make web content more accessible and engaging for all users.

---

**Need help?** Email [support@moknah.io](mailto:support@moknah.io) or [sales@moknah.io](mailto:sales@moknah.io)