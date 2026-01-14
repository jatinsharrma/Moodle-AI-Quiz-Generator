# AI Quiz Generator - Complete Workflow

This document explains how questions flow through the system from generation to quiz creation.

## System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     AI QUIZ GENERATOR WORKFLOW                   │
└─────────────────────────────────────────────────────────────────┘

Step 1: GENERATE
┌──────────────────────────────────────────────────────────┐
│ Teacher uploads documents (PDF/DOCX/PPTX) or URLs       │
│ Configures: # questions, difficulty mix                 │
└────────────────────┬─────────────────────────────────────┘
                     │
                     v
┌──────────────────────────────────────────────────────────┐
│ AI Processing (Gemini 2.5 Pro)                          │
│ - Extract text from documents                           │
│ - Generate MCQ questions with options                   │
│ - Return JSON with questions                            │
└────────────────────┬─────────────────────────────────────┘
                     │
                     v
┌──────────────────────────────────────────────────────────┐
│ Temporary Storage (local_ai_quiz_temp table)            │
│ - Store questions with sessionkey                       │
│ - Auto-cleanup after 24 hours                           │
└────────────────────┬─────────────────────────────────────┘
                     │
                     v
Step 2: PREVIEW & EDIT
┌──────────────────────────────────────────────────────────┐
│ Preview Page (preview.php)                               │
│ Teacher can:                                             │
│ ✓ Review all generated questions                        │
│ ✓ Edit question text (click to edit)                    │
│ ✓ Edit answer options (click to edit)                   │
│ ✓ Change correct answer (select radio button)           │
│ ✓ Delete unwanted questions                             │
│ ✓ Select which questions to import (checkboxes)         │
└────────────────────┬─────────────────────────────────────┘
                     │
                     v
Step 3: IMPORT TO QUESTION BANK
┌──────────────────────────────────────────────────────────┐
│ Import Selected Questions                                │
│ - Only checked questions are imported                    │
│ - Saved to Moodle question_categories table              │
│ - Temp storage record deleted                            │
└────────────────────┬─────────────────────────────────────┘
                     │
                     v
┌──────────────────────────────────────────────────────────┐
│ MOODLE QUESTION BANK                                     │
│ Central repository for all questions                     │
│ - Organized by categories                               │
│ - Can be edited further in Moodle                       │
│ - Can be tagged, versioned, etc.                        │
└────────────────────┬─────────────────────────────────────┘
                     │
                     v
Step 4: CREATE QUIZ (Standard Moodle)
┌──────────────────────────────────────────────────────────┐
│ Teacher Creates Quiz Activity                            │
│ - Add activity → Quiz                                    │
│ - Click "Edit quiz"                                      │
│ - Click "Add" → "from question bank"                    │
│ - Select questions from AI-generated category           │
│ - Configure quiz settings (time, attempts, etc.)        │
└────────────────────┬─────────────────────────────────────┘
                     │
                     v
┌──────────────────────────────────────────────────────────┐
│ LIVE QUIZ                                                │
│ Students take the quiz                                   │
│ Moodle handles grading automatically                     │
└──────────────────────────────────────────────────────────┘
```

## Detailed Step-by-Step Process

### Phase 1: Generation

**File:** `generate.php`

1. Teacher navigates to course → "AI Quiz Generator"
2. Fills form:
   - Select course and question category
   - Upload documents (PDF, DOCX, PPTX) OR enter website URLs
   - Set total questions (e.g., 20)
   - Set difficulty distribution (e.g., 5 easy, 10 medium, 5 hard)
3. Clicks "Generate Quiz"
4. Backend processes:
   ```
   Documents → Text Extraction → Send to Gemini API
   → Receive JSON with questions → Store in temp table
   → Redirect to preview page
   ```

**Database Table:** `local_ai_quiz_temp`
```sql
CREATE TABLE local_ai_quiz_temp (
    id INT,
    userid INT,
    courseid INT,
    categoryid INT,
    sessionkey VARCHAR(64),  -- Unique identifier
    questiondata TEXT,        -- JSON with all questions
    timecreated INT
);
```

### Phase 2: Preview & Edit

**File:** `preview.php`

Teacher lands on preview page with all generated questions displayed.

**Interactive Features:**

1. **View Questions:**
   - All questions shown with checkboxes (all selected by default)
   - Difficulty badges (easy/medium/hard)
   - Topic tags
   - Correct answer highlighted in green

2. **Edit Questions (Inline):**
   - Click on question text → Edit directly
   - Click on option text → Edit directly
   - Changes auto-save on blur (AJAX)
   - Visual feedback (green flash) on save

3. **Change Correct Answer:**
   - Click radio button next to different option
   - Green highlight moves to new correct answer
   - Auto-saves via AJAX

4. **Delete Questions:**
   - Click "Delete" button
   - Confirms deletion
   - Question removed from temp storage

5. **Select/Deselect:**
   - "Select All" / "Deselect All" buttons
   - Manual checkbox toggling
   - Only checked questions will be imported

6. **Import:**
   - Click "Import Selected Questions to Question Bank"
   - Selected questions imported
   - Temp record deleted
   - Redirect to Moodle question bank

### Phase 3: Import to Question Bank

**File:** `classes/question_bank_helper.php`

When teacher clicks "Import Selected":

1. Filter questions array to only selected IDs
2. For each question:
   - Create Moodle question object
   - Set type = 'multichoice'
   - Map options A,B,C,D to Moodle format
   - Set fraction (1.0 for correct, 0.0 for incorrect)
   - Save to `question` table
   - Save answers to `question_answers` table
3. Display results (X imported, Y failed)
4. Redirect to question bank

**Moodle Tables Updated:**
- `question` - Main question records
- `question_answers` - Answer options
- `question_categories` - Category association

### Phase 4: Create Quiz (Standard Moodle)

**Not part of this plugin - uses Moodle core functionality**

1. Teacher goes to course
2. Turn editing on
3. Add activity → Quiz
4. Configure quiz settings:
   - Name, description
   - Open/close dates
   - Time limit
   - Attempts allowed
   - Grading method
5. Click "Edit quiz"
6. Click "Add" → "from question bank"
7. Filter by category (find AI-generated questions)
8. Select questions to add
9. Save quiz

Students can now take the quiz!

## Question Bank vs Quiz

**Important Distinction:**

| Question Bank | Quiz |
|--------------|------|
| Storage/library of questions | Actual assessment students take |
| Organized by categories | Configured with settings (time, attempts) |
| Can be reused across multiple quizzes | Uses questions from bank |
| Managed in question bank interface | Activity added to course |
| Our plugin imports here | Teacher creates manually |

**Analogy:**
- **Question Bank** = Library of books
- **Quiz** = Reading assignment using books from library

## Data Flow

```
[Teacher Input]
    ↓
[Documents/URLs] → [AI Generator] → [JSON Questions]
    ↓
[Temp Storage (24hr TTL)]
    ↓
[Preview Page] ← Teacher edits/selects
    ↓
[Selected Questions]
    ↓
[Question Bank Import]
    ↓
[Moodle Question Bank] ← Permanent storage
    ↓
[Teacher Creates Quiz] ← Manual step
    ↓
[Quiz Activity] ← Students take quiz
```

## Security & Privacy

1. **Temporary Storage:**
   - Questions stored max 24 hours
   - Auto-cleanup via cron or on new generation
   - Tied to specific user (userid)

2. **Session Keys:**
   - Unique MD5 hash per generation
   - Prevents unauthorized access to preview

3. **Permissions:**
   - Requires `local/ai_quiz:generate` capability
   - Requires `moodle/question:add` capability
   - Course-level context

4. **File Handling:**
   - Uploaded files stored in Moodle temp directory
   - Deleted immediately after processing
   - Not sent to AI API, only extracted text

## Future Enhancements

### Short Term
- [ ] Question preview before generation (estimate cost/time)
- [ ] Batch generation for multiple topics
- [ ] Export questions as Moodle XML
- [ ] Import existing questions for regeneration/improvement

### Medium Term
- [ ] Question quality scoring
- [ ] Automatic difficulty level detection
- [ ] Learning outcome mapping
- [ ] Bloom's taxonomy alignment

### Long Term
- [ ] Video transcript processing
- [ ] Image-based questions
- [ ] Auto-create entire quiz (not just bank import)
- [ ] Student performance analytics → regenerate weak areas

## Troubleshooting

**Questions not appearing in preview:**
- Check temp table has record with correct sessionkey
- Check browser console for JavaScript errors
- Verify user has permission to access preview.php

**Import fails:**
- Verify question category exists
- Check user has `moodle/question:add` capability
- Review Moodle debug logs for specific errors

**Edits not saving:**
- Check AJAX calls in browser network tab
- Verify sesskey is valid
- Ensure temp record still exists (not expired)

**Questions missing from question bank:**
- Verify import completed successfully
- Check correct course and category selected
- Search question bank by keyword from question text

---

**Last Updated:** 2026-01-12
**Plugin Version:** v0.1.0
