# Moknah ATS WordPress Plugin

## Plugin Overview
The Moknah ATS (Automatic Transcription Service) Plugin provides seamless transcription and voice processing functionalities for WordPress. Enhance your content with automatically generated transcripts, subtitles, and more.

## Features
- Automated transcription for audio and video files
- Customizable voice settings
- Preprocessing options to enhance audio quality
- Email notifications for transcription updates
- Advanced features like SRT synchronization and text highlighting

## Requirements
- WordPress version 5.0 or higher
- PHP version 7.0 or higher
- Audio/Video files for transcription

## Installation Instructions
1. Download the plugin zip file from the repository.
2. Go to the WordPress admin panel, navigate to **Plugins > Add New**.
3. Click on **Upload Plugin** and select the zip file.
4. Activate the plugin from the **Plugins** menu.

## Configuration Guide
### API Setup
1. Create an account on Moknah.io.
2. Obtain your API key from your account dashboard.
3. Navigate to **Moknah ATS > Settings** in the WordPress admin panel.
4. Enter your API key and save changes.

## Usage Instructions
### Admin Usage
- To start a transcription, upload an audio/video file through **Moknah ATS > New Transcription**.
- Monitor progress and access completed transcriptions from the **Transcriptions** list.

### Frontend Usage
- Transcriptions can be displayed using shortcodes. For example:
  ```[moknah_transcription id="transcription_id"]``` 
  Replace `transcription_id` with your transcription's ID.
- Display transcripts in HTML or plain text format.

## Voice Settings
Customize voice settings including:
- Voice type (male/female)
- Voice speed
- Language selection

## Preprocessing Options
Improve the quality of audio input by:
- Reducing background noise
- Adjusting volume levels
- Trimming silence 

## Email Notifications
Enable email notifications under **Moknah ATS > Notifications** to receive updates:
- When transcription is complete
- For error notifications

## Advanced Features
### SRT Synchronization
- Synchronize your transcriptions with video timestamps to create SRT files for subtitles.

### Text Highlighting
- Highlight specific words or phrases within the transcription to draw attention.

## Troubleshooting
- **Transcription Fails**: Ensure your audio/video file meets the requirements.
- **API Errors**: Verify your API key and internet connection.

For more help, please check the **Support** section on Moknah.io.

## Additional Links
- [Moknah.io](https://moknah.io) - Visit our homepage for more information.
- [Documentation](https://moknah.io/documentation) - Comprehensive user guides and API documentation.

---