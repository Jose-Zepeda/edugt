# Design System Master File

> **LOGIC:** When building a specific page, first check `design-system/pages/[page-name].md`.
> If that file exists, its rules **override** this Master file.
> If not, strictly follow the rules below.

---

**Project:** EduGT
**Generated:** 2026-10-15 09:53:44
**Category:** General

---

## Global Rules

### Color Palette (Violet Base — EduGT Gamified Learning)

#### Light Mode

| Role | Hex | CSS Variable | Usage |
|------|-----|--------------|-------|
| Primary Base | `#7C3AED` | `--edugt-primary` | Botones de acción, enlaces activos, elementos de marca |
| Primary Dark | `#6D28D9` | `--edugt-primary-dark` | Hover y estados presionados de elementos primarios |
| Primary Soft | `#F3E8FF` | `--edugt-primary-soft` | Fondos de píldoras, tags activos, selecciones suaves |
| Secondary | `#8B5CF6` | `--edugt-secondary` | Acentos secundarios, bordes iluminados |
| XP / Racha (Gold) | `#F59E0B` | `--edugt-amber` | Puntos XP, contadores de racha, medallas y trofeos |
| XP Soft | `#FEF3C7` | `--edugt-amber-soft` | Píldoras de racha y badges dorados |
| Éxito / Aprobado | `#10B981` | `--edugt-green` | Quizzes aprobados, lecciones completadas, progreso |
| Éxito Soft | `#D1FAE5` | `--edugt-green-soft` | Fondos de retroalimentación positiva |
| Acento Especial | `#F43F5E` | `--edugt-pink` | Insignias destacadas, alertas de atención |
| Acento Soft | `#FFE4E6` | `--edugt-pink-soft` | Fondos de acento especial |
| Destructivo / Error | `#EF4444` | `--edugt-danger` | Opciones incorrectas, errores de validación |
| Background | `#F8F7FF` | `--edugt-bg` | Fondo general lavanda ultra limpio |
| Card / Surface | `#FFFFFF` | `--edugt-card` | Contenedores, tarjetas de lecciones y módulos |
| Card Subtle | `#FAF9FF` | `--edugt-card-subtle` | Subtarjetas, tablas y bloques de código |
| Foreground / Text | `#1E1B4B` | `--edugt-text` | Títulos y texto de alto contraste (WCAG AAA) |
| Muted Foreground | `#5B5578` | `--edugt-muted` | Textos secundarios, descripciones y metadatos |
| Border | `#E9D5FF` | `--edugt-border` | Bordes suaves lavanda para tarjetas y divisores |

#### Dark Mode (`[data-theme="dark"]`)

| Role | Hex | CSS Variable | Usage |
|------|-----|--------------|-------|
| Primary Base | `#8B5CF6` | `--edugt-primary` | Violeta luminoso optimizado para contraste nocturno |
| Primary Dark | `#7C3AED` | `--edugt-primary-dark` | Hover nocturno |
| Primary Soft | `rgba(139,92,246,0.18)` | `--edugt-primary-soft` | Fondos de selección suaves |
| Logros / Trofeos / XP | `#38BDF8` | `--edugt-amber` | Cyan eléctrico moderno (elimina tonos mostaza/marrones en oscuro) |
| Logros Soft | `rgba(56,189,248,0.16)` | `--edugt-amber-soft` | Fondos de medallas y banners de celebración nocturnos |
| Éxito / Aprobado | `#34D399` | `--edugt-green` | Lecciones y quizzes superados |
| Acento Especial | `#FB7185` | `--edugt-pink` | Insignias raras nocturnas |
| Background | `#0F0D1B` | `--edugt-bg` | Fondo cósmico violeta profundo |
| Card / Surface | `#18152B` | `--edugt-card` | Superficies elevadas nocturnas |
| Card Subtle | `#131124` | `--edugt-card-subtle` | Superficies hundidas o código |
| Foreground / Text | `#F5F3FF` | `--edugt-text` | Texto blanco lavanda |
| Muted Foreground | `#A59DBF` | `--edugt-muted` | Texto secundario nocturno |
| Border | `#2D2550` | `--edugt-border` | Bordes nocturnos con tinte violeta |

**Color Notes:** Paleta UI/UX Pro Max especializada en EdTech y Gamificación con base violeta (`#7C3AED` / `#8B5CF6`). En modo oscuro, las recompensas y trofeos utilizan Cyan Eléctrico (`#38BDF8`) para una estética de alta tecnología y máximo atractivo visual sobre violeta cósmico.

### Typography

- **Heading Font:** Nunito
- **Body Font:** DM Sans
- **Mood:** claymorphism, clay, rounded, playful, candy, bubbly, soft, 3d, children, education, tactile, spring, nunito, dm sans
- **Google Fonts:** [Nunito + DM Sans](https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,400;0,500;0,700;1,400&family=Nunito:ital,wght@0,700;0,800;0,900;1,700)

**CSS Import:**
```css
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,400;0,500;0,700;1,400&family=Nunito:ital,wght@0,700;0,800;0,900;1,700&display=swap');
```

### Spacing Variables

| Token | Value | Usage |
|-------|-------|-------|
| `--space-xs` | `4px` / `0.25rem` | Tight gaps |
| `--space-sm` | `8px` / `0.5rem` | Icon gaps, inline spacing |
| `--space-md` | `16px` / `1rem` | Standard padding |
| `--space-lg` | `24px` / `1.5rem` | Section padding |
| `--space-xl` | `32px` / `2rem` | Large gaps |
| `--space-2xl` | `48px` / `3rem` | Section margins |
| `--space-3xl` | `64px` / `4rem` | Hero padding |

### Shadow Depths

| Level | Value | Usage |
|-------|-------|-------|
| `--shadow-sm` | `0 1px 2px rgba(0,0,0,0.05)` | Subtle lift |
| `--shadow-md` | `0 4px 6px rgba(0,0,0,0.1)` | Cards, buttons |
| `--shadow-lg` | `0 10px 15px rgba(0,0,0,0.1)` | Modals, dropdowns |
| `--shadow-xl` | `0 20px 25px rgba(0,0,0,0.15)` | Hero images, featured cards |

---

## Component Specs

### Buttons

```css
/* Primary Button */
.btn-primary {
  background: #D97706;
  color: #000000;
  padding: 12px 24px;
  border-radius: 8px;
  font-weight: 600;
  transition: all 200ms ease;
  cursor: pointer;
}

.btn-primary:hover {
  opacity: 0.9;
  transform: translateY(-1px);
}

/* Secondary Button */
.btn-secondary {
  background: transparent;
  color: #134E4A;
  border: 2px solid #0D9488;
  padding: 12px 24px;
  border-radius: 8px;
  font-weight: 600;
  transition: all 200ms ease;
  cursor: pointer;
}
```

### Cards

```css
.card {
  background: #F0FDFA;
  border-radius: 12px;
  padding: 24px;
  box-shadow: var(--shadow-md);
  transition: all 200ms ease;
  cursor: pointer;
}

.card:hover {
  box-shadow: var(--shadow-lg);
  transform: translateY(-2px);
}
```

### Inputs

```css
.input {
  padding: 12px 16px;
  border: 1px solid #E2E8F0;
  border-radius: 8px;
  font-size: 16px;
  transition: border-color 200ms ease;
}

.input:focus {
  border-color: #0D9488;
  outline: none;
  box-shadow: 0 0 0 3px #0D948820;
}
```

### Modals

```css
.modal-overlay {
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
}

.modal {
  background: white;
  border-radius: 16px;
  padding: 32px;
  box-shadow: var(--shadow-xl);
  max-width: 500px;
  width: 90%;
}
```

---

## Style Guidelines

**Style:** Minimalism & Swiss Style

**Keywords:** Clean, simple, spacious, functional, white space, high contrast, geometric, sans-serif, grid-based, essential

**Best For:** Enterprise apps, dashboards, documentation sites, SaaS platforms, professional tools

**Key Effects:** Subtle hover (200-250ms), smooth transitions, sharp shadows if any, clear type hierarchy, fast loading

### Page Pattern

**Pattern Name:** Hero + Features + CTA

- **Conversion Strategy:** Deep CTA placement. For CTA label text, verify at least 4.5:1 against the button fill; use 7:1 only when the product explicitly targets AAA normal-text contrast. Keep focus and component boundaries independently visible. Disable hero parallax under reduced motion and render its static final state.
- **CTA Placement:** Hero (sticky) + Bottom
- **Section Order:** Hero with headline/image > Value prop > Key features (3-5) > CTA section > Footer

---

## Anti-Patterns (Do NOT Use)


### Additional Forbidden Patterns

- ❌ **Emojis as icons** — Use SVG icons (Heroicons, Lucide, Simple Icons)
- ❌ **Missing cursor:pointer** — All clickable elements must have cursor:pointer
- ❌ **Layout-shifting hovers** — Avoid scale transforms that shift layout
- ❌ **Low contrast text** — Maintain 4.5:1 minimum contrast ratio
- ❌ **Instant state changes** — Always use transitions (150-300ms)
- ❌ **Invisible focus states** — Focus states must be visible for a11y

---

## Pre-Delivery Checklist

Before delivering any UI code, verify:

- [ ] No emojis used as icons (use SVG instead)
- [ ] All icons from consistent icon set (Heroicons/Lucide)
- [ ] `cursor-pointer` on all clickable elements
- [ ] Hover states with smooth transitions (150-300ms)
- [ ] Light mode: text contrast 4.5:1 minimum
- [ ] Focus states visible for keyboard navigation
- [ ] `prefers-reduced-motion` respected
- [ ] Responsive: 375px, 768px, 1024px, 1440px
- [ ] No content hidden behind fixed navbars
- [ ] No horizontal scroll on mobile
