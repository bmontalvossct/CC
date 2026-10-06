# ClassCheck Design System Guidelines

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
