# AI Quiz Generator for Moodle

AI-powered quiz question generator plugin for Moodle 4.0+ using Google Gemini 2.5 Flash.

## Features

- **AI-Powered Question Generation**: Uses Google Gemini 2.5 Flash to generate multiple-choice questions
- **Primary vs Supporting Documents**: Control question scope with primary documents, use supporting documents for context
- **Page Range Extraction**: Extract specific pages from PDFs for focused question generation
- **Multiple File Formats**: Supports PDF, DOCX, PPTX, and website URLs
- **Preview & Edit**: Review and edit questions before importing to question bank
- **Difficulty Levels**: Specify distribution of easy, medium, and hard questions

## Directory Structure

```
/home/ai/moodle/
├── README.md                    # This file
├── .gitignore                   # Git ignore rules
├── src/                         # Source code
│   └── local/
│       └── ai_quiz/            # Main plugin directory
│           ├── version.php     # Plugin metadata
│           ├── settings.php    # Admin settings
│           ├── generate.php    # Main quiz generation page
│           ├── preview.php     # Question preview/edit page
│           ├── classes/        # PHP classes
│           │   ├── quiz_generator.php
│           │   ├── pdf_extractor.php
│           │   ├── question_bank_helper.php
│           │   └── forms/
│           │       └── generate_form.php
│           ├── lang/           # Language strings
│           │   └── en/
│           │       └── local_ai_quiz.php
│           ├── db/             # Database schema
│           │   └── install.xml
│           └── amd/            # JavaScript modules
│
```

## Installation

### Prerequisites
- Moodle 4.0+
- PHP 8.1+ (recommended)
- Google Gemini API key (free tier available)
- pdftotext (optional, for PDF extraction): `apt-get install poppler-utils`

### Steps

1. **Copy plugin to Moodle:**
   ```bash
   sudo cp -r src/local/ai_quiz /var/www/html/moodle/local/
   sudo chown -R www-data:www-data /var/www/html/moodle/local/ai_quiz
   ```

2. **Install plugin:**
   - Visit: Site Administration → Notifications
   - Click "Upgrade Moodle database now"

3. **Configure API key:**
   - Go to: Site Administration → Plugins → Local plugins → AI Quiz Generator
   - Enter your Google Gemini API key
   - Get a key at: https://makersuite.google.com/app/apikey

## Usage

### Accessing the Plugin

**From Course Administration:**
1. Go to your course
2. Navigate to: Course administration → AI Quiz Generator
3. Form opens with course and categories pre-loaded

**From Navigation:**
1. Click "AI Quiz Generator" in navigation menu
2. Select a course from dropdown
3. Categories will load automatically

### Generating Questions

1. **Select Course & Category**
   - Choose target course
   - Select question category (or create new one)

2. **Upload Primary Documents** (Required)
   - Upload PDF/DOCX/PPTX files
   - These are the source for questions
   - Optionally specify page ranges:
     ```
     chapter5.pdf: 10-30
     lecture.pdf: 1-15
     ```

3. **Upload Supporting Documents** (Optional)
   - Add glossaries, references, background materials
   - Questions will NOT come from these
   - Used by AI for context and terminology

4. **Configure Quiz Settings**
   - Number of questions: 20 (default)
   - Difficulty distribution:
     - Easy: 5 (recall, definitions)
     - Medium: 10 (understanding, application)
     - Hard: 5 (analysis, synthesis)

5. **Generate**
   - Click "Generate Quiz"
   - Wait for AI to process (may take 30-60 seconds)

6. **Preview & Edit**
   - Review generated questions
   - Edit question text, answers, or explanations
   - Select which questions to import
   - Click "Import to Question Bank"

7. **Use in Quiz**
   - Questions are now in your question bank
   - Add them to any quiz activity

## Configuration

### API Settings

- **Gemini API Key**: Required for question generation
- **Temperature**: 0.7 (default) - Controls AI creativity (0.0-1.0)

### Rate Limits

**Free Tier (Gemini 2.5 Flash):**
- 15 requests per minute
- 1 million tokens per minute
- 1,500 requests per day

For higher limits, upgrade at: https://aistudio.google.com/

## Troubleshooting

### Testing API Key

```bash
php test_gemini_api.php YOUR_API_KEY
```

### Common Issues

**"API quota exceeded"**
- You've hit rate limits
- Wait 1 minute and try again
- Reduce number of questions
- Or upgrade to paid tier

**"No categories available"**
- Select a course from dropdown first
- Categories will load automatically

**"0 questions imported"**
- Enable debugging: Site Administration → Development → Debugging
- Set to "DEVELOPER" level
- Try import again to see detailed errors

**"PDF extraction failed"**
- Install poppler-utils: `apt-get install poppler-utils`
- Or use DOCX/PPTX files instead

### Enable Debug Mode

```bash
sudo -u www-data php /var/www/html/moodle/admin/cli/cfg.php --name=debug --set=32767
sudo -u www-data php /var/www/html/moodle/admin/cli/cfg.php --name=debugdisplay --set=1
```

### Disable Debug Display (Production)

```bash
sudo -u www-data php /var/www/html/moodle/admin/cli/cfg.php --name=debugdisplay --set=0
```

## Technical Details

### Models Used

- **Gemini 2.5 Flash**: Fast, efficient, available on free tier
- Temperature: 0.7
- Response format: JSON

### Question Format

- Type: Multiple choice (single answer)
- Options: 4 (A, B, C, D)
- Shuffled answers: Yes
- Includes explanations

### File Processing

- **PDF**: Uses pdftotext with page range support
- **DOCX**: Extracts text from document.xml
- **PPTX**: Extracts text from slide content
- **Websites**: Fetches and processes HTML content

### Security

- Files deleted immediately after processing
- Temporary questions stored for 24 hours
- No permanent file storage
- API key stored encrypted in Moodle config

## Development

### Version History

- **v1.0.4** (2024-01-14): Fixed question bank import format
- **v1.0.3** (2024-01-14): Fixed course context detection
- **v1.0.2** (2024-01-14): Fixed question bank import
- **v1.0.1** (2024-01-14): Updated to Gemini 2.5 Flash
- **v1.0.0** (2024-01-12): Initial release

### Testing

Run PHP 8.1 compatibility check:
```bash
bash test_php81_compat.sh
```

## License

GPL v3 or later

## Credits

Built for Moodle 4.0+ using Google Gemini 2.5 Flash API.

## Download & Installation

### Quick Install

**Download:** Get `ai_quiz.zip` from the `release/` folder

**Install:** 
1. Log in to Moodle as admin
2. Go to: Site Administration → Plugins → Install plugins
3. Upload `ai_quiz.zip`
4. Follow the wizard
5. Configure API key in plugin settings

**Full Instructions:** See `INSTALL_GUIDE.md` or `release/INSTALL_GUIDE.md`

