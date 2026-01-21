# AI Quiz Generator - Installation Guide

## Quick Install (via Moodle Web Interface)

### Step 1: Upload Plugin

1. **Download the plugin zip file**: `ai_quiz.zip`

2. **Log in to Moodle as Administrator**

3. **Navigate to plugin installation page:**
   - Go to: **Site Administration** → **Plugins** → **Install plugins**

4. **Upload the zip file:**
   - Click "Choose a file"
   - Select `ai_quiz.zip`
   - Click "Install plugin from the ZIP file"

5. **Confirm plugin type:**
   - Plugin type: `Local plugin (local)`
   - Plugin name: `ai_quiz`
   - Click "Continue"

6. **Review and install:**
   - Review the plugin information
   - Click "Continue"
   - Click "Upgrade Moodle database now"

7. **Complete installation:**
   - Wait for installation to complete
   - Click "Continue"

### Step 2: Configure API Key

1. **Navigate to plugin settings:**
   - Go to: **Site Administration** → **Plugins** → **Local plugins** → **AI Quiz Generator**

2. **Enter your Gemini API Key:**
   - Get a free API key at: https://makersuite.google.com/app/apikey
   - Paste the key in "Google Gemini API Key" field
   - Click "Save changes"

### Step 3: Test Installation

1. **Go to any course**

2. **Access the plugin:**
   - Method 1: Navigate to **Course administration** → **AI Quiz Generator**
   - Method 2: Click **AI Quiz Generator** in the navigation menu

3. **Generate a test quiz:**
   - Upload a small PDF (1-2 pages)
   - Set questions: 5
   - Click "Generate Quiz"

4. **If successful:**
   - You'll see the preview page with generated questions
   - Questions can be imported to question bank

---

## Manual Installation (via Server)

### Prerequisites

- SSH/terminal access to server
- Root or sudo privileges
- Moodle 4.0+ installed
- PHP 8.1+ (recommended)

### Step 1: Upload Plugin Files

**Option A: Using the zip file**
```bash
# Upload ai_quiz.zip to server, then:
cd /var/www/html/moodle/local/
sudo unzip /path/to/ai_quiz.zip
sudo chown -R www-data:www-data ai_quiz
```

**Option B: Copy from development directory**
```bash
sudo cp -r /home/ai/moodle/src/local/ai_quiz /var/www/html/moodle/local/
sudo chown -R www-data:www-data /var/www/html/moodle/local/ai_quiz
```

### Step 2: Verify File Permissions

```bash
# Check ownership
ls -la /var/www/html/moodle/local/ | grep ai_quiz

# Should show: drwxr-xr-x www-data www-data ai_quiz
```

### Step 3: Install via Web Interface

1. Visit your Moodle site as administrator
2. You'll see: "New plugins have been detected"
3. Click "Upgrade Moodle database now"
4. Follow on-screen instructions

### Step 4: Configure Settings

Same as "Quick Install - Step 2" above.

---

## Post-Installation

### Optional: Install PDF Tools (Recommended)

For better PDF extraction with page ranges:

**Ubuntu/Debian:**
```bash
sudo apt-get update
sudo apt-get install poppler-utils
```

**CentOS/RHEL:**
```bash
sudo yum install poppler-utils
```

**Test PDF extraction:**
```bash
which pdftotext
# Should output: /usr/bin/pdftotext
```

### Optional: Enable Debugging (for troubleshooting)

```bash
sudo -u www-data php /var/www/html/moodle/admin/cli/cfg.php --name=debug --set=32767
sudo -u www-data php /var/www/html/moodle/admin/cli/cfg.php --name=debugdisplay --set=1
```

**Disable after testing:**
```bash
sudo -u www-data php /var/www/html/moodle/admin/cli/cfg.php --name=debugdisplay --set=0
```

---

## Verification Checklist

After installation, verify:

- [ ] Plugin appears in: Site Administration → Plugins → Local plugins
- [ ] Settings page accessible
- [ ] API key configured
- [ ] Link appears in course administration menu
- [ ] Can upload documents and generate questions
- [ ] Questions import to question bank successfully

---

## Troubleshooting

### "Plugin validation failed"

**Cause:** Incorrect plugin structure in zip file

**Solution:**
- Ensure zip contains `ai_quiz/` folder at root level
- Re-download the zip file
- Or use manual installation method

### "Dependency check failed"

**Cause:** Version number or requires field issue

**Solution:**
```bash
# Check version.php
cat /var/www/html/moodle/local/ai_quiz/version.php | grep version

# Should show version like: 2024011405
# NOT a future date like: 2026011405
```

### "API quota exceeded"

**Cause:** Using Gemini 2.5 Pro on free tier (not available)

**Solution:**
- Plugin uses Gemini 2.5 Flash (available on free tier)
- Verify in settings or code
- Check: `/var/www/html/moodle/local/ai_quiz/classes/quiz_generator.php`
- Should say: `gemini-2.5-flash`

### "No categories available"

**Cause:** No course context detected

**Solution:**
- Select a course from the dropdown first
- Or access from Course Administration (within a course)
- Categories will load automatically

### "Failed to import questions"

**Cause:** Question format issue

**Solution:**
1. Enable debugging (see above)
2. Try importing again
3. Check error messages for details
4. Report issue with error details

---

## Uninstallation

### Via Web Interface

1. Go to: **Site Administration** → **Plugins** → **Plugins overview**
2. Find "AI Quiz Generator" in Local plugins section
3. Click "Uninstall"
4. Confirm uninstallation
5. Database tables will be cleaned up automatically

### Manual Uninstall

```bash
# Remove plugin files
sudo rm -rf /var/www/html/moodle/local/ai_quiz

# Visit Moodle as admin to complete database cleanup
# Site Administration → Notifications
```

---

## Support

- **Documentation**: See `docs/` folder in repository
- **Issues**: Report on GitHub
- **Testing**: Use `test_gemini_api.php` script

---

## Version Information

- **Current Version**: 1.0.4
- **Release Date**: 2026-01-14
- **Moodle Version**: 4.0+
- **PHP Version**: 8.0+ (8.1+ recommended)
- **License**: GPL v3 or later
