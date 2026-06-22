# CV Generator — HTML + Markdown + PDF Export (Puppeteer)

This project allows you to write your CV content in Markdown, inject it into a styled HTML template, and export a perfect A4 PDF using Puppeteer (headless Chrome).

The goal is to separate:
- **content** → `cv.md`
- **layout** → `resume.html`
- **styling** → `style.css`
- **PDF generation** → `index.js` (Node + Puppeteer)

This makes the CV easy to maintain, version, and export.

---

## 1. Project Structure

```
/project
│── resume.html        # HTML template
│── style.css          # Full styling (screen + print)
│── cv.md              # Markdown content (optional)
│── index.js           # Puppeteer script to generate PDF
│── photo.jpg          # Profile picture
│── README.md
```

---

## 2. How the HTML Template Works

The file `resume.html` contains the full CV layout, including:

- Sidebar (profile, skills, certifications, languages, hobbies)
- Main content (header, contact, experience, education)
- Timeline system with dots and vertical line
- Print‑optimized A4 layout

All styling is handled by `style.css`.

---

## 3. How to Generate a PDF (Node + Puppeteer)

### Install dependencies

```
npm install puppeteer
```

### Make sure your project supports ES modules

Add this to `package.json`:

```
{
  "type": "module"
}
```

### Run the generator

```
node index.js
```

This will create:

```
cv.pdf
```

in the project folder.

---

## 4. Puppeteer Script (index.js)

This script loads the HTML file and exports a perfect A4 PDF:

```js
import puppeteer from "puppeteer";

const browser = await puppeteer.launch({
  headless: "new",
  args: ["--no-sandbox", "--disable-setuid-sandbox"]
});

const page = await browser.newPage();

await page.goto(`file://${process.cwd()}/resume.html`, {
  waitUntil: "networkidle0"
});

await page.pdf({
  path: "cv.pdf",
  format: "A4",
  printBackground: true
});

await browser.close();

console.log("PDF generated: cv.pdf");
```

---

## 5. Print‑Optimized CSS

The file `style.css` includes:

- A4 page sizing
- Forced background printing
- Fixed timeline rendering
- No overflow issues
- Perfect alignment for PDF export

The key rule is:

```
@media print {
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
```

This ensures gradients, colors, dots, and timeline lines appear correctly in the PDF.

---

## 6. Editing Your CV

You can edit:

- `resume.html` → structure
- `style.css` → design
- `photo.jpg` → profile picture

If you want to use Markdown (`cv.md`) and inject it dynamically, you can add a small PHP or Node parser, but the current version uses static HTML for maximum PDF accuracy.

---

## 7. Exporting a New PDF

Any time you update your CV:

```
node index.js
```

A new `cv.pdf` is generated instantly.

---

## 8. Requirements

- Node 18+
- Puppeteer
- Chrome dependencies (Linux only)
- A local or absolute path to `resume.html`

---

## 9. License

This project is free to use and modify.

