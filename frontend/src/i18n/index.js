import { createI18n } from 'vue-i18n'

/**
 * App-wide translations: English (default/fallback), Russian, Armenian.
 *
 * Messages live in `./messages/<namespace>.js`, each exporting
 * `{ en: {...}, ru: {...}, hy: {...} }`. Every file is merged under its
 * namespace, so `messages/orders.js` is used as `t('orders.title')`.
 */
export const LOCALES = [
  { code: 'en', short: 'EN', label: 'English', intl: 'en-US' },
  { code: 'ru', short: 'RU', label: 'Русский', intl: 'ru-RU' },
  { code: 'hy', short: 'HY', label: 'Հայերեն', intl: 'hy-AM' },
]

const STORAGE_KEY = 'rh_locale'
const CODES = LOCALES.map((l) => l.code)

const modules = import.meta.glob('./messages/*.js', { eager: true })
const messages = { en: {}, ru: {}, hy: {} }
for (const [path, mod] of Object.entries(modules)) {
  const ns = path.match(/\/([^/]+)\.js$/)[1]
  for (const code of CODES) messages[code][ns] = mod.default?.[code] ?? {}
}

function detectLocale() {
  try {
    const saved = localStorage.getItem(STORAGE_KEY)
    if (CODES.includes(saved)) return saved
  } catch {
    /* storage unavailable */
  }
  const nav = (navigator.language || 'en').slice(0, 2).toLowerCase()
  return CODES.includes(nav) ? nav : 'en'
}

// Plural forms are written `one | few | many` for Russian, `one | other` for
// English and Armenian (Armenian uses the singular for 0 and 1).
const pluralRules = {
  ru: (n, choicesLength) => {
    if (choicesLength < 3) return n === 1 ? 0 : 1
    const mod10 = n % 10
    const mod100 = n % 100
    if (mod10 === 1 && mod100 !== 11) return 0
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return 1
    return 2
  },
  hy: (n, choicesLength) => (choicesLength < 2 ? 0 : n === 0 || n === 1 ? 0 : 1),
}

const i18n = createI18n({
  legacy: false,
  locale: detectLocale(),
  fallbackLocale: 'en',
  pluralRules,
  messages,
})

document.documentElement.lang = i18n.global.locale.value

export function setLocale(code, { persist = true } = {}) {
  if (!CODES.includes(code)) return
  i18n.global.locale.value = code
  document.documentElement.lang = code
  if (!persist) return
  try {
    localStorage.setItem(STORAGE_KEY, code)
  } catch {
    /* storage unavailable */
  }
}

/**
 * Switch to a suggested language (e.g. the restaurant's default) unless the
 * visitor has already picked one themselves.
 */
export function suggestLocale(code) {
  try {
    if (CODES.includes(localStorage.getItem(STORAGE_KEY))) return
  } catch {
    /* storage unavailable */
  }
  setLocale(code, { persist: false })
}

/** BCP-47 tag for Intl formatting in the current locale. */
export function intlLocale() {
  return LOCALES.find((l) => l.code === i18n.global.locale.value)?.intl ?? 'en-US'
}

export default i18n
