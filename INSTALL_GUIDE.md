# AI Quiz Generator - Installation Guide

## Quick Install (via Moodle Web Interface)

> **Note:** the repository does not ship a prebuilt `ai_quiz.zip`. Build one
> first, or skip to *Manual Installation* below and copy the directory directly.
>
> ```bash
> cd src/local
> zip -r ../../release/ai_quiz.zip ai_quiz -x '*.git*'
> ```
>
> The zip must contain an `ai_quiz/` folder at its root, which this produces.

### Step 1: Upload Plugin

1. **Build or obtain the plugin zip file**: `ai_quiz.zip`

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
- PHP 8.1+

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

### Install PDF Tools (Strongly Recommended)

The plugin works without these, but **it cannot verify that generated questions
actually came from your document**. With `pdftotext` available, text is extracted
locally and every question is checked against it. Without it, PDFs are sent to
Gemini to read directly and questions are marked "Not checked".

**Ubuntu/Debian:**
```bash
sudo apt-get update
sudo apt-get install poppler-utils
```

**RHEL / Rocky / Alma / Fedora:**
```bash
sudo dnf install poppler-utils
```

**Alpine (Docker):**
```bash
apk add poppler-utils
```

If Moodle runs in a container, install it **inside the container** — add it to
your Dockerfile or it disappears on the next rebuild.

#### Installing the package is not always enough

PHP must also be permitted to run it. Two things commonly block this:

```bash
# 1. Is the binary visible to the WEB SERVER user, not just to root?
sudo -u www-data which pdftotext        # Debian/Ubuntu
sudo -u apache which pdftotext          # RHEL/Rocky/Alma

# 2. Is exec() disabled in PHP?
php -i | grep disable_functions
```

If `disable_functions` includes `exec`, remove it from `php.ini` (locate the file
with `php --ini`) and restart PHP:

```bash
sudo systemctl restart php8.1-fpm    # or php-fpm, apache2, httpd
```

Note the PHP CLI and the web server often use **different** `php.ini` files. The
one that matters is shown in Moodle under
**Site administration → Server → PHP info**.

#### Confirm with the plugin's own diagnostic

Run this **as the web server user**:

```bash
sudo -u www-data php /var/www/html/moodle/local/ai_quiz/cli/diagnose.php \
    --file=/path/to/a-real-lecture.pdf
```

It reports whether `exec()` is available, whether `pdftotext` is usable, whether
the API key is configured, the temperature setting, and whether a given PDF
actually yields readable text. You want `pdftotext usable: yes`.

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
- [ ] `cli/diagnose.php` reports `pdftotext usable: yes`
- [ ] Can upload documents and generate questions
- [ ] Generated questions show green **"Verified in source"** badges
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

# Should show: 2026090301
```

The version must be *higher* than the one already installed, or Moodle will
refuse to upgrade. If you are reinstalling over an older copy, confirm the
number increased.

### "API quota exceeded"

**Cause:** Gemini free tier rate limit reached.

**Solution:**
- Wait a minute and try again
- Reduce the number of questions, or use a page range to shrink the document
- Leave Supporting Documents empty - they consume quota too
- Current limits: https://ai.google.dev/gemini-api/docs/rate-limits

### "Questions are not from my document"

**Cause:** The server cannot extract text from the PDF, so it cannot be verified.

**Solution:**
1. Run `cli/diagnose.php` as the web server user
2. If it reports `pdftotext usable: NO`, fix that first (see Post-Installation)
3. Check the preview badges - "Not found in source" means the question could not
   be traced back to your material and should be reviewed before importing

### "The AI ran out of output space..."

**Cause:** The response was cut off before the question set was complete.

**Solution:** Generate fewer questions per run (10 rather than 20). The plugin
retries automatically with a smaller request, but very large asks can still
exhaust the budget.

### "Quiz generation was stopped because a primary document could not be read"

**Cause:** One of your primary documents failed to process.

This is deliberate: generating from only part of your material would produce a
quiz that is not based on your document. The message names the file and the
reason - fix that file, or remove it and generate again.

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
- **Diagnostics**: Run `local/ai_quiz/cli/diagnose.php` as the web server user
- **Tests**: `vendor/bin/phpunit --filter local_ai_quiz`

---

## Version Information

- **Current Version**: 1.10.0 (`2026090301`)
- **Moodle Version**: 4.0+
- **PHP Version**: 8.1+
- **License**: GPL v3 or later
