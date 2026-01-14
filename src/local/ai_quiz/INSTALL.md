# Installation Guide - AI Quiz Generator for Moodle

## Prerequisites

Before installing the plugin, ensure you have:

1. **Moodle 4.2 or later** installed and running
2. **PHP 7.4 or later** with the following extensions:
   - curl
   - zip
   - json
3. **Google Gemini API Key** (free tier available at https://makersuite.google.com/app/apikey)
4. **pdftotext utility** (optional but recommended for PDF processing):
   ```bash
   # Ubuntu/Debian
   sudo apt-get install poppler-utils

   # CentOS/RHEL
   sudo yum install poppler-utils

   # macOS
   brew install poppler
   ```

## Installation Steps

### Method 1: Manual Installation

1. **Download/Copy the plugin**
   ```bash
   # Copy to Moodle local plugins directory
   cp -r src/local/ai_quiz /path/to/moodle/local/
   ```

2. **Set proper permissions**
   ```bash
   cd /path/to/moodle/local/ai_quiz
   chmod -R 755 .
   chown -R www-data:www-data .  # Adjust for your web server user
   ```

3. **Install via Moodle Admin**
   - Log in as administrator
   - Navigate to: **Site Administration → Notifications**
   - Click **Upgrade Moodle database now**
   - The plugin will be installed automatically

4. **Configure API Key**
   - Go to: **Site Administration → Plugins → Local plugins → AI Quiz Generator**
   - Enter your **Google Gemini API Key**
   - Save changes

### Method 2: Via Moodle Plugin Installer (ZIP)

1. **Create ZIP package**
   ```bash
   cd src
   zip -r ai_quiz.zip local/ai_quiz/
   ```

2. **Upload via Moodle**
   - Log in as administrator
   - Go to: **Site Administration → Plugins → Install plugins**
   - Choose the ZIP file
   - Click **Install plugin from the ZIP file**
   - Follow the on-screen instructions

## Configuration

### Basic Configuration

1. **API Keys**
   - Navigate to: **Site Administration → Plugins → Local plugins → AI Quiz Generator**
   - Configure:
     - **Gemini API Key**: Your Google Gemini API key (required)
     - **OpenAI API Key**: Coming soon
     - **Claude API Key**: Coming soon

2. **Default Settings**
   - **Default Provider**: Select Gemini (currently the only option)
   - **Default Questions**: Number of questions to generate (default: 20)
   - **Temperature**: AI creativity setting (0.0-1.0, default: 0.7)

### Permissions

The plugin defines two capabilities:

1. **local/ai_quiz:generate** - Generate quiz questions
   - Assigned to: Editing Teacher, Manager (by default)
   - Context level: Course

2. **local/ai_quiz:manage** - Manage plugin settings
   - Assigned to: Manager (by default)
   - Context level: System

To adjust permissions:
- Go to: **Site Administration → Users → Permissions → Define roles**
- Edit the desired role
- Search for "ai_quiz"
- Enable/disable capabilities as needed

## Verification

### Test the Installation

1. **Check Plugin is Active**
   - Go to: **Site Administration → Plugins → Plugins overview**
   - Search for "AI Quiz Generator"
   - Status should be "Enabled"

2. **Test Generation**
   - Navigate to any course
   - Click "AI Quiz Generator" in navigation
   - Upload a sample PDF or DOCX file
   - Set number of questions to 5 (for testing)
   - Click "Generate Quiz"
   - Check if questions appear in question bank

### Troubleshooting

**Problem: Plugin not showing in navigation**
- Solution: Clear Moodle cache
  ```bash
  php admin/cli/purge_caches.php
  ```
  Or via admin interface: **Site Administration → Development → Purge all caches**

**Problem: API key not working**
- Verify key is correct at: https://makersuite.google.com/app/apikey
- Check Moodle error logs: **Site Administration → Reports → Logs**
- Ensure cURL is enabled in PHP

**Problem: PDF processing fails**
- Install pdftotext utility (see Prerequisites)
- Check file permissions on uploaded files
- Try with a simple PDF file first

**Problem: Questions not importing**
- Check question category exists
- Verify user has `moodle/question:add` capability
- Check Moodle debug mode for errors: **Site Administration → Development → Debugging**

## Getting API Keys

### Google Gemini API Key

1. Visit: https://makersuite.google.com/app/apikey
2. Sign in with Google account
3. Click "Create API Key"
4. Copy the key and save it securely
5. Enter in Moodle plugin settings

**Free Tier Limits:**
- 60 requests per minute
- 1500 requests per day
- Sufficient for most educational use cases

## Upgrade

To upgrade the plugin:

1. **Backup your Moodle site** (database and files)

2. **Replace plugin files**
   ```bash
   cd /path/to/moodle/local
   rm -rf ai_quiz
   cp -r /path/to/new/ai_quiz .
   ```

3. **Run upgrade**
   - Visit: **Site Administration → Notifications**
   - Follow upgrade prompts

4. **Clear caches**
   ```bash
   php admin/cli/purge_caches.php
   ```

## Uninstallation

1. **Via Moodle Admin**
   - Go to: **Site Administration → Plugins → Plugins overview**
   - Find "AI Quiz Generator"
   - Click "Uninstall"
   - Follow prompts

2. **Manual Uninstallation**
   ```bash
   cd /path/to/moodle/local
   rm -rf ai_quiz
   php admin/cli/purge_caches.php
   ```

## Security Considerations

1. **API Keys**: Store securely, never commit to version control
2. **File Uploads**: Plugin validates file types (PDF, DOCX, PPTX only)
3. **Permissions**: Restrict `local/ai_quiz:generate` to trusted users
4. **Data Privacy**: Files are processed temporarily and deleted immediately
5. **Rate Limits**: Be aware of API provider rate limits

## Support

For installation issues:
1. Check Moodle error logs
2. Enable debugging: **Site Administration → Development → Debugging**
3. Consult README.md for additional documentation
4. Contact system administrator

## Next Steps

After successful installation:
1. Read the [README.md](README.md) for usage instructions
2. Configure default settings for your institution
3. Test with sample documents before deploying to teachers
4. Train teachers on how to use the plugin effectively
5. Monitor API usage to stay within rate limits

## System Requirements Summary

| Requirement | Minimum | Recommended |
|------------|---------|-------------|
| Moodle | 4.2 | 4.5+ |
| PHP | 7.4 | 8.1+ |
| Memory | 128MB | 256MB+ |
| Disk Space | 10MB | 50MB+ |
| API Key | Gemini Free | Gemini Pro |

---

**Plugin Version**: v0.1.0
**Last Updated**: 2026-01-12
**Moodle Version**: 4.2+
