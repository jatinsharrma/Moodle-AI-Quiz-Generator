# Primary vs Supporting Documents - Complete Guide

## Overview

The AI Quiz Generator uses a **primary/supporting document architecture** to ensure questions come from the right sources while still benefiting from contextual information.

## Concept

### Primary Documents (Required)
**Purpose:** Define the scope and boundary for quiz questions

- **Questions MUST come from these documents**
- Set the learning objectives and coverage
- Typically: lecture slides, textbook chapters assigned for the quiz
- **At least one primary document is required**

### Supporting Documents (Optional)
**Purpose:** Provide additional context without being the question source

- Questions do NOT come from these
- Used by AI for understanding terminology, background, definitions
- Help AI generate better distractors and explanations
- Typically: glossaries, reference materials, supplementary readings

## Why This Design?

### Problem Without It
If all documents were treated equally:
- Questions might come from reference materials instead of assigned content
- No control over quiz scope
- Can't align with learning objectives

### Solution
Clear boundary:
- Teacher assigns "Chapter 5" as primary → questions from Chapter 5 only
- Adds glossary as supporting → AI understands terms but doesn't quiz on glossary

## Real-World Example

**Scenario:** Teaching photosynthesis (Chapter 8 of biology textbook)

**Primary Documents:**
- `biology_textbook_chapter8.pdf` (pages 150-175)
  - This is what students must learn
  - Questions will test this content

**Supporting Documents:**
- `biology_glossary.pdf` (entire document)
  - Definitions of scientific terms
  - Helps AI understand terminology
  - NOT a source of questions
- `photosynthesis_diagram.pdf` (pages 1-3)
  - Visual reference
  - Helps AI understand concepts
  - NOT tested directly

**Result:**
- All 20 questions come from Chapter 8 (pages 150-175)
- Glossary helps AI create better distractors using correct terminology
- Diagrams help AI understand the process to write clearer questions

## Page Range Feature

### Why Page Ranges?
- Large textbooks (500+ pages) → only need 1 chapter
- Reduces token usage
- Faster processing
- More focused questions

### How to Specify

**Format:**
```
filename.pdf: start-end
```

**Examples:**
```
biology_textbook.pdf: 150-175
lecture_slides.pdf: 10-25
chapter5.pdf: 1-30
```

**Rules:**
- One file per line
- Colon separates filename from range
- Pages are 1-indexed (first page = page 1)
- Spaces around colon and dash are optional
- If no range specified → entire document used

### In the UI

**Primary Documents Section:**
1. Upload files using file picker
2. (Optional) In "Page Ranges" textarea, specify ranges:
   ```
   lecture1.pdf: 5-20
   textbook.pdf: 100-120
   ```
3. If no ranges specified → full documents used

**Supporting Documents Section:**
Same process, but for supporting materials.

## Technical Implementation

### Document Processing Flow

```
User uploads:
  Primary: chapter5.pdf (pages 10-30)
  Supporting: glossary.pdf (all pages)
           ↓
Extract text:
  chapter5.pdf → Extract pages 10-30 only using pdftotext
  glossary.pdf → Extract all pages
           ↓
Build context:
  PRIMARY SOURCE MATERIALS (questions must come from these):
  === PRIMARY DOCUMENT: chapter5.pdf (pages 10-30) ===
  [extracted text from pages 10-30]

  ================================================================================

  SUPPORTING MATERIALS (for context/reference only):
  === SUPPORTING DOCUMENT: glossary.pdf ===
  [extracted text from glossary]
           ↓
Send to Gemini with prompt:
  CRITICAL SCOPE RESTRICTION:
  - Generate questions ONLY from PRIMARY SOURCE MATERIALS
  - Supporting materials are for context/reference only
  - Do NOT create questions from supporting documents
           ↓
AI generates questions from primary docs only
```

### Code Structure

**Form (generate_form.php):**
- Two separate file upload sections
- Two page range text areas
- Validation: primary documents required

**Processing (generate.php):**
- Parse page range input → map filename to {'from': int, 'to': int}
- Process primary docs with page ranges
- Process supporting docs with page ranges
- Pass to generator

**PDF Extraction (pdf_extractor.php):**
- Uses `pdftotext -f start -t end` for page ranges
- Falls back to full extraction if pdftotext unavailable
- Validates page ranges (start >= 1, end >= start)

**Quiz Generator (quiz_generator.php):**
- Accepts separate primary/supporting document arrays
- Labels context clearly (PRIMARY vs SUPPORTING)
- Adds "CRITICAL SCOPE RESTRICTION" to prompt
- AI enforces primary-only question generation

## User Interface

### Form Layout

```
┌──────────────────────────────────────────────────────┐
│ AI Quiz Generator                                     │
├──────────────────────────────────────────────────────┤
│                                                       │
│ Course: [Biology 101 ▼]                              │
│ Category: [Chapter 8 Questions ▼]                    │
│                                                       │
│ ┌─ Primary Documents (Required) ─────────────────┐  │
│ │                                                  │  │
│ │ ℹ️ Primary documents set the scope and boundary │  │
│ │   for quiz questions. All questions will be     │  │
│ │   generated from these materials.               │  │
│ │                                                  │  │
│ │ Upload Primary Documents:                        │  │
│ │ [Choose files] (PDF, DOCX, PPTX, max 10MB)      │  │
│ │                                                  │  │
│ │ Page Ranges (Optional):                          │  │
│ │ ┌─────────────────────────────────────────────┐ │  │
│ │ │ chapter5.pdf: 10-30                         │ │  │
│ │ │ lecture.pdf: 1-15                           │ │  │
│ │ └─────────────────────────────────────────────┘ │  │
│ │ Format: filename.pdf: 10-20                     │  │
│ └──────────────────────────────────────────────────┘  │
│                                                       │
│ ┌─ Supporting Documents (Optional) ───────────────┐  │
│ │                                                  │  │
│ │ ℹ️ Supporting documents provide additional      │  │
│ │   context but questions will still come from   │  │
│ │   primary documents. Use for reference          │  │
│ │   materials, definitions, or background info.  │  │
│ │                                                  │  │
│ │ Upload Supporting Documents:                     │  │
│ │ [Choose files] (PDF, DOCX, PPTX, max 10MB)      │  │
│ │                                                  │  │
│ │ Page Ranges (Optional):                          │  │
│ │ ┌─────────────────────────────────────────────┐ │  │
│ │ │ glossary.pdf: 50-75                         │ │  │
│ │ └─────────────────────────────────────────────┘ │  │
│ └──────────────────────────────────────────────────┘  │
│                                                       │
│ ┌─ Website URLs (Optional) ───────────────────────┐  │
│ │ Supporting Website URLs:                         │  │
│ │ ┌─────────────────────────────────────────────┐ │  │
│ │ │ https://biology.example.com/photosynthesis  │ │  │
│ │ └─────────────────────────────────────────────┘ │  │
│ └──────────────────────────────────────────────────┘  │
│                                                       │
│ ┌─ Quiz Settings ──────────────────────────────────┐  │
│ │ Number of Questions: [20]                        │  │
│ │ Easy: [5]  Medium: [10]  Hard: [5]               │  │
│ └──────────────────────────────────────────────────┘  │
│                                                       │
│ [Generate Quiz]  [Cancel]                            │
└──────────────────────────────────────────────────────┘
```

## Best Practices

### For Teachers

1. **Primary Documents:**
   - Upload exactly what students should study
   - Use page ranges to focus on specific chapters/sections
   - Keep it focused (1-3 documents typical)

2. **Supporting Documents:**
   - Add glossaries for technical terms
   - Add reference materials for context
   - Add background readings
   - More is okay here (helps AI understand)

3. **Page Ranges:**
   - Always specify for large files (>50 pages)
   - Match assigned reading exactly
   - Verify page numbers match PDF pages (not book page numbers)

### Examples

**Good Practice:**
```
Primary:
  - biology_ch8.pdf: 150-175 (assigned reading)

Supporting:
  - biology_glossary.pdf: all
  - cell_diagrams.pdf: 1-10
```

**Bad Practice:**
```
Primary:
  - entire_textbook.pdf: all (too broad)

Supporting:
  - (nothing - AI lacks context)
```

## Troubleshooting

### "Questions coming from wrong content"
- Check that correct files are in PRIMARY section
- Verify page ranges are correct
- Supporting docs might be too detailed

### "AI doesn't understand terminology"
- Add glossary as supporting document
- Add reference materials for context

### "Page extraction failed"
- Install pdftotext: `apt-get install poppler-utils`
- Verify page numbers are valid (1-indexed)
- Try without page ranges first

### "Context too large error"
- Use page ranges to reduce content
- Split large documents into smaller files
- Remove unnecessary supporting documents

## File Size Limits

- **Max file size:** 10MB per file
- **Max files:** 10 primary + 10 supporting = 20 total
- **Supported formats:** PDF, DOCX, PPTX
- **Page extraction:** PDF only (DOCX/PPTX always use full file)

## Summary

| Aspect | Primary | Supporting |
|--------|---------|------------|
| **Purpose** | Source of questions | Additional context |
| **Required** | Yes | No |
| **Questions from** | ✅ Yes | ❌ No |
| **Page ranges** | ✅ Supported | ✅ Supported |
| **Typical use** | Assigned readings | Glossaries, references |
| **Limit** | 10 files | 10 files |

---

**Key Takeaway:** Primary documents define WHAT is tested, supporting documents help AI understand HOW to test it better.

**Version:** 0.1.0
**Last Updated:** 2026-01-12
