# AI Quiz Generator for Moodle

A Moodle plugin that uses AI (Google Gemini 2.5 Pro) to automatically generate multiple-choice quiz questions from uploaded documents and websites.

## Features

- Generate quiz questions from PDF, DOCX, and PPTX files
- Extract content from websites for quiz generation
- Configure difficulty distribution (easy, medium, hard)
- Automatic import to Moodle question bank
- Support for multiple AI providers (OpenAI and Claude coming soon)
- Customizable number of questions and difficulty mix

## Requirements

- Moodle 4.2 or later
- PHP 7.4 or later
- Google Gemini API key
- `pdftotext` utility for PDF processing (optional but recommended)

## Installation

1. Copy the plugin to your Moodle installation:
   ```bash
   cp -r src/local/ai_quiz /path/to/moodle/local/
   ```

2. Visit Site Administration → Notifications to complete the installation

3. Configure your API key:
   - Go to Site Administration → Plugins → Local plugins → AI Quiz Generator
   - Enter your Google Gemini API key
   - (Get an API key at: https://makersuite.google.com/app/apikey)

## Usage

### For Teachers

1. Navigate to your course
2. Click on "AI Quiz Generator" in the navigation menu
3. Select the course and question category
4. Upload documents (PDF, DOCX, PPTX) or enter website URLs
5. Configure the number of questions and difficulty distribution
6. Click "Generate Quiz"
7. Questions will be imported to your question bank

### For Administrators

Configure the plugin settings at:
**Site Administration → Plugins → Local plugins → AI Quiz Generator**

Settings include:
- AI provider API keys (Gemini, OpenAI, Claude)
- Default AI provider
- Default number of questions
- Temperature setting for AI generation

## API Providers

### Current Support
- **Google Gemini 2.5 Pro** ✓ Fully supported

### Coming Soon
- **OpenAI GPT-4** - In development
- **Anthropic Claude** - In development

## File Format Support

- **PDF**: Text extraction using pdftotext
- **DOCX**: Native ZIP-based text extraction
- **PPTX**: Native ZIP-based text extraction
- **Websites**: HTML content extraction

## Question Generation

The plugin generates questions with:
- 4 multiple-choice options (A, B, C, D)
- Configurable difficulty levels (easy, medium, hard)
- Automatic categorization by topic
- Explanations for correct answers
- Validation before import

## Permissions

Two capabilities are defined:
- `local/ai_quiz:generate` - Generate quiz questions (Teacher, Manager)
- `local/ai_quiz:manage` - Manage plugin settings (Manager)

## Development Roadmap

### Phase 1 (Current)
- [x] Basic plugin structure
- [x] Gemini 2.5 Pro integration
- [x] Document processing (PDF, DOCX, PPTX)
- [x] Website content extraction
- [x] Question bank integration
- [x] Settings page for API keys

### Phase 2 (Coming Soon)
- [ ] OpenAI GPT-4 support
- [ ] Anthropic Claude support
- [ ] User-level API key management (BYOK - Bring Your Own Key)
- [ ] Advanced document processing with Docling
- [ ] Better website content extraction with Trafilatura
- [ ] Question preview before import
- [ ] Bulk editing of generated questions

### Phase 3 (Future)
- [ ] Video support (transcript generation and processing)
- [ ] Question type expansion (True/False, Short Answer, Essay)
- [ ] Learning analytics integration
- [ ] Custom prompt templates
- [ ] Question quality scoring
- [ ] Batch processing for multiple courses

## Technical Architecture

```
local_ai_quiz/
├── classes/
│   ├── quiz_generator.php          # Core AI generation logic
│   ├── question_bank_helper.php    # Moodle question bank integration
│   └── forms/
│       └── generate_form.php       # Upload and configuration form
├── db/
│   └── access.php                  # Capability definitions
├── lang/
│   └── en/
│       └── local_ai_quiz.php       # Language strings
├── generate.php                     # Main page
├── lib.php                         # Moodle hooks and navigation
├── settings.php                    # Admin settings
├── version.php                     # Plugin metadata
└── README.md                       # This file
```

## Privacy

The plugin does not store any personal data. Uploaded files are:
- Processed temporarily
- Sent to AI provider API (Gemini)
- Deleted immediately after processing

## License

GPL v3 or later

## Support

For issues, feature requests, or contributions, please contact the development team.

## Credits

- Original Colab implementation adapted for Moodle
- Uses Google Gemini 2.5 Pro API
- Designed for Moodle 4.2+

## Version History

### v0.1.0 (2026-01-12)
- Initial release
- Gemini 2.5 Pro support
- Basic document processing
- Question bank integration
