import { http } from '@inertiajs/vue3'
import { applyBackdrop } from './applyBackdrop'

const hasHeader = (headers, name) => Object.keys(headers || {})
  .some((header) => header.toLowerCase() === name.toLowerCase())

/**
 * Reuse current props and component for the modal backdrop
 */
export default function (app) {
  http.onResponse((response) => {
    if (!hasHeader(response.headers, 'x-inertia-modal')) {
      return response
    }

    const currentPage = app.config.globalProperties.$page

    if (!currentPage) {
      return response
    }

    response.data = typeof response.data === 'string' ? JSON.parse(response.data) : response.data
    response.data = applyBackdrop(currentPage, response.data)

    response.headers['x-inertia'] = 'true'

    return response
  })
}
