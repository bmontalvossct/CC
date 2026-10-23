# ClassCheck Design System Guidelines

## Meta-inspired theme

The gradebook and student grade summary opt into this theme with `.meta-theme`.
Its reusable tokens live in `resources/css/meta-theme.css`. These rules take
precedence over the legacy palette and typography below within that scope.

- **Typography:** Segoe UI Variable Text, Segoe UI, then Helvetica/Arial. Use
  400 for body text, 500 for labels, and 600-650 for headings and key figures.
  Page/dialog titles are 23-28px, activity titles 15px, reading text 13-15px,
  and supporting metadata 11-12px. Preserve tabular numerals for scores.
- **Color:** Retain ClassCheck's golden-yellow palette for navigation, focus,
  and actions. Pair yellow surfaces with dark-gold text in light mode and
  lighter yellow text in dark mode to keep labels readable.
  Keep backgrounds neutral, with white cards in light mode and neutral charcoal
  cards in dark mode. Use independent semantic colors for passing, failing,
  missing work, and activity types; always retain their text labels and icons.
- **Composition:** Use an 8px spacing rhythm and 12-24px section gaps. The student
  summary uses a charcoal header with a yellow accent, a continuous category
  ledger, strong numeric hierarchy, and flat period totals. Avoid a separate
  rounded card for every field. Expanded coursework uses dividers and a yellow
  feedback rule, with restrained elevation reserved for the dialog itself.
- **Controls:** Use compact icon actions with accessible names, yellow selected
  states, visible focus rings, and short color transitions. Reduced-motion mode
  disables transitions. Maintain working keyboard, search, and collapse controls.
- **Feedback:** Display a labeled feedback block under every activity. Render
  stored text safely, preserve line breaks, and use “No feedback recorded.” for
  blank entries. Keep feedback readable at a maximum of 68 characters per line.
- **Grade summary:** Show Quizzes, Activities, and Exams as collapsed category
  rows first, with earned/possible points from the existing gradebook totals.
  Expanding a row reveals its individual scores, dates, and feedback. Group
  activities belong under Activities; laboratories and projects remain separate.
  Search and deficiency filters expand matching categories. Print every category
  even when it is collapsed on screen. Period grades follow the category summary.
- **Responsive/print:** Keep dialog content scrollable while its header and
  filters remain available. Prevent horizontal overflow at mobile widths. Print
  all attendance details and hide interactive controls.
- **Data:** The theme changes presentation only. Preserve grading formulas,
  existing scores, period availability, and database contents.

Reference: [Design at Meta: accessibility and design systems](https://www.meta.com/design-at-meta/blog/accessibility-and-design-systems/).

## Core Principles

1. Background Colors
- Light Mode: Clean crisp white background for all main page layouts, cards, and workspaces.
- Dark Mode: Default neutral dark mode (pure dark neutral background #121212 / hsl(0 0% 7%), card #1c1c1c / hsl(0 0% 11%), sidebar #0f0f0f / hsl(0 0% 6%)) with zero navy/dark blue saturation.
- Avoid dark blue or green overall tint on dark backgrounds.

2. Typography & Fonts
- Good typography makes content clear, attractive, and memorable: follow the 10 rules in [.agents/rules/frontend_typography.md](file:///.agents/rules/frontend_typography.md).
- Hierarchy: Main Heading (H1: text-2xl/3xl, font-semibold), Subheading (H2: text-lg/xl, font-medium), Section Title (H3: text-sm/base, font-semibold), Body text (text-sm, leading-relaxed), Eyebrows/Captions (text-xs/10px uppercase tracking-wider).
- Readability & Pairing: Primary font family is Atkinson Hyperlegible (with fallback sans-serif) for all UI components. Monospace (`font-mono`) is used for technical identifiers, student numbers, percentages, and seat numbers.
- Weight Restraint: Standard (400) and medium/semibold (500-600) font weights are used across typography. Avoid excessive black/extrabold weights.
- Alignment: Left-aligned for natural text reading flow; center-aligned for modals/empty states; right-aligned for tabular numbers and scores.
- Whitespace: Generous spacing around cards (`p-5`, `p-6`) and sections (`gap-4`, `gap-6`) to prevent cramped layouts.
- Dark Mode: Font color is pure white (#FFFFFF / hsl(0 0% 100%)) with crisp light/white borders.

3. Sidebar & Menu Selected States
- When any item is selected or active in the sidebar/navigation menu, its font must be pure white (text-white) with matching white icon, set on a dark green primary background (data-[active=true]:bg-primary data-[active=true]:text-white).

4. Button Colors & Styling
- Buttons use white as background in light mode (bg-white) and card background in dark mode (dark:bg-card) with crisp light/white border in dark mode (dark:border-white/30 dark:text-white).
- Primary buttons: Clean white background with primary outline (border border-primary) and primary text (text-primary).
- Destructive / Delete buttons: Clean white background with red outline (border border-rose-600) and red text (text-rose-700).
- Secondary / Action buttons: Clean white background with subtle outline (border border-border) and foreground text (text-foreground).
- Golden Yellow Hover State: When any button is hovered, its background changes to golden yellow (bg-amber-400 / #f59e0b) with white text (text-white) and white icons for visual feedback.

5. Status Tags & Badges
- **Strictly Single-Line**: Badges must NEVER wrap across lines. Always include `whitespace-nowrap shrink-0`.
- **Horizontal Margin**: Badges must have margin to their left and right (`mx-1` / `mx-1.5`) so they never press against adjacent headings, labels, or sibling badges.
- Status tags and badges must use white font with darker background colors for high contrast and legibility:
- Primary / Section Badges: Dark green background with white text (.badge-primary)
- Success / Present Badges: Dark emerald (bg-emerald-700 / bg-emerald-800) with white text (.badge-success)
- Warning / Late Badges: Dark amber (bg-amber-700 / bg-amber-800) with white text (.badge-warning)
- Danger / Absent Badges: Dark red/rose (bg-rose-700 / bg-rose-800) with white text (.badge-danger)
- Muted / Info Badges: Dark slate/zinc (bg-slate-700 / bg-slate-800) with white text (.badge-muted)
- Exam Badges: Dark purple (bg-purple-800) with white text (.badge-purple)
- Quiz Badges: Dark blue (bg-blue-800) with white text (.badge-info)
- Project Badges: Dark emerald (bg-emerald-800) with white text
- Reporting Badges: Dark amber (bg-amber-800) with white text

6. Summaries & Summary Tables
- In summaries and summary tables (such as student attendance counts, absent/late counts, rates, and cumulative breakdowns), do not use pill/box background status tags.
- Instead, use only font/text, where the font color is styled directly with the status color (e.g. text-emerald-700 / text-emerald-400 for present, text-amber-700 / text-amber-400 for late, text-rose-700 / text-rose-400 for absent) for clean, high-density tabular presentation.
