# AI Quiz Generator — Usage Guide

## What This Plugin Does

Generates multiple-choice quiz questions automatically from your course documents using Google's Gemini AI. Questions are added directly to the Moodle Question Bank, ready to use in any quiz.

---

## ⚠️ Important Notices Before You Start

### 1. API Limitations (Free Tier)
This plugin uses **Gemini 2.5 Flash** — Google's free AI API. The free tier has:

| Limit | Value |
|-------|-------|
| Tokens per minute | **250,000** |
| Requests per day | **500** |
| Tokens per day | **1,000,000** |

**A single A4 page ≈ 500–800 tokens.**
To stay safely within the per-minute token limit, **keep your primary document under 15 pages (~10,000 tokens)**. Larger documents risk hitting the rate limit mid-generation.

If you hit the limit, wait 60 seconds and try again.

### 2. ⚠️ Do NOT Use Supporting Documents (for now)
Supporting documents are uploaded alongside primary documents to give the AI extra context. However:
- Every page of a supporting document **consumes tokens** from your quota
- A 50-page supporting document can **double your token usage**
- This can trigger the per-minute rate limit even with a small primary document

**Recommendation: Leave Supporting Documents empty until a future version adds smarter handling.**

### 3. ⚠️ Website URLs Are Not Tested
The Website URL field allows adding web pages as supporting material. This feature **has not been fully tested** and may produce unexpected results.

**Recommendation: Leave Website URLs empty for now.**

---

## Workflow

### Step 1: Open the Plugin
Navigate to your course → find **AI Quiz Generator** in the course tools or navigation menu.

---

### Step 2: Fill in Basic Details

| Field | Description |
|-------|-------------|
| **Quiz Title** | Name for this quiz generation session |
| **Course** | Select the course this quiz belongs to |
| **Category** | Question Bank category to save questions into |

**Tip:** You can create a new category inline by clicking **"Create New Category"** below the category dropdown. No need to leave the page.

---

### Step 3: Upload Primary Document

- Click **Upload Primary Documents**
- Upload a **PDF, DOCX, or PPTX** file
- This is the **only source** the AI will use to generate questions
- All questions will be based on content from this document

**Requirements:**
- PDF must have **selectable text** (not a scanned image)
  - Test: open the PDF and try to highlight text with your mouse
  - If you cannot select text → the PDF is image-based and won't work
- Recommended: **5–15 pages** for best results within API limits
- Maximum practical size: **~30 pages** (risks rate limit)

**Page Range (Optional):**
If you only want questions from specific pages, enter the range:
```
yourdocument.pdf: 5-20
```
One file per line. Leave blank to use the entire document.

---

### Step 4: Configure Quiz Settings

#### Number of Questions
Enter how many questions to generate (recommended: 10–20).

#### Difficulty Distribution
Set the percentage split across difficulty levels. Must total **100%**.

| Level | Description |
|-------|-------------|
| Easy | Recall and definitions |
| Medium | Understanding and application |
| Hard | Analysis and synthesis |

Example: `25% Easy, 50% Medium, 25% Hard`

#### Multiple Answer Questions (Optional)
Check **"Include Multiple Answer Questions"** if you want questions with more than one correct answer.

When enabled:
- Set how many questions should be multiple answer
- Set difficulty split for those questions separately
- These questions use **negative marking** to prevent students from selecting all options

---

### Step 5: Generate

Click **Generate Quiz**. The page will process for **10–60 seconds** depending on document size and API response time.

---

### Step 6: Review Questions (Preview Page)

Before importing, you can review every generated question:

- **Uncheck** any question you don't want to import
- Use **Select All** / **Deselect All** for bulk operations
- Each multiple answer question shows **+X% / -X%** scoring badges

When happy, click **"Import Selected Questions"**.

---

### Step 7: Questions in Question Bank

Your questions are now in the Moodle Question Bank under the category you selected.

From there you can:
- Add them to any **Quiz activity**
- Edit individual questions
- Reuse across multiple quizzes

---

## Question Quality

The AI generates questions that:
- ✅ Test **subject knowledge**, not document awareness
- ✅ Never say "According to the document..." or "What does the text say..."
- ✅ Have exactly **4 options (A, B, C, D)**
- ✅ Cover the document **proportionally** (not just the first section)
- ✅ Include an **explanation** for the correct answer

---

## Troubleshooting

| Error | Cause | Fix |
|-------|-------|-----|
| "Primary documents are required" | No file uploaded or file not saved | Re-upload the file |
| "PDF extraction returned empty" | PDF is image/scanned based | Use a PDF with selectable text |
| "Quota exceeded" | Hit API rate limit | Wait 60 seconds, try again |
| "Bad API request" | Invalid characters in PDF | Try a different PDF |
| Questions not appearing in bank | Moodle cache stale | Purge caches: Site Admin → Development → Purge all caches |

---

## Tips for Best Results

1. **Clean PDFs work best** — lecture slides exported as PDF, textbook chapters, RFC documents, etc.
2. **Focused content** — a 10-page chapter on one topic generates better questions than a 50-page mixed document
3. **Run multiple times** — generate 20 questions, keep the best 15, generate again for variety
4. **Check difficulty** — review generated questions and uncheck any that don't match the intended difficulty
5. **Page ranges** — if your document has a table of contents or references section, exclude those pages using page ranges

---

## Version
Current version: **v1.7.7** — Gemini 2.5 Flash
