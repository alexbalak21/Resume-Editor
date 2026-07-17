# Full‑Stack Laravel CV Builder — Project Specification

This document describes the architecture, features, data model, workflow, and Markdown import/export system of the Laravel application that manages user accounts, multiple resumes, customizable CV templates, and Markdown‑based editable sections.

---

# 1. Project Overview

This application is a full‑stack Laravel platform where users can:

- Create an account
- Create multiple resumes (CVs)
- Customize each resume with:
  - A profile image (stored as Base64 in the database)
  - A language setting
  - A name/title
  - Multiple editable sections
- Edit each section using Markdown
- Choose icons for sections and skills
- Manage skill levels (beginner, advanced, expert, percentage, etc.)
- Manage language levels (basic, intermediate, advanced, fluent)
- Export the CV using a rendering engine (HTML → PDF)
- Import and export a full CV as a Markdown file
- Eventually choose between multiple CV templates
- Later: build a template editor or template builder

The system behaves like a lightweight CMS dedicated to CV creation.

---

# 2. Core Concepts

## Users
Each user has:
- Email
- Password
- Profile settings (optional)
- Multiple resumes

## Resumes (CVs)
Each resume has:
- Name (e.g., “Developer CV”, “React CV”, “Java CV”)
- Language (e.g., FR, EN)
- Profile image (Base64 encoded)
- Template ID (for future template selection)
- Multiple sections
- Metadata (created_at, updated_at)

## Sections
Each section corresponds to a Markdown block.

Examples:
- `# HEADER`
- `# PROFILE`
- `# CONTACT`
- `# SKILLS`
- `# EXPERIENCE`
- `# EDUCATION`
- `# CERTIFICATIONS`
- `# LANGUAGES`
- `# HOBBIES`

Each section contains:
- Title (editable)
- Icon (optional)
- Markdown content (textarea)
- Section type (optional, for template logic)

## Skills (special section type)
A skill entry contains:
- Icon (optional)
- Name
- Level (beginner, intermediate, advanced, expert)
- Or numeric percentage (0–100)
- Belongs to a resume

## Languages (special section type)
A language entry contains:
- Name
- Level (one of the 4 official levels below)

### **Language Levels**
- **basic** — elementary usage, simple communication  
- **intermediate** — conversational, work‑capable  
- **advanced** — professional fluency  
- **fluent** — native or near‑native mastery  

---

# 3. Database Structure

## users
- id  
- name  
- email  
- password  
- timestamps  

## resumes
- id  
- user_id (FK)  
- name  
- language  
- profile_image_base64 (TEXT)  
- template_id (nullable)  
- timestamps  

## sections
- id  
- resume_id (FK)  
- title  
- icon (nullable)  
- markdown_content (LONGTEXT)  
- order_index  
- section_type  
- timestamps  

## skills
- id  
- resume_id (FK)  
- icon (nullable)  
- name  
- level_type (enum: beginner, intermediate, advanced, expert, percentage)  
- level_value (nullable integer)  
- timestamps  

## languages
- id  
- resume_id (FK)  
- name  
- level (enum: basic, intermediate, advanced, fluent)  
- timestamps  

---

# 4. Application Pages

## 4.1 Dashboard
- List of user resumes  
- Create new resume  
- Edit resume  
- Delete resume  

## 4.2 Resume Editor
A full page dedicated to editing a CV.

Sections:
1. Profile Image (Base64)
2. Header Section
3. Profile Section
4. Contact Section
5. Skills Section
6. Experience Section
7. Education Section
8. Certifications Section
9. Languages Section
10. Hobbies Section

All sections are editable using Markdown.

## 4.3 Template Preview
- Render the CV using the selected template  
- Live preview  
- Export to PDF  

---

# 5. Markdown Editing

Each section is stored as Markdown.

Example:

```
# PROFILE
Full‑Stack Developer specialized in Java, Spring Boot, React, and TypeScript.
```

Markdown → HTML conversion happens during rendering.

---

# 6. Template System

## Phase 1: Single Template
- One HTML/CSS template  
- Sections injected dynamically  
- Profile image rendered from Base64  

## Phase 2: Multiple Templates
- Template selection per resume  
- Templates stored in `/resources/views/templates/`  
- Each template defines layout, colors, icons  

## Phase 3: Template Builder (future)
- Drag‑and‑drop layout editor  
- Custom color themes  
- Custom fonts  
- Custom section ordering  

---

# 7. PDF Generation

### Option A: Puppeteer (recommended)
- Perfect CSS rendering  
- Supports gradients, flexbox, pseudo‑elements  
- Pixel‑perfect A4 PDFs  

### Option B: Laravel Snappy (wkhtmltopdf)
- Faster  
- Less CSS support  

---

# 8. API Endpoints (Laravel)

## Auth
- POST `/register`  
- POST `/login`  
- POST `/logout`  

## Resumes
- GET `/resumes`  
- POST `/resumes`  
- GET `/resumes/{id}`  
- PUT `/resumes/{id}`  
- DELETE `/resumes/{id}`  

## Sections
- POST `/resumes/{id}/sections`  
- PUT `/sections/{id}`  
- DELETE `/sections/{id}`  

## Skills
- POST `/resumes/{id}/skills`  
- PUT `/skills/{id}`  
- DELETE `/skills/{id}`  

## Languages
- POST `/resumes/{id}/languages`  
- PUT `/languages/{id}`  
- DELETE `/languages/{id}`  

## PDF Export
- GET `/resumes/{id}/pdf`  

## Markdown Import/Export
- GET `/resumes/{id}/export-md`  
- POST `/resumes/import-md`  

---

# 9. Markdown Import & Export System

The application supports full import/export of a resume using a single `.md` file.

## 9.1 Export Format

A CV is exported as a Markdown file containing:

```
# META
name: Developer CV
language: fr
template: default
profile_image_base64: <BASE64_STRING>

# LANGUAGES
## Language
name: English
level: fluent

## Language
name: French
level: advanced

## Language
name: German
level: basic
```

## 9.2 Import Workflow

1. User uploads a `.md` file  
2. Laravel parses the file  
3. Extracts:
   - META block  
   - Sections  
   - Skills  
   - Languages  
4. Validates:
   - Required fields  
   - Base64 image  
   - Section structure  
   - Language level enum  
5. Creates or updates the resume  
6. Stores everything in the database  

## 9.3 Validation Rules

- `# META` must exist  
- `name` and `language` required  
- Base64 image must be valid  
- Sections must start with `#`  
- Skills must start with `## Skill`  
- Languages must start with `## Language`  
- Unknown sections allowed  

---

# 10. Future Features

- Multiple templates  
- Template builder  
- Drag‑and‑drop section ordering  
- AI‑assisted CV writing  
- Import LinkedIn profile  
- Export to DOCX  
- Public CV sharing link  

---

# 11. Summary

This Laravel application is a modular, scalable CV builder with:

- User accounts  
- Multiple resumes per user  
- Markdown‑editable sections  
- Base64 profile images  
- Skill management  
- Language level management  
- Template rendering  
- PDF export  
- Full Markdown import/export  

It is designed to evolve into a complete CV‑building platform with customizable templates and advanced editing tools.
