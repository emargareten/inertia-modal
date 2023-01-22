import axios from 'axios'
import { usePage } from '@inertiajs/vue3'
import {computed} from "vue";

const props = computed(() => usePage().props)
const component = computed(() => usePage().component)

/**
 * Reuse current (stale) props and component for the modal backdrop
 */
export default function () {
  axios.interceptors.response.use(function(response) {
    if(response.headers['x-inertia'] && response.data.props?.modal) {
      let oldProps = JSON.parse(JSON.stringify(props.value))
      response.data.props = { ...oldProps, ...response.data.props }
      response.data.component = component.value
    }
    return response
  })
}
