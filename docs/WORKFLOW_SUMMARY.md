# AI Quiz Generator - Complete System Overview

## Your Questions Answered

### Q1: "Will this show quiz questions before making quiz?"
**YES!** The new system includes a full preview/edit page.

### Q2: "How does the quiz bank work?"
See detailed explanation below.

---

## Complete Workflow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                    COMPLETE USER JOURNEY                         │
└─────────────────────────────────────────────────────────────────┘

TEACHER WORKFLOW
════════════════════════════════════════════════════════════════════

Step 1: UPLOAD & GENERATE
┌────────────────────────────────────────────────┐
│ Page: generate.php                             │
│                                                 │
│ 1. Teacher uploads PDF/DOCX/PPTX or URLs      │
│ 2. Sets: 20 questions (5 easy, 10 med, 5 hard)│
│ 3. Clicks "Generate Quiz"                      │
│                                                 │
│ Backend:                                        │
│ → Extract text from documents                  │
│ → Send to Gemini 2.5 Pro API                  │
│ → Receive 20 MCQ questions as JSON            │
│ → Store in temporary database table            │
└────────────────┬───────────────────────────────┘
                 │
                 ↓ REDIRECT TO PREVIEW
                 │
Step 2: PREVIEW, EDIT & SELECT (NEW!)
┌────────────────────────────────────────────────┐
│ Page: preview.php                               │
│                                                 │
│ Teacher sees ALL 20 questions with:             │
│                                                 │
│ ☑️ Checkbox (all selected by default)          │
│ 📝 Question text (click to edit)               │
│ 🔤 Options A, B, C, D (click to edit)          │
│ ⭕ Radio buttons (select correct answer)       │
│ ❌ Delete button                                │
│                                                 │
│ Teacher can:                                    │
│ • Edit any question inline (auto-saves)        │
│ • Change correct answers                        │
│ • Delete bad questions                          │
│ • Uncheck questions they don't want            │
│                                                 │
│ Example: Keep only 15 out of 20 questions      │
└────────────────┬───────────────────────────────┘
                 │
                 ↓ TEACHER CLICKS "IMPORT SELECTED"
                 │
Step 3: IMPORT TO QUESTION BANK
┌────────────────────────────────────────────────┐
│ Backend: question_bank_helper.php              │
│                                                 │
│ → Only checked questions imported              │
│ → Saved to Moodle Question Bank               │
│ → Success message: "15 questions imported"     │
│ → Temp storage deleted                          │
└────────────────┬───────────────────────────────┘
                 │
                 ↓
┌────────────────────────────────────────────────┐
│         MOODLE QUESTION BANK                   │
│         (Permanent Storage)                    │
│                                                 │
│ Questions are now stored here forever          │
│ Can be:                                         │
│ • Edited further in Moodle                     │
│ • Tagged with keywords                          │
│ • Organized in categories                       │
│ • Reused in MULTIPLE quizzes                   │
│ • Exported as Moodle XML                        │
└────────────────┬───────────────────────────────┘
                 │
                 ↓ TEACHER MANUALLY CREATES QUIZ
                 │
Step 4: CREATE QUIZ ACTIVITY (Standard Moodle)
┌────────────────────────────────────────────────┐
│ Teacher Actions:                                │
│                                                 │
│ 1. Go to course                                │
│ 2. Turn editing on                              │
│ 3. Add activity → Quiz                         │
│ 4. Configure quiz settings:                     │
│    • Name: "Chapter 5 Test"                    │
│    • Time limit: 60 minutes                     │
│    • Attempts: 2                                │
│    • Grading: Highest grade                     │
│                                                 │
│ 5. Click "Edit quiz"                           │
│ 6. Click "Add" → "from question bank"         │
│ 7. Select AI-generated questions                │
│ 8. Save                                         │
└────────────────┬───────────────────────────────┘
                 │
                 ↓
┌────────────────────────────────────────────────┐
│         LIVE QUIZ (Students Take It!)          │
│                                                 │
│ • Students see quiz in course                  │
│ • Click to attempt                              │
│ • Answer questions                              │
│ • Submit                                        │
│ • Moodle grades automatically                   │
└────────────────────────────────────────────────┘

STUDENT WORKFLOW (For Context)
════════════════════════════════════════════════════════════════════

Student → Course → Sees "Chapter 5 Test" quiz
       → Clicks quiz → Attempts quiz
       → Answers 15 MCQs → Submits
       → Gets grade immediately → Reviews answers
```

## Question Bank vs Quiz - KEY DISTINCTION

| Aspect | Question Bank | Quiz Activity |
|--------|---------------|---------------|
| **What is it?** | Storage warehouse for questions | Actual test students take |
| **Location** | Site Administration → Question Bank | Course → Activities |
| **Purpose** | Store and organize questions | Assess students |
| **Our plugin** | ✅ Imports questions HERE | ❌ Does NOT create this |
| **Teacher action** | Automatic (plugin does it) | Manual (teacher creates) |
| **Reusability** | One question → many quizzes | One quiz → one course |
| **Settings** | Just questions, no time limits | Time, attempts, grading, dates |
| **Students see it?** | NO | YES |

**Analogy:**
- **Question Bank** = Recipe book (collection of recipes)
- **Quiz** = Actual meal you cook (uses recipes from book)

## New Preview Page Features

### Visual Preview
```
┌───────────────────────────────────────────────────────────┐
│  Preview Generated Questions                               │
├───────────────────────────────────────────────────────────┤
│  ℹ️ Review instructions: You can edit, delete, select...  │
│                                                            │
│  Total generated: 20 questions                             │
│  [Select All] [Deselect All]                              │
├───────────────────────────────────────────────────────────┤
│  ☑️ Question 1  [Easy] [Topic: Photosynthesis]            │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ What is the primary function of chlorophyll?       │  │ ← Click to edit
│  └─────────────────────────────────────────────────────┘  │
│  ⭕ A. Absorb sunlight (green highlight)  ← Correct       │ ← Click to edit
│  ○ B. Store energy                        ← Click to edit │
│  ○ C. Release oxygen                      ← Click to edit │
│  ○ D. Break down glucose                  ← Click to edit │
│                                                            │
│  💡 Explanation: Chlorophyll absorbs light energy...       │
│  [Delete This Question]                                    │
├───────────────────────────────────────────────────────────┤
│  ☑️ Question 2  [Medium] [Topic: Cell Division]           │
│  ...                                                       │
└───────────────────────────────────────────────────────────┘
     [Import Selected Questions to Question Bank]
```

### Interactive Features

1. **Inline Editing:**
   - Click on any text → Edit directly
   - Press Enter or click away → Auto-saves
   - Green flash confirms save

2. **Change Correct Answer:**
   - Click radio button next to different option
   - Green highlight moves
   - Auto-saves

3. **Delete Questions:**
   - Click "Delete" button
   - Question removed permanently

4. **Bulk Selection:**
   - Check/uncheck individual questions
   - "Select All" / "Deselect All" buttons
   - Only checked ones imported

## File Structure (Complete)

```
src/local/ai_quiz/
├── amd/src/
│   └── preview.js              # Interactive preview features
├── classes/
│   ├── forms/
│   │   └── generate_form.php   # Upload form
│   ├── privacy/
│   │   └── provider.php        # GDPR compliance
│   ├── question_bank_helper.php # Import to Moodle
│   └── quiz_generator.php      # AI generation logic
├── db/
│   ├── access.php              # Permissions
│   └── install.xml             # Database table (temp storage)
├── lang/en/
│   └── local_ai_quiz.php       # All text strings
├── generate.php                # Step 1: Upload & generate
├── preview.php                 # Step 2: Review & edit (NEW!)
├── lib.php                     # Moodle hooks
├── settings.php                # Admin settings (API keys)
├── version.php                 # Plugin metadata
├── INSTALL.md                  # Installation guide
├── README.md                   # Main documentation
└── WORKFLOW.md                 # Detailed workflow (NEW!)
```

## Database Storage

### Temporary Storage (Preview Phase)
```sql
local_ai_quiz_temp
├── id (unique ID)
├── userid (who generated)
├── courseid (target course)
├── categoryid (target category)
├── sessionkey (unique preview link)
├── questiondata (JSON with all questions)
└── timecreated (auto-cleanup after 24h)
```

### Permanent Storage (After Import)
Standard Moodle tables:
- `question` - Question records
- `question_answers` - Answer options
- `question_categories` - Organization

## Example User Journey

**Scenario:** Teacher wants to create a quiz about Photosynthesis

1. **Upload:** Teacher uploads textbook chapter PDF (20 pages)
2. **Generate:** Clicks "Generate 20 questions"
3. **Wait:** 30 seconds while AI processes
4. **Preview:** Sees 20 generated questions
5. **Review:** Reads through each question
   - Question 3 has a typo → Clicks and fixes it
   - Question 7 is too hard → Unchecks it
   - Question 12 correct answer is wrong → Changes radio button
   - Question 18 is duplicate → Deletes it
6. **Import:** 17 questions selected, clicks "Import"
7. **Success:** "17 questions imported to Question Bank"
8. **Create Quiz:**
   - Goes to course
   - Add activity → Quiz
   - Name: "Photosynthesis Test"
   - Time limit: 45 minutes
   - Edit quiz → Add from question bank
   - Selects 15 out of 17 questions
9. **Done:** Students can now take the quiz!

## Key Benefits of Preview System

✅ **Quality Control:** Teacher reviews before importing
✅ **Flexibility:** Edit questions to match teaching style
✅ **Selection:** Import only good questions
✅ **Correction:** Fix AI mistakes before students see them
✅ **Customization:** Adjust difficulty on the fly

## Installation Quick Start

```bash
# 1. Copy plugin
cp -r src/local/ai_quiz /path/to/moodle/local/

# 2. Install via Moodle
# Visit: Site Administration → Notifications
# Click: Upgrade Moodle database now

# 3. Configure API key
# Go to: Site Administration → Plugins → Local → AI Quiz Generator
# Enter: Your Gemini API key

# 4. Test it
# Go to any course → AI Quiz Generator
# Upload a sample PDF → Generate 5 questions → Preview → Import
```

## Next Steps

1. Install the plugin
2. Get Gemini API key (free at https://makersuite.google.com/app/apikey)
3. Test with sample document
4. Train teachers on workflow
5. Monitor question quality
6. Gather feedback

---

**Questions?** Read WORKFLOW.md for deep technical details.
**Installation help?** Read INSTALL.md for step-by-step guide.
**General info?** Read README.md for feature overview.
