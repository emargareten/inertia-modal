import { http } from '@inertiajs/vue3'

const mergePageData = (currentValue, responseValue) => {
  if (Array.isArray(currentValue) || Array.isArray(responseValue)) {
    return [...new Set([...(currentValue || []), ...(responseValue || [])])]
  }

  return {
    ...JSON.parse(JSON.stringify(currentValue || {})),
    ...(responseValue || {}),
  }
}

/**
 * Reuse current props and component for the modal backdrop
 */
export default function (app) {
  http.onResponse((response) => {

    if (response.headers['x-inertia-modal']) {
      const currentPage = app.config.globalProperties.$page

      response.data = typeof response.data === 'string' ? JSON.parse(response.data) : response.data

      response.data.component = currentPage.component
      response.data.props = {
        ...JSON.parse(JSON.stringify(currentPage.props)),
        ...response.data.props
      }

      const preserveKeys = ['scrollProps', 'mergeProps', 'prependProps', 'deepMergeProps', 'matchPropsOn', 'deferredProps', 'sharedProps', 'onceProps']

      for (const key of preserveKeys) {
        if (currentPage[key]) {
          response.data[key] = mergePageData(currentPage[key], response.data[key])
        }
      }

      response.headers['x-inertia'] = 'true'
    }

    return response
  })
}
