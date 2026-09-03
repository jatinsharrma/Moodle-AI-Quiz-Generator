# Updated Section: Quiz Generation Configuration (Final Version)

**Replace Section 1.2 in the requirements document with this:**

---

## 1.2 Quiz Generation Configuration

### User Inputs:

**Basic Configuration:**
- **Quiz title** (required, max 255 characters)
- **Quiz description** (optional, text area)
- **Total number of questions** (5-50, default: 20)

**Question Type Selection:**
- **Number of MCQ (Multiple Choice Questions)** (0-50)
  - 4 options (A, B, C, D)
  - Single correct answer
- **Number of True/False questions** (0-50)
  - Statement with True or False answer
- **Number of Type Answer questions** (0-50)
  - Student types short text answer
  - Exact match or keyword-based grading
- **Validation:** Sum must equal total number of questions
- **Constraint:** At least one question type must be selected

**Difficulty Distribution (for all question types):**
- **Number of Easy questions** (integer input, 0-50)
- **Number of Medium questions** (integer input, 0-50)
- **Number of Hard questions** (integer input, 0-50)
- **Validation:** Sum must equal total number of questions
- **Display:** Live counter showing remaining questions to allocate

**Question Content Distribution (applies to MCQ and True/False):**
- Conceptual understanding (percentage slider, 0-100%)
- Application/problem-solving (percentage slider, 0-100%)
- Factual recall (percentage slider, 0-100%)
- Analysis/evaluation (percentage slider, 0-100%)
- **Validation:** Must sum to 100%
- **Note:** Does not apply to Type Answer questions

### UI Layout:

```
┌──────────────────────────────────────────────────────────┐
│ Quiz Configuration                                        │
├──────────────────────────────────────────────────────────┤
│                                                           │
│ Quiz Title: [_____________________________] *            │
│                                                           │
│ Description: [____________________________]              │
│              [____________________________]              │
│                                                           │
│ ┌────────────────────────────────────────────────────┐  │
│ │ Total Questions: [20] ▼  (5-50)                   │  │
│ └────────────────────────────────────────────────────┘  │
│                                                           │
│ ┌────────────────────────────────────────────────────┐  │
│ │ Question Types                                      │  │
│ │                                                     │  │
│ │ ┌─ Multiple Choice (MCQ) ──────────────────────┐  │  │
│ │ │ Number: [12] ⏶⏷                              │  │  │
│ │ │ • 4 options per question                      │  │  │
│ │ │ • Single correct answer                       │  │  │
│ │ └──────────────────────────────────────────────┘  │  │
│ │                                                     │  │
│ │ ┌─ True/False ─────────────────────────────────┐  │  │
│ │ │ Number: [5] ⏶⏷                               │  │  │
│ │ │ • Statement with True/False answer            │  │  │
│ │ └──────────────────────────────────────────────┘  │  │
│ │                                                     │  │
│ │ ┌─ Type Answer (Short Text) ───────────────────┐  │  │
│ │ │ Number: [3] ⏶⏷                               │  │  │
│ │ │ • Student types text answer                   │  │  │
│ │ │ • Exact match or partial credit               │  │  │
│ │ └──────────────────────────────────────────────┘  │  │
│ │                                                     │  │
│ │ ℹ Total allocated: 20/20 ✓                        │  │
│ └────────────────────────────────────────────────────┘  │
│                                                           │
│ ┌────────────────────────────────────────────────────┐  │
│ │ Difficulty Distribution                             │  │
│ │                                                     │  │
│ │ Easy questions:      [5]  ⏶⏷  (25%) ━━░░░░░░░░   │  │
│ │ Medium questions:    [10] ⏶⏷  (50%) ━━━━━░░░░░   │  │
│ │ Hard questions:      [5]  ⏶⏷  (25%) ━━░░░░░░░░   │  │
│ │                                                     │  │
│ │ ℹ Total allocated: 20/20 ✓                        │  │
│ └────────────────────────────────────────────────────┘  │
│                                                           │
│ ┌────────────────────────────────────────────────────┐  │
│ │ Content Focus (for MCQ/True-False only)            │  │
│ │                                                     │  │
│ │ Conceptual:     [40%] ━━━━━━━━░░  ◄──────►        │  │
│ │ Application:    [30%] ━━━━━━░░░░  ◄──────►        │  │
│ │ Factual:        [20%] ━━━━░░░░░░  ◄──────►        │  │
│ │ Analysis:       [10%] ━━░░░░░░░░  ◄──────►        │  │
│ │                                                     │  │
│ │ Total: 100% ✓                                      │  │
│ │                                                     │  │
│ │ ℹ Applies to 17 questions (MCQ + True/False)      │  │
│ │   Type Answer questions: 3 (not included)          │  │
│ └────────────────────────────────────────────────────┘  │
│                                                           │
│ ┌────────────────────────────────────────────────────┐  │
│ │ Quick Presets:                                      │  │
│ │ [Balanced ▼] [Load Preset]                         │  │
│ └────────────────────────────────────────────────────┘  │
│                                                           │
│ [Generate Quiz]  [Save as Draft]  [Clear All]           │
│                                                           │
└──────────────────────────────────────────────────────────┘
```

### Interactive Features:

**Live Validation Indicators:**
```javascript
// Question Type Counter
if (mcq_count + tf_count + ta_count === total_questions) {
    show_checkmark("Question types allocated correctly");
} else {
    remaining = total_questions - (mcq_count + tf_count + ta_count);
    show_warning(`${remaining} questions remaining to allocate`);
}

// Difficulty Counter
if (easy + medium + hard === total_questions) {
    show_checkmark("Difficulty levels allocated correctly");
} else {
    remaining = total_questions - (easy + medium + hard);
    show_warning(`${remaining} questions remaining to allocate`);
}

// Content Focus (only if MCQ or TF present)
mcq_tf_count = mcq_count + tf_count;
if (mcq_tf_count > 0) {
    if (Math.abs(conceptual + application + factual + analysis - 100) < 0.1) {
        show_checkmark("Content focus adds to 100%");
    } else {
        current = conceptual + application + factual + analysis;
        show_warning(`Content focus: ${current}% (should be 100%)`);
    }
    show_info(`Applies to ${mcq_tf_count} questions (MCQ + True/False)`);
    show_info(`Type Answer questions: ${ta_count} (not included)`);
} else {
    show_info("No MCQ/True-False questions - content focus not applicable");
}
```

### Default Presets:

**Preset 1: Balanced Quiz (Default)**
```
Total: 20 questions
- MCQ: 12
- True/False: 5
- Type Answer: 3

Difficulty:
- Easy: 5 (25%)
- Medium: 10 (50%)
- Hard: 5 (25%)

Content Focus (17 MCQ+TF):
- Conceptual: 40%
- Application: 30%
- Factual: 20%
- Analysis: 10%
```

**Preset 2: Quick Assessment**
```
Total: 10 questions
- MCQ: 6
- True/False: 3
- Type Answer: 1

Difficulty:
- Easy: 5 (50%)
- Medium: 4 (40%)
- Hard: 1 (10%)

Content Focus (9 MCQ+TF):
- Conceptual: 30%
- Application: 20%
- Factual: 40%
- Analysis: 10%
```

**Preset 3: MCQ Only (Traditional)**
```
Total: 25 questions
- MCQ: 25
- True/False: 0
- Type Answer: 0

Difficulty:
- Easy: 8 (32%)
- Medium: 12 (48%)
- Hard: 5 (20%)

Content Focus (25 MCQ):
- Conceptual: 40%
- Application: 30%
- Factual: 20%
- Analysis: 10%
```

**Preset 4: Mixed Assessment**
```
Total: 30 questions
- MCQ: 18
- True/False: 7
- Type Answer: 5

Difficulty:
- Easy: 9 (30%)
- Medium: 15 (50%)
- Hard: 6 (20%)

Content Focus (25 MCQ+TF):
- Conceptual: 35%
- Application: 35%
- Factual: 20%
- Analysis: 10%
```

**Preset 5: Comprehensive Exam**
```
Total: 40 questions
- MCQ: 25
- True/False: 10
- Type Answer: 5

Difficulty:
- Easy: 10 (25%)
- Medium: 20 (50%)
- Hard: 10 (25%)

Content Focus (35 MCQ+TF):
- Conceptual: 35%
- Application: 35%
- Factual: 20%
- Analysis: 10%
```

### Validation Rules:

```javascript
validate_configuration() {
    let errors = [];
    
    // Basic validation
    if (!quiz_title.trim()) {
        errors.push("Quiz title is required");
    }
    if (quiz_title.length > 255) {
        errors.push("Quiz title must be under 255 characters");
    }
    if (total_questions < 5 || total_questions > 50) {
        errors.push("Total questions must be between 5 and 50");
    }
    
    // Question type validation
    if (mcq_count < 0 || tf_count < 0 || ta_count < 0) {
        errors.push("Question counts cannot be negative");
    }
    
    let type_sum = mcq_count + tf_count + ta_count;
    if (type_sum !== total_questions) {
        errors.push(
            `Question types (${type_sum}) must equal total questions (${total_questions})`
        );
    }
    
    if (type_sum === 0) {
        errors.push("Must have at least one question");
    }
    
    // Difficulty validation
    if (easy_count < 0 || medium_count < 0 || hard_count < 0) {
        errors.push("Difficulty counts cannot be negative");
    }
    
    let difficulty_sum = easy_count + medium_count + hard_count;
    if (difficulty_sum !== total_questions) {
        errors.push(
            `Difficulty levels (${difficulty_sum}) must equal total questions (${total_questions})`
        );
    }
    
    // Content focus validation (only if MCQ or TF present)
    let mcq_tf_total = mcq_count + tf_count;
    if (mcq_tf_total > 0) {
        let focus_sum = conceptual + application + factual + analysis;
        if (Math.abs(focus_sum - 100) > 0.1) {
            errors.push(
                `Content focus must sum to 100% (currently ${focus_sum.toFixed(1)}%)`
            );
        }
        
        if (conceptual < 0 || application < 0 || factual < 0 || analysis < 0) {
            errors.push("Content focus percentages cannot be negative");
        }
    }
    
    return errors;
}
```

---

## Updated Prompt Template (Section 3.2)

```python
SYSTEM_PROMPT = """
You are an expert educator creating assessment questions.

PRIMARY SOURCE MATERIALS:
{primary_documents}

SUPPORTING MATERIALS (for reference only):
{secondary_documents}

INSTRUCTIONS:
Generate questions primarily from PRIMARY sources.
Secondary materials are for context only.

QUESTION GENERATION REQUIREMENTS:

TOTAL QUESTIONS: {total_questions}

QUESTION TYPES TO GENERATE:
{question_types_section}

DIFFICULTY DISTRIBUTION (applies to all question types):
- Easy: {easy_count} questions
  * Basic recall and recognition
  * Definitions and simple facts
  * Direct information from materials
  
- Medium: {medium_count} questions
  * Understanding and application
  * Interpretation of concepts
  * Relating ideas together
  
- Hard: {hard_count} questions
  * Analysis and evaluation
  * Complex reasoning
  * Synthesis of multiple concepts
  * Critical thinking

{content_focus_section}

QUALITY REQUIREMENTS:
1. Questions are standalone and clear
2. Cover content across all primary documents proportionally
3. Use varied question stems and formats
4. Only use information explicitly stated in provided materials
5. Avoid questions like "According to the document..."
6. Ensure questions test understanding, not just material recognition

OUTPUT JSON FORMAT:
{{
  "questions": [
    {question_examples}
  ],
  "metadata": {{
    "total_questions": {total_questions},
    "mcq_count": {mcq_count},
    "truefalse_count": {truefalse_count},
    "typeanswer_count": {typeanswer_count},
    "difficulty_distribution": {{
      "easy": {easy_count},
      "medium": {medium_count},
      "hard": {hard_count}
    }},
    "generated_at": "{timestamp}"
  }}
}}
"""

# Dynamic sections based on configuration

QUESTION_TYPES_SECTION = """
1. MULTIPLE CHOICE QUESTIONS (MCQ): {mcq_count}
   * Each MCQ must have EXACTLY 4 options (A, B, C, D)
   * Only ONE correct answer per question
   * Distractors must be plausible but clearly incorrect to someone who knows the material
   * Options should be similar in length and complexity
   * Avoid "All of the above" or "None of the above" unless necessary

2. TRUE/FALSE QUESTIONS: {truefalse_count}
   * Statement must be unambiguously true or false based on the materials
   * Avoid trick questions or semantic games
   * Include clear explanation for why it's true/false
   * Statement should be substantial, not trivial

3. TYPE ANSWER QUESTIONS: {typeanswer_count}
   * Questions requiring short text answers (1-3 words or short phrase)
   * Answer should be specific and unambiguous
   * Provide primary answer and acceptable alternatives
   * Common acceptable variations (spelling, capitalization) should be listed
   * Examples:
     - "What protocol operates at layer 4?" → Answer: "TCP" (alternatives: "Transmission Control Protocol")
     - "What year was X invented?" → Answer: "1991"
     - "What is the capital of France?" → Answer: "Paris"
"""

CONTENT_FOCUS_SECTION = """
CONTENT FOCUS (for {mcq_tf_count} MCQ and True/False questions):
- Conceptual understanding: {conceptual_percent}%
  * Testing understanding of core concepts and theories
  * "Why" and "How" questions
  * Relationships between ideas
  
- Application/problem-solving: {application_percent}%
  * Applying knowledge to scenarios
  * Problem-solving questions
  * Practical use cases
  
- Factual recall: {factual_percent}%
  * Specific facts, dates, names, terms
  * Definitions and terminology
  * Direct recall from materials
  
- Analysis/evaluation: {analysis_percent}%
  * Comparing and contrasting
  * Evaluating arguments
  * Critical analysis
  * Drawing conclusions

NOTE: Type Answer questions ({typeanswer_count}) are typically factual recall 
and do not follow this distribution.
"""

QUESTION_EXAMPLES = """
    // Example MCQ
    {{
      "id": 1,
      "type": "mcq",
      "question": "What is the primary purpose of the Transport Layer in the OSI model?",
      "options": {{
        "A": "Routing packets between networks",
        "B": "End-to-end communication and error recovery",
        "C": "Physical transmission of bits",
        "D": "Data encryption and security"
      }},
      "correct_answer": "B",
      "difficulty": "medium",
      "topic": "OSI Model",
      "question_type": "conceptual",
      "explanation": "The Transport Layer (Layer 4) is responsible for end-to-end communication, flow control, and error recovery between hosts."
    }},
    
    // Example True/False
    {{
      "id": 2,
      "type": "truefalse",
      "question": "IPv4 addresses are 128 bits long.",
      "correct_answer": "false",
      "difficulty": "easy",
      "topic": "IP Addressing",
      "question_type": "factual",
      "explanation": "IPv4 addresses are 32 bits long, not 128 bits. IPv6 addresses are 128 bits long."
    }},
    
    // Example Type Answer
    {{
      "id": 3,
      "type": "typeanswer",
      "question": "What is the maximum number of hosts in a /24 subnet?",
      "correct_answer": "254",
      "acceptable_answers": ["254", "254 hosts"],
      "case_sensitive": false,
      "difficulty": "medium",
      "topic": "Subnetting",
      "question_type": "application",
      "explanation": "A /24 subnet has 8 host bits (32-24=8), giving 2^8 = 256 addresses. Subtracting network and broadcast addresses: 256-2 = 254 usable host addresses.",
      "grading_notes": "Accept '254' or '254 hosts'. Do not accept '256' as it doesn't account for network/broadcast addresses."
    }}
"""
```

---

## Updated Database Schema (Section 2.3)

**Updated Table: mdl_quizgen_sessions**
```sql
CREATE TABLE mdl_quizgen_sessions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    course_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    quiz_title VARCHAR(255) NOT NULL,
    quiz_description TEXT,
    
    -- Question counts
    total_questions INT NOT NULL,
    mcq_count INT NOT NULL DEFAULT 0,
    truefalse_count INT NOT NULL DEFAULT 0,
    typeanswer_count INT NOT NULL DEFAULT 0,
    
    -- Difficulty distribution
    easy_count INT NOT NULL DEFAULT 0,
    medium_count INT NOT NULL DEFAULT 0,
    hard_count INT NOT NULL DEFAULT 0,
    
    -- Content focus (JSON)
    content_focus_config TEXT, -- {conceptual: 40, application: 30, factual: 20, analysis: 10}
    
    -- Status tracking
    status VARCHAR(50), -- 'draft', 'processing', 'completed', 'failed'
    error_message TEXT NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    
    FOREIGN KEY (course_id) REFERENCES mdl_course(id),
    FOREIGN KEY (user_id) REFERENCES mdl_user(id),
    
    INDEX idx_status (status),
    INDEX idx_user_course (user_id, course_id),
    INDEX idx_created (created_at)
);
```

**Updated Table: mdl_quizgen_generated_questions**
```sql
CREATE TABLE mdl_quizgen_generated_questions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    session_id BIGINT NOT NULL,
    moodle_question_id BIGINT NULL, -- Links to mdl_question after import
    
    -- Question identification
    question_number INT NOT NULL, -- 1, 2, 3, etc.
    question_type VARCHAR(20) NOT NULL, -- 'mcq', 'truefalse', 'typeanswer'
    question_text TEXT NOT NULL,
    
    -- For MCQ (JSON)
    options TEXT NULL, -- {"A": "text", "B": "text", "C": "text", "D": "text"}
    
    -- For Type Answer (JSON)
    acceptable_answers TEXT NULL, -- ["answer1", "answer2", "answer3"]
    case_sensitive BOOLEAN DEFAULT FALSE,
    grading_notes TEXT NULL,
    
    -- Common fields
    correct_answer VARCHAR(255) NOT NULL, -- "A"/"B"/"C"/"D" or "true"/"false" or actual answer
    difficulty VARCHAR(20) NOT NULL, -- 'easy', 'medium', 'hard'
    topic VARCHAR(255),
    content_type VARCHAR(50), -- 'conceptual', 'application', 'factual', 'analysis'
    explanation TEXT NOT NULL,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (session_id) REFERENCES mdl_quizgen_sessions(id) ON DELETE CASCADE,
    INDEX idx_session (session_id),
    INDEX idx_question_type (question_type),
    INDEX idx_difficulty (difficulty)
);
```

---

## Updated Moodle Integration (Section 2.1)

```python
def create_moodle_question(quiz_id, question_data):
    """
    Create question in Moodle based on type
    """
    question_type = question_data['type']
    
    if question_type == 'mcq':
        # Create multichoice question
        question = {
            'qtype': 'multichoice',
            'name': f"Q{question_data['id']}: {question_data['topic']}",
            'questiontext': {
                'text': question_data['question'],
                'format': 'html'
            },
            'defaultmark': 1.0,
            'single': 1,  # Single answer only
            'shuffleanswers': 1,  # Shuffle options
            'answernumbering': 'abc',  # A, B, C, D style
            'correctfeedback': {
                'text': 'Correct!',
                'format': 'html'
            },
            'partiallycorrectfeedback': {
                'text': '',
                'format': 'html'
            },
            'incorrectfeedback': {
                'text': 'Incorrect. ' + question_data['explanation'],
                'format': 'html'
            },
            'answers': [
                {
                    'answer': {
                        'text': question_data['options']['A'],
                        'format': 'html'
                    },
                    'fraction': 1.0 if question_data['correct_answer'] == 'A' else 0.0,
                    'feedback': {
                        'text': question_data['explanation'] if question_data['correct_answer'] == 'A' else '',
                        'format': 'html'
                    }
                },
                {
                    'answer': {
                        'text': question_data['options']['B'],
                        'format': 'html'
                    },
                    'fraction': 1.0 if question_data['correct_answer'] == 'B' else 0.0,
                    'feedback': {
                        'text': question_data['explanation'] if question_data['correct_answer'] == 'B' else '',
                        'format': 'html'
                    }
                },
                {
                    'answer': {
                        'text': question_data['options']['C'],
                        'format': 'html'
                    },
                    'fraction': 1.0 if question_data['correct_answer'] == 'C' else 0.0,
                    'feedback': {
                        'text': question_data['explanation'] if question_data['correct_answer'] == 'C' else '',
                        'format': 'html'
                    }
                },
                {
                    'answer': {
                        'text': question_data['options']['D'],
                        'format': 'html'
                    },
                    'fraction': 1.0 if question_data['correct_answer'] == 'D' else 0.0,
                    'feedback': {
                        'text': question_data['explanation'] if question_data['correct_answer'] == 'D' else '',
                        'format': 'html'
                    }
                }
            ]
        }
    
    elif question_type == 'truefalse':
        # Create true/false question
        question = {
            'qtype': 'truefalse',
            'name': f"Q{question_data['id']}: {question_data['topic']}",
            'questiontext': {
                'text': question_data['question'],
                'format': 'html'
            },
            'defaultmark': 1.0,
            'correctanswer': 1 if question_data['correct_answer'].lower() == 'true' else 0,
            'feedbacktrue': {
                'text': question_data['explanation'] if question_data['correct_answer'].lower() == 'true' 
                        else 'Incorrect. ' + question_data['explanation'],
                'format': 'html'
            },
            'feedbackfalse': {
                'text': question_data['explanation'] if question_data['correct_answer'].lower() == 'false'
                        else 'Incorrect. ' + question_data['explanation'],
                'format': 'html'
            }
        }
    
    elif question_type == 'typeanswer':
        # Create short answer question
        # Use Moodle's "shortanswer" question type with multiple acceptable answers
        
        answers_list = []
        
        # Primary answer gets 100% credit
        answers_list.append({
            'answer': {
                'text': question_data['correct_answer'],
                'format': 'plain'
            },
            'fraction': 1.0,
            'feedback': {
                'text': 'Correct! ' + question_data['explanation'],
                'format': 'html'
            }
        })
        
        # Acceptable alternatives also get 100% credit
        if 'acceptable_answers' in question_data:
            for alt_answer in question_data['acceptable_answers']:
                if alt_answer != question_data['correct_answer']:  # Avoid duplicates
                    answers_list.append({
                        'answer': {
                            'text': alt_answer,
                            'format': 'plain'
                        },
                        'fraction': 1.0,
                        'feedback': {
                            'text': 'Correct! ' + question_data['explanation'],
                            'format': 'html'
                        }
                    })
        
        question = {
            'qtype': 'shortanswer',
            'name': f"Q{question_data['id']}: {question_data['topic']}",
            'questiontext': {
                'text': question_data['question'],
                'format': 'html'
            },
            'defaultmark': 1.0,
            'usecase': 1 if question_data.get('case_sensitive', False) else 0,  # Case sensitivity
            'answers': answers_list,
            'generalfeedback': {
                'text': question_data.get('grading_notes', ''),
                'format': 'html'
            }
        }
    
    else:
        raise ValueError(f"Unknown question type: {question_type}")
    
    # Create question in Moodle question bank
    question_id = moodle_api_create_question(
        category_id=get_quiz_category(quiz_id),
        question=question
    )
    
    # Add question to quiz
    add_question_to_quiz(quiz_id, question_id)
    
    return question_id
```

---

## Updated Validation (Section 6.1)

```python
def validate_config(config):
    """
    Comprehensive validation of quiz configuration
    """
    errors = []
    
    # Basic validation
    if not config['quiz_title'] or not config['quiz_title'].strip():
        errors.append("Quiz title is required")
    
    if len(config['quiz_title']) > 255:
        errors.append("Quiz title must be under 255 characters")
    
    total = config['total_questions']
    if not (5 <= total <= 50):
        errors.append(f"Total questions must be 5-50, got {total}")
    
    # Question type validation
    mcq = config['mcq_count']
    tf = config['truefalse_count']
    ta = config['typeanswer_count']
    
    if mcq < 0:
        errors.append("MCQ count cannot be negative")
    if tf < 0:
        errors.append("True/False count cannot be negative")
    if ta < 0:
        errors.append("Type Answer count cannot be negative")
    
    type_sum = mcq + tf + ta
    if type_sum != total:
        errors.append(
            f"Question types sum ({type_sum}) must equal total ({total}). "
            f"MCQ: {mcq}, True/False: {tf}, Type Answer: {ta}"
        )
    
    if type_sum == 0:
        errors.append("Must have at least one question")
    
    # Difficulty validation
    easy = config['easy_count']
    medium = config['medium_count']
    hard = config['hard_count']
    
    if easy < 0:
        errors.append("Easy count cannot be negative")
    if medium < 0:
        errors.append("Medium count cannot be negative")
    if hard < 0:
        errors.append("Hard count cannot be negative")
    
    difficulty_sum = easy + medium + hard
    if difficulty_sum != total:
        errors.append(
            f"Difficulty levels sum ({difficulty_sum}) must equal total ({total}). "
            f"Easy: {easy}, Medium: {medium}, Hard: {hard}"
        )
    
    # Content focus validation (only if MCQ or TF present)
    mcq_tf_total = mcq + tf
    if mcq_tf_total > 0:
        focus = config['content_focus_config']
        conceptual = focus.get('conceptual', 0)
        application = focus.get('application', 0)
        factual = focus.get('factual', 0)
        analysis = focus.get('analysis', 0)
        
        if conceptual < 0 or application < 0 or factual < 0 or analysis < 0:
            errors.append("Content focus percentages cannot be negative")
        
        focus_sum = conceptual + application + factual + analysis
        if abs(focus_sum - 100) > 0.1:
            errors.append(
                f"Content focus must sum to 100%, got {focus_sum:.1f}%. "
                f"Conceptual: {conceptual}%, Application: {application}%, "
                f"Factual: {factual}%, Analysis: {analysis}%"
            )
    
    if errors:
        return {
            'valid': False,
            'errors': errors
        }
    
    return {
        'valid': True,
        'summary': {
            'total_questions': total,
            'mcq': mcq,
            'truefalse': tf,
            'typeanswer': ta,
            'easy': easy,
            'medium': medium,
            'hard': hard
        }
    }
```

---

## Updated Output Validation (Section 6.2)

```python
def validate_generated_questions(questions, config):
    """
    Validate AI-generated questions match configuration
    """
    errors = []
    warnings = []
    
    total_generated = len(questions)
    total_expected = config['total_questions']
    
    # Allow 20% tolerance on total count
    if total_generated < total_expected * 0.8:
        errors.append(
            f"Generated too few questions: {total_generated} (expected ~{total_expected})"
        )
    
    # Count by type
    mcq_generated = sum(1 for q in questions if q['type'] == 'mcq')
    tf_generated = sum(1 for q in questions if q['type'] == 'truefalse')
    ta_generated = sum(1 for q in questions if q['type'] == 'typeanswer')
    
    # Allow 15% tolerance on type distribution
    tolerance = max(1, total_expected * 0.15)
    
    if abs(mcq_generated - config['mcq_count']) > tolerance:
        warnings.append(
            f"MCQ count off: generated {mcq_generated}, expected {config['mcq_count']}"
        )
    
    if abs(tf_generated - config['truefalse_count']) > tolerance:
        warnings.append(
            f"True/False count off: generated {tf_generated}, expected {config['truefalse_count']}"
        )
    
    if abs(ta_generated - config['typeanswer_count']) > tolerance:
        warnings.append(
            f"Type Answer count off: generated {ta_generated}, expected {config['typeanswer_count']}"
        )
    
    # Validate each question
    for i, q in enumerate(questions, 1):
        # Type validation
        if q['type'] not in ['mcq', 'truefalse', 'typeanswer']:
            errors.append(f"Q{i}: Invalid type '{q['type']}'")
        
        # Common field validation
        if not q.get('question'):
            errors.append(f"Q{i}: Missing question text")
        
        if not q.get('correct_answer'):
            errors.append(f"Q{i}: Missing correct answer")
        
        if not q.get('difficulty') or q['difficulty'] not in ['easy', 'medium', 'hard']:
            errors.append(f"Q{i}: Invalid difficulty '{q.get('difficulty')}'")
        
        if not q.get('explanation'):
            warnings.append(f"Q{i}: Missing explanation")
        
        # Type-specific validation
        if q['type'] == 'mcq':
            if 'options' not in q:
                errors.append(f"Q{i}: MCQ missing options")
            elif len(q['options']) != 4:
                errors.append(f"Q{i}: MCQ must have 4 options, has {len(q['options'])}")
            elif set(q['options'].keys()) != {'A', 'B', 'C', 'D'}:
                errors.append(f"Q{i}: MCQ options must be A, B, C, D")
            
            if q['correct_answer'] not in ['A', 'B', 'C', 'D']:
                errors.append(f"Q{i}: MCQ answer must be A/B/C/D, got '{q['correct_answer']}'")
        
        elif q['type'] == 'truefalse':
            if q['correct_answer'].lower() not in ['true', 'false']:
                errors.append(f"Q{i}: True/False answer must be true/false, got '{q['correct_answer']}'")
        
        elif q['type'] == 'typeanswer':
            if not q.get('correct_answer'):
                errors.append(f"Q{i}: Type Answer missing correct answer")
            
            if 'acceptable_answers' not in q or not q['acceptable_answers']:
                warnings.append(f"Q{i}: Type Answer has no acceptable alternatives")
    
    # Check for duplicates
    question_texts = [q['question'].lower().strip() for q in questions]
    if len(question_texts) != len(set(question_texts)):
        duplicates = [text for text in question_texts if question_texts.count(text) > 1]
        errors.append(f"Duplicate questions found: {duplicates[:3]}...")
    
    # Difficulty distribution check
    easy_generated = sum(1 for q in questions if q['difficulty'] == 'easy')
    medium_generated = sum(1 for q in questions if q['difficulty'] == 'medium')
    hard_generated = sum(1 for q in questions if q['difficulty'] == 'hard')
    
    if abs(easy_generated - config['easy_count']) > tolerance:
        warnings.append(
            f"Easy questions: generated {easy_generated}, expected {config['easy_count']}"
        )
    
    if abs(medium_generated - config['medium_count']) > tolerance:
        warnings.append(
            f"Medium questions: generated {medium_generated}, expected {config['medium_count']}"
        )
    
    if abs(hard_generated - config['hard_count']) > tolerance:
        warnings.append(
            f"Hard questions: generated {hard_generated}, expected {config['hard_count']}"
        )
    
    return {
        'valid': len(errors) == 0,
        'errors': errors,
        'warnings': warnings,
        'statistics': {
            'total': total_generated,
            'by_type': {
                'mcq': mcq_generated,
                'truefalse': tf_generated,
                'typeanswer': ta_generated
            },
            'by_difficulty': {
                'easy': easy_generated,
                'medium': medium_generated,
                'hard': hard_generated
            }
        }
    }
```

---

**This comprehensive update provides:**

1. ✅ Three question types: MCQ, True/False, Type Answer
2. ✅ User controls for count of each type
3. ✅ Individual difficulty level controls
4. ✅ Content focus percentages (for MCQ/TF only)
5. ✅ Clear validation that sums match requirements
6. ✅ Multiple preset configurations
7. ✅ Live feedback and error messages
8. ✅ Complete database schema for all question types
9. ✅ Proper Moodle integration for each question type
10. ✅ Comprehensive validation for inputs and outputs
11. ✅ Support for acceptable answer alternatives in Type Answer
12. ✅ Case sensitivity option for Type Answer
13. ✅ Grading notes for Type Answer questions
