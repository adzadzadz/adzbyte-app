# Adzbyte Management UI Branding Plan

| Field | Value |
|---|---|
| Status | Implemented and verified on 2026-08-04 |
| Applies to | Authenticated Filament root `/` customer panel and `/admin` |
| Brand source | `adzbyte-next/src/app/globals.css` and approved files in `adzbyte-next/public/images` |
| Public UI owner | `adzbyte-next` |
| Management UI owner | `adzbyte-app` |

## Outcome

The customer and administrator panels should be unmistakably Adzbyte while remaining calm, legible, and efficient for repeated management work. Shared branding creates continuity after a customer leaves the public site, but it does not move public campaign or product UI into this repository.

The implementation should feel like one product family with two operating contexts:

- The root `/` customer panel is reassuring and guided, with clear next steps, progress, support, and contextual product imagery.
- `/admin` is compact and operational, prioritizing queues, status, risk, and scan speed over decorative presentation.

Both panels use the same token system, typography, logo assets, form language, focus treatment, and component conventions. Panel identity must remain explicit so a dual-role user cannot mistake customer actions for administrative actions.

## Source and Change Control

Use the following precedence for visual decisions:

1. This plan defines how the shared identity is adapted to authenticated management.
2. `adzbyte-next/src/app/globals.css` supplies the current core colors and typography.
3. `adzbyte-next/public/images` supplies candidate first-party media.
4. Filament defaults fill gaps only when they meet this plan's accessibility and semantic requirements.

Do not import from the sibling repository at runtime, hotlink its files, or make a production build depend on its working tree. Copy each selected asset into `adzbyte-app`, optimize it for its actual placement, and record its source path and intended use in an asset manifest. A future brand change should update the source and management implementations deliberately rather than silently coupling them.

## Visual System

### Core tokens

| Role | Source value | Management use |
|---|---:|---|
| Canvas | `#0a0f17` | Page and authentication background |
| Surface | `#111827` | Navigation, panels, cards, modals, and raised regions |
| Primary brand/action | `#8139ff` | Primary actions, active navigation, and strong focus emphasis |
| Cyan accent | `#00c6d8` | Links, informational emphasis, and selected data accents |
| Teal accent | `#2dd4a8` | Progress and positive brand emphasis when it cannot be confused with a status |
| Gold accent | `#d4a843` | Sparse premium, milestone, or SLA emphasis; not a warning substitute |
| Primary text | `#ffffff` | Headings and high-emphasis content |
| Muted text | `rgba(255, 255, 255, 0.70)` | Supporting labels and descriptions that still pass contrast requirements |

Use subtle translucent white borders and surface layers to create hierarchy on the dark canvas. Exact hover, pressed, disabled, border, and focus values should be derived during implementation and contrast-tested; do not introduce arbitrary framework palette values where a named theme token is appropriate.

Purple is the default primary-action color. Cyan, teal, and gold are supporting accents, not competing calls to action. The purple-to-cyan-to-teal gradient is reserved for the logo environment, authentication emphasis, selected headings, and restrained empty-state decoration. Do not apply gradients to tables, long-form text, status badges, form controls, or routine action buttons.

Operational state is separate from brand color. Success, warning, danger, information, disabled, and pending states must have explicit semantic tokens, labels, and icons with accessible contrast. Never communicate payment, fulfillment, approval, or SLA state through color alone, and never reinterpret gold as warning or teal as success without a semantic label.

### Typography

Use Poppins with the source weights `300`, `400`, `500`, `600`, and `700`, but prefer `400` for body copy, `500` for labels, `600` for actions and section headings, and `700` only for high-emphasis totals or page titles. Avoid weight `300` for dense management content.

The font should be self-hosted through this application's compiled assets with an appropriate fallback stack and `font-display: swap`. Tables, identifiers, money, dates, and status labels must remain easy to scan; branding must not reduce information density or introduce marketing-style oversized type into operational screens.

### Theme mode

Phase M0 is dark-first and should ship as a complete dark management theme matching the public identity. Do not expose a partially styled light mode. A user-selectable light or system theme may be added later only with a complete semantic token set, equivalent accessibility verification, and visual coverage for both panels.

## Logo and Media Plan

### Approved baseline assets

| Source asset | Intended use |
|---|---|
| `images/adzbyte-logo-transparent.png` | Full wordmark on authentication screens and expanded panel navigation |
| `images/adzbyte-logo-square-dark.webp` | Compact navigation, application icon, or small branded empty state |
| `images/adzbyte-logo-square-dark.png` | Lossless fallback and metadata use where WebP is unsuitable |
| `images/services/*-hero.jpg` | Customer-only product context when the image matches the purchased service |
| `images/landing/hero-mockup.jpg` | Candidate authentication or onboarding support image after checking crop, relevance, and responsive cost |

The wordmark and square mark are the required initial media usage. Additional imagery must answer a user need: orient the customer, identify a purchased product, explain an empty state, or support onboarding. Do not add decorative hero photography to dense admin resources, queues, forms, or tables.

Personal photos, certificates, testimonial screenshots, campaign artwork, and review images are excluded by default. They require a screen-specific reason plus privacy, permission, relevance, and crop review before copying. No management screen should imply an endorsement or customer result merely because an image exists in the public repository.

For every copied asset:

- record the original relative path, destination, purpose, and accessible text treatment;
- prefer WebP or AVIF for photographic content and retain transparency for logos;
- provide intrinsic dimensions and responsive sizes to prevent layout shift;
- keep above-the-fold authentication media lightweight and lazy-load noncritical imagery;
- use empty alternative text for purely decorative images and concise meaningful text for informative images;
- avoid embedding sensitive customer material in public build assets.

## Screen-Level Direction

### Authentication and activation

- Use the full wordmark, dark canvas, restrained gradient glow, and one clear primary action.
- Keep login, activation, reset, and verification forms visually consistent across both panel routes.
- State the destination clearly as **Customer account** or **Adzbyte administration** before credentials are entered.
- On small screens, prioritize the form and wordmark; optional supporting imagery must not push the primary action below the initial viewport.

### Customer panel

- Use guided cards, progress indicators, plain-language statuses, and clear next-action hierarchy.
- Allow contextual service imagery in onboarding, order headers, or empty states only when it maps to the customer's product.
- Favor a warmer, more spacious composition than the admin panel while retaining efficient forms and accessible tables.
- Make payment confirmation, brief completeness, draft readiness, corrections, and delivery visually distinct through semantic components rather than decorative color.

### Admin panel

- Keep the logo and brand accents restrained so queues, overdue work, payment exceptions, and customer context dominate.
- Favor compact tables, filters, bulk actions, clear timestamps, and persistent status labels.
- Reserve purple for primary operational actions and selected navigation; destructive actions retain a distinct danger treatment.
- Do not use marketing imagery in operational dashboards or resource pages.

### Shared components

Buttons, links, inputs, badges, alerts, empty states, file previews, timelines, tables, pagination, modals, and notifications should use one reusable management theme. Custom Blade, Livewire, and Filament components must consume the same tokens instead of reproducing raw color values.

## Accessibility, Responsiveness, and Motion

- Meet WCAG 2.2 AA contrast: at least `4.5:1` for normal text and `3:1` for large text and meaningful UI boundaries.
- Provide a visible keyboard focus indicator, logical focus order, skip access where layouts require it, and no keyboard traps.
- Maintain usable layouts at `320px` width and through common tablet and desktop sizes; tables need an intentional responsive strategy rather than uncontrolled clipping.
- Honor `prefers-reduced-motion`. Motion must communicate state or hierarchy, remain brief, and never block interaction.
- Preserve zoom and text resizing without hiding controls or truncating critical status information.
- Pair color with text and, where useful, an icon for every operational state.
- Test authentication, customer, and administrator shells with representative long names, labels, validation errors, and empty/loading/error states.

## Phase M0 Implementation Sequence

1. Create the Filament theme entry point and map the shared source colors into named management tokens.
2. Self-host Poppins and establish the typography, spacing, radius, border, shadow, focus, and semantic-status foundations.
3. Copy the required wordmark and square mark, create the asset manifest, and configure consistent panel identity and browser metadata.
4. Brand the shared authentication lifecycle: login, activation, password reset, email verification, and profile entry points.
5. Apply the shared shell to `/` and `/admin`, with explicit panel names and deliberately different information density.
6. Implement shared component states before adding screen-specific decoration or contextual customer imagery.
7. Capture desktop and mobile reference screenshots and run accessibility, responsive, production-build, and panel-boundary verification.

## Acceptance Gate

Phase M0 is complete only when:

- both panels use one reusable token and component foundation without duplicated raw brand colors;
- the full wordmark and compact mark are locally versioned, optimized, and documented in an asset manifest;
- all authentication lifecycle screens and both panel shells identify their destination consistently;
- customer and admin treatments feel related but preserve their different tasks and authorization boundaries;
- primary, secondary, destructive, disabled, loading, empty, error, warning, success, and informational states are visually and semantically distinct;
- representative desktop and `320px` mobile screenshots have been reviewed for both panels;
- keyboard, focus, reduced-motion, zoom, contrast, and alternative-text checks pass;
- the production asset build passes without network or runtime dependency on `adzbyte-next`;
- automated panel-access and authorization tests remain green.

Exact screen-by-screen customer imagery can be chosen as each product-management workflow is implemented. That selection does not block the shared theme, logos, authentication experience, or panel shell.
