import i18n from '@/i18n'

/**
 * Client-side check for image uploads, so a wrong file is rejected at once
 * with a clear message. The server re-checks the real file content.
 */

export const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp']
export const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp']
/** For <input type="file" accept>: limits the device's file picker. */
export const IMAGE_ACCEPT = [...IMAGE_TYPES, ...IMAGE_EXTENSIONS.map((e) => `.${e}`)].join(',')

/**
 * Null when the file is a JPG/PNG/WebP image within `maxMb`, else a
 * translated message saying what's wrong.
 */
export function imageFileError(file, maxMb = 4) {
  if (!file) return null
  const extension = file.name?.split('.').pop()?.toLowerCase() ?? ''
  if (!IMAGE_TYPES.includes(file.type) || !IMAGE_EXTENSIONS.includes(extension)) {
    return i18n.global.t('ui.imageWrongType')
  }
  if (file.size > maxMb * 1024 * 1024) {
    return i18n.global.t('ui.imageTooLarge', { max: maxMb })
  }
  return null
}
