---
version: alpha
name: Apple Minimal
description: A clean, premium, content-first system with restrained color and spacious editorial hierarchy.
colors:
  primary: "#0071E3"
  secondary: "#0066CC"
  tertiary: "#1D1D1F"
  neutral: "#FFFFFF"
  surface: "#F5F5F7"
  on-surface: "#1D1D1F"
  on-primary: "#FFFFFF"
  border: "#E5E7EB"
  muted: "#86868B"
  error: "#D93025"
typography:
  headline-display:
    fontFamily: "SF Pro Display"
    fontSize: "48px"
    fontWeight: 700
    lineHeight: "58px"
    letterSpacing: "0px"
  headline-lg:
    fontFamily: "SF Pro Display"
    fontSize: "40px"
    fontWeight: 600
    lineHeight: "52px"
    letterSpacing: "-0.01em"
  headline-md:
    fontFamily: "SF Pro Display"
    fontSize: "34px"
    fontWeight: 600
    lineHeight: "36px"
    letterSpacing: "0.004em"
  headline-sm:
    fontFamily: "SF Pro Text"
    fontSize: "29px"
    fontWeight: 600
    lineHeight: "35px"
    letterSpacing: "0px"
  body-lg:
    fontFamily: "SF Pro Display"
    fontSize: "24px"
    fontWeight: 400
    lineHeight: "36px"
    letterSpacing: "0.01em"
  body-md:
    fontFamily: "SF Pro Display"
    fontSize: "17px"
    fontWeight: 400
    lineHeight: "1.6"
    letterSpacing: "0px"
  body-sm:
    fontFamily: "SF Pro Text"
    fontSize: "14px"
    fontWeight: 400
    lineHeight: "20px"
    letterSpacing: "0px"
  label-lg:
    fontFamily: "SF Pro Text"
    fontSize: "17px"
    fontWeight: 400
    lineHeight: "44px"
    letterSpacing: "0px"
  label-md:
    fontFamily: "SF Pro Text"
    fontSize: "15px"
    fontWeight: 400
    lineHeight: "1.4"
    letterSpacing: "0px"
  label-sm:
    fontFamily: "SF Pro Text"
    fontSize: "12px"
    fontWeight: 600
    lineHeight: "16px"
    letterSpacing: "0.04em"
  caption:
    fontFamily: "SF Pro Text"
    fontSize: "12px"
    fontWeight: 400
    lineHeight: "16px"
    letterSpacing: "0px"
rounded:
  none: 0px
  sm: 4px
  md: 8px
  lg: 16px
  xl: 24px
  full: 9999px
spacing:
  xs: 8px
  sm: 16px
  md: 24px
  lg: 44px
  xl: 64px
  xxl: 126px
  gutter: 24px
  margin: 16px
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.on-primary}"
    typography: "{typography.label-lg}"
    rounded: "{rounded.full}"
    padding: "11px 21px"
    height: "44px"
  button-primary-hover:
    backgroundColor: "{colors.secondary}"
    textColor: "{colors.on-primary}"
    rounded: "{rounded.full}"
  button-secondary:
    backgroundColor: "transparent"
    textColor: "{colors.secondary}"
    typography: "{typography.label-lg}"
    rounded: "{rounded.full}"
    padding: "11px 21px"
    height: "44px"
  button-link:
    backgroundColor: "transparent"
    textColor: "{colors.secondary}"
    typography: "{typography.label-lg}"
    rounded: "{rounded.none}"
    padding: "0px"
  card:
    backgroundColor: "{colors.neutral}"
    textColor: "{colors.on-surface}"
    rounded: "{rounded.sm}"
    padding: "16px"
  input:
    backgroundColor: "{colors.neutral}"
    textColor: "{colors.on-surface}"
    rounded: "{rounded.sm}"
    padding: "11px 16px"
  nav-link:
    textColor: "{colors.tertiary}"
    typography: "{typography.body-sm}"
  hero-panel:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.none}"
    padding: "64px"

# Apple Minimal

## Overview

This is a restrained, premium, product-led system that feels calm, confident, and highly polished. The tone is professional rather than playful, with large editorial headings, generous white space, and a strong emphasis on clarity and conversion. It favors a dense navigation bar at the top and spacious hero/product sections below, keeping attention on the content and the call to action.

## Colors

- **Primary (#0071E3):** Apple blue, used for the main action buttons and other key interactive accents. It is vivid but controlled, standing out cleanly against the white and light gray canvas.
- **Secondary (#0066CC):** A slightly deeper link blue for outlines, secondary actions, and textual interactive affordances. It supports the primary color without competing with it.
- **Tertiary (#1D1D1F):** The core ink tone for headlines, navigation, and body copy. This near-black creates the crisp, high-contrast editorial look the interface relies on.
- **Neutral (#FFFFFF):** Pure white for the page base and button text on filled actions. It keeps the UI bright, minimal, and uncluttered.
- **Surface (#F5F5F7):** A soft Apple-style gray used for section backgrounds and subtle tonal separation. It replaces heavy borders or shadows in most places.
- **On-surface (#1D1D1F):** The primary readable text color on light surfaces. It matches the brand’s dark ink and ensures strong contrast.
- **On-primary (#FFFFFF):** White text on blue buttons for maximum legibility and a crisp branded look.
- **Border (#E5E7EB):** A light neutral border used sparingly for cards and structural separation. It supports the flat visual language without adding weight.
- **Muted (#86868B):** A secondary gray for low-emphasis metadata, utility text, and inactive navigation details.
- **Error (#D93025):** Reserved for validation and destructive states; it should appear rarely in this otherwise restrained interface.

## Typography

The system is built on SF Pro Display and SF Pro Text, matching the familiar Apple voice: modern, neutral, and highly legible. Display sizes handle the hero and section headings with medium-to-bold weights, while SF Pro Text supports navigation, buttons, and supporting UI with a lighter, more functional cadence.

Headlines should stay compact and confident, with clear hierarchy from 48px down through 29px. Body copy is larger than typical web UI, especially in the hero, reinforcing a premium editorial feel. Labels and navigation text should remain simple and unadorned; uppercase is not a core pattern here, so keep letter spacing subtle and avoid decorative tracking.

## Layout

The layout is centered and highly symmetrical, with broad horizontal breathing room and vertically stacked content blocks. Sections use a full-width canvas with softly differentiated surface bands rather than framed containers, and the hero is allowed to dominate the viewport.

Spacing follows a calm rhythm built from 8px, 16px, 24px, 44px, and 126px steps. Use the smaller values for inline gaps and UI relationships, and the larger values for section separation, hero padding, and product presentation. Cards and controls should feel spacious but not oversized; button padding and vertical spacing are tuned for a polished, touch-friendly desktop experience.

## Elevation & Depth

Depth is intentionally flat. Instead of shadows, the interface relies on tonal layering, subtle borders, and strong typographic hierarchy. When visual separation is needed, use the light surface gray and 1px borders rather than elevated surfaces or heavy blur.

Shadow should be avoided for most UI elements; the brand’s premium feel comes from spacing, contrast, and photography rather than 3D depth. If a component needs emphasis, increase contrast or size before adding shadow.

## Shapes

The shape language is soft and refined, with rounded pill buttons and very modest rounding for cards. Interactive controls lean into full-radius pills, while containers remain near-rectangular with an 8px corner radius.

Overall, the system feels smooth rather than bubbly. Avoid sharp geometric accents unless they are functional, such as icons or product imagery.

## Components

Buttons are the clearest expression of the system. Use `button-primary` for the main call to action: filled blue, white text, pill-shaped, and sized to a 44px touch target with 11px 21px padding. `button-secondary` should be transparent with a blue outline and blue text, used for alternate actions. `button-link` is reserved for low-emphasis textual navigation and should remain unboxed and underlined only when appropriate.

Cards should be simple white panels with a 1px border, `rounded.sm`, and 16px padding. Keep shadows off unless there is a very specific need for emphasis. Inputs should follow the same card-like restraint: white background, light border, subtle radius, and comfortable internal padding.

Navigation links are small, quiet, and evenly spaced across the top bar. They should use the dark tertiary ink for regular states and remain visually understated. Hero panels can use the `surface` color to create a gentle section break without appearing like a distinct container.

For any chips, tooltips, or secondary badges, keep them minimal: small typographic scale, neutral surfaces, and blue only when the element is interactive or status-bearing.

## Do's and Don'ts

- Do keep the page airy and center-aligned, with generous vertical spacing between major sections.
- Do use SF Pro Display for headlines and SF Pro Text for controls, navigation, and supporting copy.
- Do prefer flat surfaces, subtle borders, and contrast over shadows or heavy gradients.
- Do make primary actions unmistakable with the blue filled pill button style.
- Don't introduce vivid extra accent colors beyond the restrained blue, ink, gray, and error palette.
- Don't over-round cards or sections; reserve full pill shapes for buttons and small controls.
- Don't compress body copy into tight line lengths or small sizes; the system depends on readable, spacious typography.
- Don't add decorative effects that distract from the product imagery and editorial hierarchy.
