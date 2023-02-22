import axios from 'axios'
import { usePage } from '@inertiajs/vue3'

/**
 * Reuse current props and component for the modal backdrop
 */
export default function () {
  axios.interceptors.response.use(function(response) {

    if (response.headers['x-inertia-modal']) {
      let { component, props } = usePage();
      props = JSON.parse(JSON.stringify(props));
      response.data.props = { ...props, ...response.data.props };
      response.data.component = component;
      response.headers['x-inertia'] = true
    }

    return response
  })
}
