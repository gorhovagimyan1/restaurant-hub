import { LOCALES } from '@/i18n'

/**
 * Helpers for owner-written text in several languages (categories, dishes,
 * the restaurant description).
 *
 * The API stores the original wording in plain fields (name, description, …)
 * and per-language copies in `translations: { ru: { name, … }, … }`. In forms
 * we edit one copy per language; the restaurant's default language is the
 * "main" one and also fills the plain fields, which guests see whenever a
 * language is left blank.
 */

const CODES = LOCALES.map((l) => l.code)

/** Languages with the main one first. */
export function orderedLocales(main) {
  return [...LOCALES].sort((a, b) => (a.code === main ? -1 : b.code === main ? 1 : 0))
}

/**
 * One editable copy per language: `{ en: { name, … }, ru: {…}, hy: {…} }`.
 * `blank` gives each field's empty value; `toEdit` turns a stored value into
 * its editable form (e.g. an ingredients array into "a, b").
 */
export function initText(source, blank, main, toEdit = {}) {
  const fields = Object.keys(blank)
  const edit = (field, value) => (toEdit[field] ? toEdit[field](value) : value)
  // Dashboard resources show name/description in the viewer's language and
  // carry the stored wording in `original`.
  const original = source?.original ?? source
  const text = {}
  for (const code of CODES) {
    const stored = source?.translations?.[code] ?? {}
    text[code] = {}
    for (const field of fields) {
      // The main language falls back to the original wording.
      const value = stored[field] ?? (code === main ? original?.[field] : undefined)
      const v = value == null ? blank[field] : edit(field, value)
      // Arrays (e.g. ingredients) are copied so languages never share one list.
      text[code][field] = Array.isArray(v) ? [...v] : v
    }
  }
  return text
}

/**
 * Back to the API shape: the main language fills the plain fields, and every
 * language — the main one included, so switching the default language later
 * loses nothing — goes into `translations`.
 */
export function splitText(text, main, fromEdit = {}) {
  const save = (field, value) => (fromEdit[field] ? fromEdit[field](value) : value)
  const base = {}
  const translations = {}
  for (const code of CODES) {
    translations[code] = {}
    for (const [field, value] of Object.entries(text[code])) {
      translations[code][field] = save(field, value)
      if (code === main) base[field] = save(field, value)
    }
  }
  return { ...base, translations }
}

/** Whether a language has any text filled in (for the tab indicator). */
export function hasText(fields) {
  return Object.values(fields ?? {}).some((v) => String(v ?? '').trim() !== '')
}
