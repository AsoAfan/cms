import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::index
* @see app/Http/Controllers/Purchasing/PurchaseController.php:35
* @route '/purchases'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/purchases',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::index
* @see app/Http/Controllers/Purchasing/PurchaseController.php:35
* @route '/purchases'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::index
* @see app/Http/Controllers/Purchasing/PurchaseController.php:35
* @route '/purchases'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::index
* @see app/Http/Controllers/Purchasing/PurchaseController.php:35
* @route '/purchases'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::index
* @see app/Http/Controllers/Purchasing/PurchaseController.php:35
* @route '/purchases'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::index
* @see app/Http/Controllers/Purchasing/PurchaseController.php:35
* @route '/purchases'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::index
* @see app/Http/Controllers/Purchasing/PurchaseController.php:35
* @route '/purchases'
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
* @see \App\Http\Controllers\Purchasing\PurchaseController::store
* @see app/Http/Controllers/Purchasing/PurchaseController.php:66
* @route '/purchases'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/purchases',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::store
* @see app/Http/Controllers/Purchasing/PurchaseController.php:66
* @route '/purchases'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::store
* @see app/Http/Controllers/Purchasing/PurchaseController.php:66
* @route '/purchases'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::store
* @see app/Http/Controllers/Purchasing/PurchaseController.php:66
* @route '/purchases'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::store
* @see app/Http/Controllers/Purchasing/PurchaseController.php:66
* @route '/purchases'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::show
* @see app/Http/Controllers/Purchasing/PurchaseController.php:85
* @route '/purchases/{purchase}'
*/
export const show = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/purchases/{purchase}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::show
* @see app/Http/Controllers/Purchasing/PurchaseController.php:85
* @route '/purchases/{purchase}'
*/
show.url = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { purchase: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { purchase: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            purchase: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        purchase: typeof args.purchase === 'object'
        ? args.purchase.id
        : args.purchase,
    }

    return show.definition.url
            .replace('{purchase}', parsedArgs.purchase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::show
* @see app/Http/Controllers/Purchasing/PurchaseController.php:85
* @route '/purchases/{purchase}'
*/
show.get = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::show
* @see app/Http/Controllers/Purchasing/PurchaseController.php:85
* @route '/purchases/{purchase}'
*/
show.head = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::show
* @see app/Http/Controllers/Purchasing/PurchaseController.php:85
* @route '/purchases/{purchase}'
*/
const showForm = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::show
* @see app/Http/Controllers/Purchasing/PurchaseController.php:85
* @route '/purchases/{purchase}'
*/
showForm.get = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::show
* @see app/Http/Controllers/Purchasing/PurchaseController.php:85
* @route '/purchases/{purchase}'
*/
showForm.head = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Purchasing\PurchaseController::update
* @see app/Http/Controllers/Purchasing/PurchaseController.php:95
* @route '/purchases/{purchase}'
*/
export const update = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/purchases/{purchase}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::update
* @see app/Http/Controllers/Purchasing/PurchaseController.php:95
* @route '/purchases/{purchase}'
*/
update.url = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { purchase: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { purchase: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            purchase: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        purchase: typeof args.purchase === 'object'
        ? args.purchase.id
        : args.purchase,
    }

    return update.definition.url
            .replace('{purchase}', parsedArgs.purchase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::update
* @see app/Http/Controllers/Purchasing/PurchaseController.php:95
* @route '/purchases/{purchase}'
*/
update.put = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::update
* @see app/Http/Controllers/Purchasing/PurchaseController.php:95
* @route '/purchases/{purchase}'
*/
update.patch = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::update
* @see app/Http/Controllers/Purchasing/PurchaseController.php:95
* @route '/purchases/{purchase}'
*/
const updateForm = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::update
* @see app/Http/Controllers/Purchasing/PurchaseController.php:95
* @route '/purchases/{purchase}'
*/
updateForm.put = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::update
* @see app/Http/Controllers/Purchasing/PurchaseController.php:95
* @route '/purchases/{purchase}'
*/
updateForm.patch = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Purchasing\PurchaseController::destroy
* @see app/Http/Controllers/Purchasing/PurchaseController.php:158
* @route '/purchases/{purchase}'
*/
export const destroy = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/purchases/{purchase}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::destroy
* @see app/Http/Controllers/Purchasing/PurchaseController.php:158
* @route '/purchases/{purchase}'
*/
destroy.url = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { purchase: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { purchase: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            purchase: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        purchase: typeof args.purchase === 'object'
        ? args.purchase.id
        : args.purchase,
    }

    return destroy.definition.url
            .replace('{purchase}', parsedArgs.purchase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::destroy
* @see app/Http/Controllers/Purchasing/PurchaseController.php:158
* @route '/purchases/{purchase}'
*/
destroy.delete = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::destroy
* @see app/Http/Controllers/Purchasing/PurchaseController.php:158
* @route '/purchases/{purchase}'
*/
const destroyForm = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::destroy
* @see app/Http/Controllers/Purchasing/PurchaseController.php:158
* @route '/purchases/{purchase}'
*/
destroyForm.delete = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Purchasing\PurchaseController::status
* @see app/Http/Controllers/Purchasing/PurchaseController.php:136
* @route '/purchases/{purchase}/status'
*/
export const status = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: status.url(args, options),
    method: 'post',
})

status.definition = {
    methods: ["post"],
    url: '/purchases/{purchase}/status',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::status
* @see app/Http/Controllers/Purchasing/PurchaseController.php:136
* @route '/purchases/{purchase}/status'
*/
status.url = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { purchase: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { purchase: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            purchase: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        purchase: typeof args.purchase === 'object'
        ? args.purchase.id
        : args.purchase,
    }

    return status.definition.url
            .replace('{purchase}', parsedArgs.purchase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::status
* @see app/Http/Controllers/Purchasing/PurchaseController.php:136
* @route '/purchases/{purchase}/status'
*/
status.post = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: status.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::status
* @see app/Http/Controllers/Purchasing/PurchaseController.php:136
* @route '/purchases/{purchase}/status'
*/
const statusForm = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: status.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::status
* @see app/Http/Controllers/Purchasing/PurchaseController.php:136
* @route '/purchases/{purchase}/status'
*/
statusForm.post = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: status.url(args, options),
    method: 'post',
})

status.form = statusForm

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::rename
* @see app/Http/Controllers/Purchasing/PurchaseController.php:123
* @route '/purchases/{purchase}/number'
*/
export const rename = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: rename.url(args, options),
    method: 'patch',
})

rename.definition = {
    methods: ["patch"],
    url: '/purchases/{purchase}/number',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::rename
* @see app/Http/Controllers/Purchasing/PurchaseController.php:123
* @route '/purchases/{purchase}/number'
*/
rename.url = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { purchase: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { purchase: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            purchase: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        purchase: typeof args.purchase === 'object'
        ? args.purchase.id
        : args.purchase,
    }

    return rename.definition.url
            .replace('{purchase}', parsedArgs.purchase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::rename
* @see app/Http/Controllers/Purchasing/PurchaseController.php:123
* @route '/purchases/{purchase}/number'
*/
rename.patch = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: rename.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::rename
* @see app/Http/Controllers/Purchasing/PurchaseController.php:123
* @route '/purchases/{purchase}/number'
*/
const renameForm = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: rename.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Purchasing\PurchaseController::rename
* @see app/Http/Controllers/Purchasing/PurchaseController.php:123
* @route '/purchases/{purchase}/number'
*/
renameForm.patch = (args: { purchase: number | { id: number } } | [purchase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: rename.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

rename.form = renameForm

const PurchaseController = { index, store, show, update, destroy, status, rename }

export default PurchaseController