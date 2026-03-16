# Design System & UI Guidelines

## 1. Overview
The IQA Metal Label APP avoids heavy CSS frameworks like Tailwind or Bootstrap. Everything is styled through a global `style.css` file. The goal of this UI is to look incredibly clean, professional, and accessible. 

To maintain consistency throughout the app, future developers and agents must adhere to the design decisions and CSS variables outlined below.

---

## 2. CSS Variables (The Foundation)
You can see these initialized in `index.php`. All new CSS written must use these global `--var` tags instead of hardcoding colors. This makes it trivial to change themes later if needed.

```css
:root {
    /* Typography */
    --font-main: Arial, sans-serif;
    
    /* Colors - Light Theme Default */
    --text-main: #333333;
    --text-secondary: #666666;
    --link-color: #007bff;
    
    /* Layout & Spacing */
    --spacing: 20px;
    --border-radius: 8px;
    
    /* Backgrounds & Panels */
    --bg-page: #f8f9fa;
    --bg-panel: #ffffff;
    --border-color: #e0e0e0;

    /* Actions */
    --btn-primary-bg: #007bff;
    --btn-primary-text: #ffffff;
    --btn-success-bg: #28a745;
    --btn-success-text: #ffffff;
    --btn-danger-bg: #dc3545;
    --btn-danger-text: #ffffff;
}
```

---

## 3. Typography & Basics
* **Font Family:** `--font-main` (Arial/Sans-Serif) for high legibility.
* **Headers (`h1`, `h2`, `h3`):** Use `--text-main`. Keep them crisp with a bottom margin of `0.5em` or `1em`.
* **Paragraphs & Labels (`p`, `label`):** Use `--text-secondary`.
* **Links (`a`):** Interactive color (`--link-color`), no underline by default. Underline only on `:hover`.

---

## 4. UI Components

### Panels & Cards
All major content (forms, tables, dashboard stats) should be contained within a "card" or "panel" to separate it from the page background.
```css
.panel {
    background-color: var(--bg-panel);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: var(--spacing);
    margin-bottom: var(--spacing);
    box-shadow: 0 2px 4px rgba(0,0,0,0.05); /* Soft, premium shadow */
}
```

### Forms & Inputs
Forms need to be highly readable since this system involves precise hardware specifications.
* Inputs, Selects, and Textareas should span `100%` of their container width by default.
* Provide a solid `padding` (e.g., `10px`) so they are easy to click.
* On `:focus`, wrap the input in a ring matching the `--link-color` to clearly show active state.

```css
.form-group {
    margin-bottom: 15px;
}
input[type="text"], select {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-family: var(--font-main);
}
input[type="text"]:focus, select:focus {
    outline: none;
    border-color: var(--link-color);
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}
```

### Buttons
Buttons should feel tactile and clearly indicate their action type:
* **Primary Actions** (e.g., "Search", "Next"): Use `--btn-primary-bg`.
* **Positive/Creation Actions** (e.g., "Generate Label", "Add Laptop"): Use `--btn-success-bg`.
* **Destructive Actions** (e.g., "Delete", "Remove"): Use `--btn-danger-bg`.

All buttons should have a quick `transition: background 0.2s` for a smooth hover effect.

---

## 5. Layout (Flex & Grid)
* Instead of messy floats or inline-blocks, rely heavily on `display: flex;` and `gap` for aligning small elements (like buttons in a row or navbar links).
* Rely on `display: grid;` for laying out the dashboard numbers or structurally splitting two halves of a form.

---

## 6. Javascript Interactivity Guidelines
When an action updates data, try to avoid traditional browser POST redirect loops (`header("Location: index.php")`).
Instead:
1. Prevent default form submission.
2. Send data using Javascript `fetch()`.
3. Show a lightweight CSS spinner/loader on the button.
4. Update the DOM explicitly (e.g., change target to "Sold") or pop up a subtle notification toast upon success. 
This aligns with the goal of creating a fast, snappy local Web-App.
