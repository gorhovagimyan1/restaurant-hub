/**
 * A "rating ticket": enough to rate this visit's dishes after the dining session
 * has ended (bill settled, or the table timed out). The session itself is wiped
 * from sessionStorage when it ends, so the ticket lives in localStorage, where
 * it also survives the guest closing the tab after paying.
 *
 * The server decides whether it is still valid (a few hours after the visit
 * closes); this only remembers it.
 */
const KEY = 'rh_rating_ticket'

// Prune stale tickets client-side so they don't sit in storage forever.
const MAX_AGE_MS = 24 * 60 * 60 * 1000

export function saveRatingTicket({ token, sessionToken, slug, tableName }) {
  try {
    localStorage.setItem(
      KEY,
      JSON.stringify({ token, sessionToken, slug, tableName, savedAt: Date.now() }),
    )
  } catch {
    // storage unavailable — the guest just won't get the after-visit prompt
  }
}

export function loadRatingTicket() {
  try {
    const ticket = JSON.parse(localStorage.getItem(KEY) || 'null')
    if (!ticket?.token || !ticket?.sessionToken) return null
    if (Date.now() - ticket.savedAt > MAX_AGE_MS) {
      clearRatingTicket()
      return null
    }
    return ticket
  } catch {
    return null
  }
}

export function clearRatingTicket() {
  try {
    localStorage.removeItem(KEY)
  } catch {
    // ignore
  }
}
