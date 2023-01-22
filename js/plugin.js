import resolver from './resolver'
import preserveBackdrop from './preserveBackdrop'

export const plugin = {
  install(app, options) {
    resolver.setResolveCallback(options.resolve)

    preserveBackdrop()
  },
}
