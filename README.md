# Tejas Mehar — Portfolio

A clean, responsive personal portfolio built with **HTML, Tailwind CSS, and JavaScript**.
Single-color (indigo) theme, works on all screen sizes. Now split into **separate pages**.

## Pages (one file each)
| Page | File |
|------|------|
| Home | `index.html` |
| About | `about.html` |
| Services | `services.html` |
| Education | `education.html` |
| Skills | `skills.html` |
| Projects | `projects.html` |
| Contact | `contact.html` |

## Shared files (used by every page)
```
portfolio/
├── index.html … contact.html   # the 7 pages
├── css/style.css               # all component styles (plain CSS) + animations
└── js/script.js                # menu, active link, reveal, skill bars, form
```
The **navbar** and **footer** are the same on every page, so a visitor can jump
between pages from anywhere. The current page is highlighted automatically.

## Run it
Open `index.html` in any browser — no build step (Tailwind loads via CDN, so keep
internet on the first load). In VS Code, use the **Live Server** extension → "Go Live".

## How the styling works
- **Layout & spacing** use Tailwind utility classes directly in the HTML (from the CDN).
- **Reusable components** (buttons, cards, badges, form inputs, timeline, etc.) live in
  `css/style.css` as plain CSS, so they look identical on every page with no duplication.
- **Theme color** — change the indigo palette in two places if you want a different color:
  1. the `--brand-*` variables at the top of `css/style.css`, and
  2. the `brand` colors in the `tailwind.config` block inside each page's `<head>`.

## Make it yours
1. **Your photo** — replace the placeholder image URLs (`placehold.co`) in
   `index.html` (hero) and `about.html`. Save your photo as `assets/tejas.jpg`.
2. **Projects** — edit titles, tags, descriptions and links in `projects.html`.
3. **Education** — put your real school/college names and years in `education.html`.
4. **Contact details** — update the phone number in `contact.html`.
5. **Social links** — replace every `href="#"` on GitHub / LinkedIn with your profiles.

## Contact form
The form (in `contact.html`) currently opens the visitor's email app (mailto). To receive
messages directly, connect it to **Formspree**, **Web3Forms**, or **EmailJS**.

## Editing the menu or footer
Because each page is a standalone HTML file, the navbar/footer markup is repeated in all 7
files. If you change a menu link, update it in each page (or ask to switch to a shared
JavaScript-injected header/footer to avoid the repetition).
