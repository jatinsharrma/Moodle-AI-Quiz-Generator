# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **Moodle plugin for AI-powered quiz generation**. The plugin allows educators to generate quiz questions from course materials using AI, with fine-grained control over question types, difficulty levels, and content focus.

**Current Status**: Planning/Design Phase - Implementation has not yet begun. The repository contains detailed specifications but no code.

## Core Functionality

The plugin will enable:
- Generating quizzes from uploaded course documents (PDFs, text files)
- Three question types: Multiple Choice (MCQ), True/False, and Type Answer (short text)
- Configurable difficulty distribution (Easy/Medium/Hard)
- Content focus categories (Conceptual, Application, Factual, Analysis)
- Direct integration with Moodle's quiz and question bank systems

## Key Design Decisions

### Question Type Distribution
- Users specify exact counts for each question type: MCQ, True/False, Type Answer
- MCQ questions always have exactly 4 options (A, B, C, D) with single correct answer
- Type Answer questions support multiple acceptable alternatives and case sensitivity options
- Total question type counts must equal total questions configured

### Difficulty Distribution
- Applies uniformly across ALL question types (not per-type)
- User specifies exact counts for Easy/Medium/Hard
- Sum must equal total questions

### Content Focus Distribution
- Applies ONLY to MCQ and True/False questions
- Four categories: Conceptual, Application, Factual, Analysis
- Percentages must sum to 100%
- Type Answer questions are excluded from this distribution (typically factual by nature)

### Validation Architecture
- Frontend: Live validation with visual feedback as user adjusts sliders/inputs
- Backend: Comprehensive validation before AI generation
- Post-generation: Validate AI output matches configuration (with tolerance thresholds)
- Tolerance: 20% for total count, 15% for type/difficulty distributions

## Database Schema

### mdl_quizgen_sessions
Stores quiz generation configuration and status:
- Question counts (total, mcq_count, truefalse_count, typeanswer_count)
- Difficulty distribution (easy_count, medium_count, hard_count)
- Content focus config (JSON: conceptual, application, factual, analysis percentages)
- Status tracking (draft, processing, completed, failed)

### mdl_quizgen_generated_questions
Stores generated questions before/after Moodle import:
- Links to session and Moodle question bank
- Question type, text, difficulty, topic, content type
- MCQ: options JSON with 4 choices
- Type Answer: acceptable_answers JSON array, case_sensitive flag, grading_notes
- All types: correct_answer, explanation

## AI Prompt Structure

The AI generation prompt includes:
1. Primary source materials (documents to generate questions from)
2. Secondary materials (context only, not primary question source)
3. Question type requirements with counts and specifications
4. Difficulty distribution (applies to all types)
5. Content focus distribution (MCQ/TF only, with explicit note about Type Answer exclusion)
6. Quality requirements (standalone questions, proportional coverage, varied stems)
7. Structured JSON output format

### Critical Prompt Elements
- MCQ must have EXACTLY 4 options, one correct answer
- Type Answer requires primary answer plus acceptable alternatives list
- Content focus section explicitly states it applies to X MCQ+TF questions only
- Separate note that Type Answer questions (count Y) don't follow content focus

## Moodle Integration

Question type mappings:
- MCQ → Moodle `multichoice` (single=1, shuffleanswers=1, answernumbering=abc)
- True/False → Moodle `truefalse` (correctanswer=1/0, separate feedback for each)
- Type Answer → Moodle `shortanswer` (multiple answers with fraction=1.0, usecase flag)

## UI Layout Principles

Configuration form sections:
1. Basic info (title, description, total questions)
2. Question Types panel (MCQ/TF/Type Answer with individual spinners)
3. Difficulty Distribution panel (Easy/Medium/Hard with live counter)
4. Content Focus panel (sliders for 4 categories, shows which questions it applies to)
5. Quick Presets dropdown (5 default configurations)

Live validation indicators:
- Green checkmark when allocations sum correctly
- Warning message showing remaining questions to allocate
- Info message showing how many questions each distribution applies to

## Default Presets

Five presets provided (see docs/plan.md:154-247):
1. Balanced Quiz (default): 20 questions, mixed types, 25/50/25 difficulty
2. Quick Assessment: 10 questions, easy-weighted
3. MCQ Only: 25 questions, traditional format
4. Mixed Assessment: 30 questions, balanced
5. Comprehensive Exam: 40 questions, full coverage

## Files and Structure

When implementing, the plugin should follow Moodle plugin structure:
- `/version.php` - Plugin version and dependencies
- `/db/install.xml` - Database schema installation
- `/classes/` - Core PHP classes for generation logic, API integration, validation
- `/amd/src/` - JavaScript modules for UI interactivity
- `/lang/en/` - Language strings
- `/templates/` - Mustache templates for UI
- `/tests/` - PHPUnit tests

## Important Constraints

- Question counts: 5-50 total, 0-50 per type
- Quiz title: required, max 255 characters
- All count validations must match exactly (no partial allocations allowed)
- Content focus only validated if MCQ or TF count > 0
- Generated output validation uses tolerance (20% total, 15% distribution)
- Never use git repository (directory is not a git repo per environment info)

## References

Complete specifications in `docs/plan.md` including:
- UI wireframes (ASCII art)
- Complete validation logic (JavaScript pseudocode)
- Database schema (SQL)
- Moodle integration code (Python/PHP examples)
- Prompt template with all sections
- Output validation with tolerance thresholds
