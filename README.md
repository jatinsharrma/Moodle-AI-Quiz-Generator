# AI Quiz Generator for Moodle

AI-powered quiz question generator plugin for Moodle 4.0+ using Google Gemini 2.5 Flash.

**Current version:** v1.10.0

## Features

- **AI-Powered Question Generation**: Uses Google Gemini 2.5 Flash to generate multiple-choice questions
- **Source Grounding Verification**: Every question must cite a verbatim quote from your document, which is automatically checked against the extracted text
- **Automatic Replacement of Off-Source Questions**: Questions that can't be traced back to your material are replaced with new ones drawn from the source, rather than merely flagged. Replacements are checked for grounding and against the existing questions so they don't repeat them
- **Primary vs Supporting Documents**: Control question scope with primary documents, use supporting documents for context
- **Native PDF Reading**: If the server can't extract PDF text (no `pdftotext`, or a scanned document), the PDF is sent to Gemini to read directly rather than falling back to guesswork
- **Page Range Extraction**: Extract specific pages from PDFs for focused question generation
- **Multiple File Formats**: Supports PDF, DOCX, PPTX, and website URLs
- **Preview & Edit**: Review and edit questions before importing to question bank
- **Difficulty Levels**: Specify distribution of easy, medium, and hard questions
- **Multiple Answer Questions**: Optional, with negative marking to prevent "select all" exploitation
- **Bounded Repair Loop**: If the AI returns a malformed response, it is asked to correct it (up to 3 attempts total). Failures that retrying cannot fix — content filters, authentication, quota — fail immediately rather than wasting quota

## How Grounding Works

The core guarantee of this plugin is that questions come from *your* document, not from the AI's general knowledge of the subject.

1. Text is extracted from your primary documents.
2. The AI is required to return a `source_quote` for every question — a verbatim span from your material that proves the answer.
3. Each quote is checked against the extracted text. Matching tolerates line wraps, hyphenation and punctuation drift, but rejects paraphrase.
4. **Any question that fails is automatically replaced.** The AI is sent the whole question set, told which ones could not be verified and why, and asked for replacements drawn from the source. The questions being kept go with the request so replacements don't repeat them — and any replacement that is itself unverifiable, malformed, or a reworded copy of an existing question is refused. Up to two replacement rounds.
5. The preview page labels whatever remains:

| Badge | Meaning |
|-------|---------|
| **Verified in source** | A supporting quote was found in your document |
| **Not found in source** | The quote could not be located, and replacement was attempted but did not succeed. Unselected by default |
| **No source quote** | The AI supplied no usable quote, and replacement did not succeed. Unselected by default |
| **Not checked** | The PDF was read directly by Gemini, so there is no local text to check against |

To get **Verified** rather than **Not checked**, the server needs working local text extraction — see `pdftotext` below.

## Directory Structure

```
/home/ai/moodle/
├── README.md                    # This file
├── INSTALL_GUIDE.md             # Installation instructions
├── QUICK_START.txt              # Condensed setup
├── docs/                        # Additional documentation
├── src/local/ai_quiz/           # Main plugin directory
│   ├── version.php              # Plugin metadata
│   ├── settings.php             # Admin settings
│   ├── generate.php             # Main quiz generation page
│   ├── preview.php              # Question preview/edit page
│   ├── classes/
│   │   ├── quiz_generator.php            # Prompt building, API calls, repair loop
│   │   ├── pdf_extractor.php             # Text extraction (never guesses)
│   │   ├── grounding_validator.php       # Verifies questions against the source
│   │   ├── extraction_unavailable.php    # Signals "use native PDF reading"
│   │   ├── response_format_exception.php # Signals "repairable response"
│   │   ├── question_bank_helper.php      # Moodle question bank import
│   │   └── forms/generate_form.php
│   ├── cli/
│   │   └── diagnose.php         # Checks whether PDF text extraction works
│   ├── tests/                   # PHPUnit tests
│   ├── lang/en/                 # Language strings
│   ├── db/install.xml           # Database schema
│   └── amd/src/                 # JavaScript modules
```

## Installation

### Prerequisites

- Moodle 4.0+
- PHP 8.1+
- Google Gemini API key (free tier available)
- **poppler-utils** (strongly recommended — see below)

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

4. **Verify PDF extraction** (see next section)

### Building an installable zip

No prebuilt zip ships with the repository. To create one for upload via
Site Administration → Plugins → Install plugins:

```bash
cd src/local
zip -r ../../release/ai_quiz.zip ai_quiz -x '*.git*'
```

Full instructions: `INSTALL_GUIDE.md`

## pdftotext: Why It Matters

Without local text extraction the plugin still works — PDFs are sent to Gemini to read directly — but **questions cannot be automatically verified against your document**, and every question shows "Not checked" instead of "Verified in source".

Install it:

```bash
sudo apt-get install poppler-utils     # Debian / Ubuntu
sudo dnf install poppler-utils         # RHEL / Rocky / Alma / Fedora
apk add poppler-utils                  # Alpine
```

Installing the package is not always sufficient. PHP must also be allowed to run it — if `exec` appears in your `disable_functions`, `pdftotext` can never be called no matter what is installed. Check the web server's PHP config (Site administration → Server → PHP info), not just the CLI.

Confirm the plugin can actually use it, running **as the web server user**:

```bash
sudo -u www-data php local/ai_quiz/cli/diagnose.php --file=/path/to/lecture.pdf
```

You want `pdftotext usable: yes` and readable text in the preview.

## Usage

### Accessing the Plugin

**From Course Administration:**
1. Go to your course
2. Navigate to: Course administration → AI Quiz Generator

**From Navigation:**
1. Click "AI Quiz Generator" in navigation menu
2. Select a course from dropdown

### Generating Questions

1. **Select Course & Category** — choose target course and question category (or create one)

2. **Upload Primary Documents** (Required)
   - Upload PDF/DOCX/PPTX files — these are the source for questions
   - Optionally specify page ranges:
     ```
     chapter5.pdf: 10-30
     lecture.pdf: 1-15
     ```

3. **Upload Supporting Documents** (Optional) — background context only; questions will *not* come from these

4. **Configure Quiz Settings** — number of questions and difficulty split (Easy / Medium / Hard)

5. **Generate** — takes roughly 30–60 seconds

6. **Preview & Edit**
   - Review each question and its **source quote**
   - Questions flagged **"Not found in source"** are unselected by default — check them against your material before importing
   - Edit text, answers or explanations inline
   - Click "Import to Question Bank"

7. **Use in Quiz** — questions are now in your question bank

### Expect Fewer Questions Sometimes

The AI is instructed to return **fewer questions rather than invent them** when the source material doesn't support the number requested. Asking for 20 and receiving 14 well-grounded questions is correct behaviour, not a fault. A warning explains when this happens.

## Configuration

### API Settings

- **Gemini API Key**: Required for question generation
- **Temperature**: Default `0.2`. This is a grounded extraction task, not a creative one — values above roughly `0.4` measurably increase drift away from the source document

### Rate Limits

Gemini's free tier is rate limited, and the exact limits change over time. Current figures: https://ai.google.dev/gemini-api/docs/rate-limits

Practical guidance:
- Keep primary documents modest (a page range beats a whole textbook)
- Supporting documents consume quota too — leave them empty unless needed
- On "quota exceeded", wait a minute and retry, or reduce the question count

## Troubleshooting

### Diagnostics First

```bash
sudo -u www-data php local/ai_quiz/cli/diagnose.php --file=/path/to/lecture.pdf
```

This reports whether `exec()` is available, whether `pdftotext` is usable, whether the API key is set, and whether a given PDF actually yields text.

### Common Issues

**Questions aren't from my document**

This is what the grounding badges are for. If questions show "Not found in source", they were probably written from general knowledge. Run the diagnostic — if `pdftotext usable: NO`, the server can't read your PDF locally and can't verify the questions.

**"The AI ran out of output space…"**

The response was cut off before finishing. Generate fewer questions at a time (10 instead of 20), then run again for the rest. The plugin retries automatically with a smaller request, but very large asks can still exhaust the budget.

**"Quiz generation was stopped because a primary document could not be read"**

Deliberate. Generating from only part of your material would produce a quiz that isn't from your document. The message names the file and the reason.

**"The AI provider rejected the request…" / content filter**

A safety filter triggered on your document or the generated content. Not retryable — try a different section.

**"API quota exceeded"**

Rate limit reached. Wait a minute, reduce question count, or upgrade at https://aistudio.google.com/

**"No categories available"**

Select a course from the dropdown first; categories load automatically.

**"PDF extraction failed" / "no selectable text"**

The PDF is likely scanned. The plugin will send it to Gemini to read directly — questions will work but show "Not checked".

### Enable Debug Mode

```bash
sudo -u www-data php /var/www/html/moodle/admin/cli/cfg.php --name=debug --set=32767
sudo -u www-data php /var/www/html/moodle/admin/cli/cfg.php --name=debugdisplay --set=1
```

Disable display in production:

```bash
sudo -u www-data php /var/www/html/moodle/admin/cli/cfg.php --name=debugdisplay --set=0
```

## Technical Details

### Model

- **Gemini 2.5 Flash** — fast, available on the free tier
- Temperature: `0.2` (configurable)
- Response format: JSON
- `maxOutputTokens`: 32768. Gemini 2.5 Flash reasons before answering; without an explicit ceiling a long question set can exhaust the output budget and return an empty response

### Question Format

- Type: Multiple choice (single or multiple answer)
- Options: 4 (A, B, C, D)
- Shuffled answers: Yes
- Includes explanations and a source quote
- Multiple answer questions use negative marking

### File Processing

- **PDF**: `pdftotext` with page range support; falls back to native Gemini reading
- **DOCX**: Extracts text from `document.xml`, preserving word boundaries
- **PPTX**: Extracts text from slide content
- **Websites**: Fetches and processes HTML content (not thoroughly tested)

### Security

- Files are written to a per-request temporary directory and deleted after processing, including on failure
- Temporary questions stored for 24 hours
- No permanent file storage
- API key stored in Moodle config

## Development

### Running Tests

```bash
vendor/bin/phpunit --filter local_ai_quiz
```

Covers PDF text validation, source grounding, API response handling, and the repair loop.

### Version History

- **v1.9.0**: Grounding verification, native PDF reading, bounded repair loop, diagnostics
- **v1.7.7**: Prompt tuning to avoid document-referencing questions
- **v1.7.1**: Question import fixes, multiple answer controls
- **v1.0.4**: Fixed question bank import format
- **v1.0.3**: Fixed course context detection
- **v1.0.1**: Updated to Gemini 2.5 Flash
- **v1.0.0**: Initial release

## License

GPL v3 or later

## Credits

Built for Moodle 4.0+ using Google Gemini 2.5 Flash API.
