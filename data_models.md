# Data Models — Laravel CV Builder

This document defines all Eloquent models, their fields, relationships, and intended usage inside the Full‑Stack Laravel CV Builder.

---

# 1. Overview

The application uses structured models for all CV components:

- Resume  
- Section (Markdown blocks)  
- Experience  
- Education  
- Certification  
- Skill  
- Language  
- SoftSkill  

Each model belongs to a `Resume`, allowing users to create multiple CVs with fully structured content.

---

# 2. Models & Fields

## 2.1 User
Laravel default user model.

### Fields
- id  
- name  
- email  
- password  
- timestamps  

### Relationships
```php
public function resumes() { return $this->hasMany(Resume::class); }
```

---

## 2.2 Resume

### Fields
- id  
- user_id (FK)  
- name  
- language  
- profile_image_base64 (TEXT)  
- template_id (nullable)  
- timestamps  

### Relationships
```php
public function sections()      { return $this->hasMany(Section::class); }
public function experiences()   { return $this->hasMany(Experience::class); }
public function educations()    { return $this->hasMany(Education::class); }
public function certifications() { return $this->hasMany(Certification::class); }
public function skills()        { return $this->hasMany(Skill::class); }
public function languages()     { return $this->hasMany(Language::class); }
public function softSkills()    { return $this->hasMany(SoftSkill::class); }
```

---

## 2.3 Section (Markdown-based)

### Fields
- id  
- resume_id (FK)  
- title  
- icon (nullable)  
- markdown_content (LONGTEXT)  
- order_index  
- section_type  
- timestamps  

### Notes
Used for:
- Header  
- Profile  
- Contact  
- Hobbies  
- Any future Markdown section  

---

## 2.4 Experience (Structured)

### Fields
- id  
- resume_id (FK)  
- position_title  
- company  
- location  
- start_date  
- end_date  
- description (LONGTEXT)  
- timestamps  

### Notes
Structured replacement for timeline Markdown.

---

## 2.5 Education (Structured)

### Fields
- id  
- resume_id (FK)  
- school  
- diploma  
- year  
- timestamps  

### Notes
Simple structured education entries.

---

## 2.6 Certification (Structured)

### Fields
- id  
- resume_id (FK)  
- name  
- organization  
- year (nullable)  
- timestamps  

### Notes
Supports optional year.

---

## 2.7 Skill (Structured)

### Fields
- id  
- resume_id (FK)  
- icon (nullable)  
- name  
- level_type (enum: beginner, intermediate, advanced, expert, percentage)  
- level_value (nullable integer)  
- timestamps  

### Notes
Supports both named levels and numeric percentages.

---

## 2.8 Language (Structured)

### Fields
- id  
- resume_id (FK)  
- name  
- level (enum: basic, intermediate, advanced, fluent)  
- timestamps  

### Notes
CEFR‑aligned 4‑level system.

---

## 2.9 SoftSkill (Structured)

### Fields
- id  
- resume_id (FK)  
- name  
- icon (nullable)  
- description (nullable)  
- timestamps  

### Notes
Used for communication, teamwork, leadership, etc.

---

# 3. Relationships Summary

## Resume → Sections
One resume contains multiple Markdown sections.

## Resume → Experience / Education / Certifications
Structured entries stored separately for clean rendering.

## Resume → Skills / Languages / SoftSkills
Each resume has its own structured skill sets.

---

# 4. Import/Export Mapping

Each structured model maps to Markdown blocks:

### Experience
```
## Entry
position_title: ...
company: ...
location: ...
start_date: ...
end_date: ...
description: |
  - bullet
  - bullet
```

### Education
```
## Education
school: ...
diploma: ...
year: ...
```

### Certification
```
## Certification
name: ...
organization: ...
year: ...
```

### Skill
```
## Skill
name: ...
icon: ...
level_type: ...
level_value: ...
```

### Language
```
## Language
name: ...
level: ...
```

### SoftSkill
```
## SoftSkill
name: ...
icon: ...
description: ...
```

---

# 5. Future Extensions

- Template-specific fields  
- Section ordering stored in DB  
- Custom skill categories  
- Public CV sharing links  
- AI-assisted CV generation  

---

# 6. Summary

This data model provides:

- Full structure  
- Clean relationships  
- Markdown + structured hybrid system  
- Perfect compatibility with templates  
- Easy import/export  
- Scalable architecture for future features

It is the foundation of the entire Laravel CV Builder.
