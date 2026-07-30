/**
 * Turns a restaurant's stored menu theme into the CSS custom properties the
 * customer portal renders with, and loads whatever web fonts it asks for.
 *
 * The server sends only base choices (palette, fonts, radius, layout); the rest
 * of the palette — borders, muted text, hover tints — is derived in CSS from
 * these variables. See assets/main.css and App\Support\MenuTheme.
 */

/** Font families a restaurant may pick, mirroring MenuTheme::FONTS. */
export const FONTS = {
  inter: {
    label: 'Inter',
    stack: "'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif",
    // Loaded in index.html for the app shell, so it never needs injecting.
    google: null,
    note: 'Clean and neutral',
  },
  poppins: {
    label: 'Poppins',
    stack: "'Poppins', ui-sans-serif, system-ui, sans-serif",
    google: 'Poppins:wght@400;500;600;700',
    note: 'Friendly and geometric',
  },
  'dm-sans': {
    label: 'DM Sans',
    stack: "'DM Sans', ui-sans-serif, system-ui, sans-serif",
    google: 'DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,700',
    note: 'Soft and modern',
  },
  'space-grotesk': {
    label: 'Space Grotesk',
    stack: "'Space Grotesk', ui-sans-serif, system-ui, sans-serif",
    google: 'Space+Grotesk:wght@400;500;600;700',
    note: 'Sharp and contemporary',
  },
  playfair: {
    label: 'Playfair Display',
    stack: "'Playfair Display', Georgia, 'Times New Roman', serif",
    google: 'Playfair+Display:wght@400;500;600;700',
    note: 'Classic fine dining',
  },
  lora: {
    label: 'Lora',
    stack: "'Lora', Georgia, 'Times New Roman', serif",
    google: 'Lora:wght@400;500;600;700',
    note: 'Warm and readable',
  },
}

export const LAYOUTS = [
  { value: 'list', label: 'List', hint: 'One dish per row — easiest to scan.' },
  { value: 'grid', label: 'Grid', hint: 'Two-column cards — shows food off.' },
]

export const HERO_STYLES = [
  { value: 'cover', label: 'Cover photo', hint: 'Large image with your name over it.' },
  { value: 'gradient', label: 'Tinted', hint: 'Photo washed in your brand colour.' },
  { value: 'compact', label: 'Compact', hint: 'No photo — just name and details.' },
]

/** Matches MenuTheme::defaults() so the portal renders before the API answers. */
export const DEFAULT_THEME = Object.freeze({
  preset: 'classic',
  primary_color: '#10b981',
  surface_color: '#f6f8f7',
  card_color: '#ffffff',
  heading_font: 'inter',
  body_font: 'inter',
  radius: 16,
  layout: 'list',
  show_images: true,
  hero_style: 'cover',
})

/** The design fields a preset owns — everything except its name and label. */
export const THEME_FIELDS = Object.keys(DEFAULT_THEME).filter((key) => key !== 'preset')

function hexToRgb(hex) {
  const value = String(hex || '').replace('#', '')
  if (value.length !== 6) return null
  return {
    r: parseInt(value.slice(0, 2), 16),
    g: parseInt(value.slice(2, 4), 16),
    b: parseInt(value.slice(4, 6), 16),
  }
}

/** WCAG relative luminance, 0 (black) to 1 (white). */
function luminance(hex) {
  const rgb = hexToRgb(hex)
  if (!rgb) return 1
  const [r, g, b] = [rgb.r, rgb.g, rgb.b].map((channel) => {
    const c = channel / 255
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4
  })
  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

/**
 * Near-black or near-white, whichever reads better on the given background.
 * This is what stops an adventurous colour choice from producing a menu nobody
 * can read.
 */
export function readableOn(hex) {
  return luminance(hex) > 0.42 ? '#12171c' : '#f7f9fa'
}

/** True when the theme's page background is a dark one. */
export function isDarkTheme(theme) {
  return luminance(theme?.surface_color || DEFAULT_THEME.surface_color) <= 0.42
}

function fontStack(key) {
  return (FONTS[key] || FONTS.inter).stack
}

/**
 * The inline custom properties for a theme. Apply alongside the `menu-theme`
 * class, which derives the rest of the palette from these.
 */
export function themeVars(theme) {
  const t = { ...DEFAULT_THEME, ...(theme || {}) }
  const radius = Math.max(0, Math.min(32, Number(t.radius) || 0))

  return {
    '--m-primary': t.primary_color,
    '--m-primary-contrast': readableOn(t.primary_color),
    '--m-surface': t.surface_color,
    '--m-card': t.card_color,
    '--m-text': readableOn(t.surface_color),
    '--m-text-card': readableOn(t.card_color),
    '--m-radius': `${radius}px`,
    // Rounded themes get pill buttons; sharp ones keep their own corners so a
    // "Minimal" menu doesn't end up with stadium-shaped CTAs.
    '--m-radius-btn': radius >= 12 ? '999px' : `${radius}px`,
    '--m-font-heading': fontStack(t.heading_font),
    '--m-font-body': fontStack(t.body_font),
  }
}

const injected = new Set()

/**
 * Load the web fonts a theme needs, once per family per page load.
 */
export function ensureThemeFonts(theme) {
  if (typeof document === 'undefined') return

  for (const key of [theme?.heading_font, theme?.body_font]) {
    const font = FONTS[key]
    if (!font?.google || injected.has(key)) continue

    injected.add(key)
    const link = document.createElement('link')
    link.rel = 'stylesheet'
    link.href = `https://fonts.googleapis.com/css2?family=${font.google}&display=swap`
    document.head.appendChild(link)
  }
}

/**
 * Preload every selectable family. The design editor swaps fonts as fast as the
 * owner can click, and waiting for a stylesheet on each click looks broken.
 */
export function preloadAllThemeFonts() {
  for (const key of Object.keys(FONTS)) {
    ensureThemeFonts({ heading_font: key, body_font: key })
  }
}
