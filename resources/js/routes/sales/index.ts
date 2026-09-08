import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Sales\SaleController::index
* @see app/Http/Controllers/Sales/SaleController.php:42
* @route '/sales'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/sales',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Sales\SaleController::index
* @see app/Http/Controllers/Sales/SaleController.php:42
* @route '/sales'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Sales\SaleController::index
* @see app/Http/Controllers/Sales/SaleController.php:42
* @route '/sales'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Sales\SaleController::index
* @see app/Http/Controllers/Sales/SaleController.php:42
* @route '/sales'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Sales\SaleController::index
* @see app/Http/Controllers/Sales/SaleController.php:42
* @route '/sales'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Sales\SaleController::index
* @see app/Http/Controllers/Sales/SaleController.php:42
* @route '/sales'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Sales\SaleController::index
* @see app/Http/Controllers/Sales/SaleController.php:42
* @route '/sales'
*/
indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

index.form = indexForm

/**
* @see \App\Http\Controllers\Sales\SaleController::store
* @see app/Http/Controllers/Sales/SaleController.php:89
* @route '/sales'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/sales',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Sales\SaleController::store
* @see app/Http/Controllers/Sales/SaleController.php:89
* @route '/sales'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Sales\SaleController::store
* @see app/Http/Controllers/Sales/SaleController.php:89
* @route '/sales'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Sales\SaleController::store
* @see app/Http/Controllers/Sales/SaleController.php:89
* @route '/sales'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Sales\SaleController::store
* @see app/Http/Controllers/Sales/SaleController.php:89
* @route '/sales'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Sales\SaleController::show
* @see app/Http/Controllers/Sales/SaleController.php:104
* @route '/sales/{sale}'
*/
export const show = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/sales/{sale}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Sales\SaleController::show
* @see app/Http/Controllers/Sales/SaleController.php:104
* @route '/sales/{sale}'
*/
show.url = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { sale: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { sale: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            sale: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        sale: typeof args.sale === 'object'
        ? args.sale.id
        : args.sale,
    }

    return show.definition.url
            .replace('{sale}', parsedArgs.sale.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Sales\SaleController::show
* @see app/Http/Controllers/Sales/SaleController.php:104
* @route '/sales/{sale}'
*/
show.get = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Sales\SaleController::show
* @see app/Http/Controllers/Sales/SaleController.php:104
* @route '/sales/{sale}'
*/
show.head = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Sales\SaleController::show
* @see app/Http/Controllers/Sales/SaleController.php:104
* @route '/sales/{sale}'
*/
const showForm = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Sales\SaleController::show
* @see app/Http/Controllers/Sales/SaleController.php:104
* @route '/sales/{sale}'
*/
showForm.get = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Sales\SaleController::show
* @see app/Http/Controllers/Sales/SaleController.php:104
* @route '/sales/{sale}'
*/
showForm.head = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

show.form = showForm

/**
* @see \App\Http\Controllers\Sales\SaleController::update
* @see app/Http/Controllers/Sales/SaleController.php:114
* @route '/sales/{sale}'
*/
export const update = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/sales/{sale}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Sales\SaleController::update
* @see app/Http/Controllers/Sales/SaleController.php:114
* @route '/sales/{sale}'
*/
update.url = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { sale: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { sale: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            sale: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        sale: typeof args.sale === 'object'
        ? args.sale.id
        : args.sale,
    }

    return update.definition.url
            .replace('{sale}', parsedArgs.sale.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Sales\SaleController::update
* @see app/Http/Controllers/Sales/SaleController.php:114
* @route '/sales/{sale}'
*/
update.put = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Sales\SaleController::update
* @see app/Http/Controllers/Sales/SaleController.php:114
* @route '/sales/{sale}'
*/
update.patch = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Sales\SaleController::update
* @see app/Http/Controllers/Sales/SaleController.php:114
* @route '/sales/{sale}'
*/
const updateForm = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Sales\SaleController::update
* @see app/Http/Controllers/Sales/SaleController.php:114
* @route '/sales/{sale}'
*/
updateForm.put = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Sales\SaleController::update
* @see app/Http/Controllers/Sales/SaleController.php:114
* @route '/sales/{sale}'
*/
updateForm.patch = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

update.form = updateForm

/**
* @see \App\Http\Controllers\Sales\SaleController::destroy
* @see app/Http/Controllers/Sales/SaleController.php:172
* @route '/sales/{sale}'
*/
export const destroy = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/sales/{sale}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Sales\SaleController::destroy
* @see app/Http/Controllers/Sales/SaleController.php:172
* @route '/sales/{sale}'
*/
destroy.url = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { sale: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { sale: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            sale: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        sale: typeof args.sale === 'object'
        ? args.sale.id
        : args.sale,
    }

    return destroy.definition.url
            .replace('{sale}', parsedArgs.sale.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Sales\SaleController::destroy
* @see app/Http/Controllers/Sales/SaleController.php:172
* @route '/sales/{sale}'
*/
destroy.delete = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Sales\SaleController::destroy
* @see app/Http/Controllers/Sales/SaleController.php:172
* @route '/sales/{sale}'
*/
const destroyForm = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Sales\SaleController::destroy
* @see app/Http/Controllers/Sales/SaleController.php:172
* @route '/sales/{sale}'
*/
destroyForm.delete = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

/**
* @see \App\Http\Controllers\Sales\SaleController::status
* @see app/Http/Controllers/Sales/SaleController.php:150
* @route '/sales/{sale}/status'
*/
export const status = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: status.url(args, options),
    method: 'post',
})

status.definition = {
    methods: ["post"],
    url: '/sales/{sale}/status',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Sales\SaleController::status
* @see app/Http/Controllers/Sales/SaleController.php:150
* @route '/sales/{sale}/status'
*/
status.url = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { sale: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { sale: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            sale: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        sale: typeof args.sale === 'object'
        ? args.sale.id
        : args.sale,
    }

    return status.definition.url
            .replace('{sale}', parsedArgs.sale.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Sales\SaleController::status
* @see app/Http/Controllers/Sales/SaleController.php:150
* @route '/sales/{sale}/status'
*/
status.post = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: status.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Sales\SaleController::status
* @see app/Http/Controllers/Sales/SaleController.php:150
* @route '/sales/{sale}/status'
*/
const statusForm = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: status.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Sales\SaleController::status
* @see app/Http/Controllers/Sales/SaleController.php:150
* @route '/sales/{sale}/status'
*/
statusForm.post = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: status.url(args, options),
    method: 'post',
})

status.form = statusForm

/**
* @see \App\Http\Controllers\Sales\SaleController::rename
* @see app/Http/Controllers/Sales/SaleController.php:137
* @route '/sales/{sale}/number'
*/
export const rename = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: rename.url(args, options),
    method: 'patch',
})

rename.definition = {
    methods: ["patch"],
    url: '/sales/{sale}/number',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Sales\SaleController::rename
* @see app/Http/Controllers/Sales/SaleController.php:137
* @route '/sales/{sale}/number'
*/
rename.url = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { sale: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { sale: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            sale: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        sale: typeof args.sale === 'object'
        ? args.sale.id
        : args.sale,
    }

    return rename.definition.url
            .replace('{sale}', parsedArgs.sale.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Sales\SaleController::rename
* @see app/Http/Controllers/Sales/SaleController.php:137
* @route '/sales/{sale}/number'
*/
rename.patch = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: rename.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Sales\SaleController::rename
* @see app/Http/Controllers/Sales/SaleController.php:137
* @route '/sales/{sale}/number'
*/
const renameForm = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: rename.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Sales\SaleController::rename
* @see app/Http/Controllers/Sales/SaleController.php:137
* @route '/sales/{sale}/number'
*/
renameForm.patch = (args: { sale: number | { id: number } } | [sale: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: rename.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

rename.form = renameForm

const sales = {
    index: Object.assign(index, index),
    store: Object.assign(store, store),
    show: Object.assign(show, show),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
    status: Object.assign(status, status),
    rename: Object.assign(rename, rename),
}

export default sales