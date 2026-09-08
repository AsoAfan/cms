import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Suppliers\SupplierController::index
* @see app/Http/Controllers/Suppliers/SupplierController.php:16
* @route '/suppliers'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/suppliers',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::index
* @see app/Http/Controllers/Suppliers/SupplierController.php:16
* @route '/suppliers'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::index
* @see app/Http/Controllers/Suppliers/SupplierController.php:16
* @route '/suppliers'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::index
* @see app/Http/Controllers/Suppliers/SupplierController.php:16
* @route '/suppliers'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::index
* @see app/Http/Controllers/Suppliers/SupplierController.php:16
* @route '/suppliers'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::index
* @see app/Http/Controllers/Suppliers/SupplierController.php:16
* @route '/suppliers'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::index
* @see app/Http/Controllers/Suppliers/SupplierController.php:16
* @route '/suppliers'
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
* @see \App\Http\Controllers\Suppliers\SupplierController::create
* @see app/Http/Controllers/Suppliers/SupplierController.php:28
* @route '/suppliers/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/suppliers/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::create
* @see app/Http/Controllers/Suppliers/SupplierController.php:28
* @route '/suppliers/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::create
* @see app/Http/Controllers/Suppliers/SupplierController.php:28
* @route '/suppliers/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::create
* @see app/Http/Controllers/Suppliers/SupplierController.php:28
* @route '/suppliers/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::create
* @see app/Http/Controllers/Suppliers/SupplierController.php:28
* @route '/suppliers/create'
*/
const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::create
* @see app/Http/Controllers/Suppliers/SupplierController.php:28
* @route '/suppliers/create'
*/
createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::create
* @see app/Http/Controllers/Suppliers/SupplierController.php:28
* @route '/suppliers/create'
*/
createForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

create.form = createForm

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::store
* @see app/Http/Controllers/Suppliers/SupplierController.php:33
* @route '/suppliers'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/suppliers',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::store
* @see app/Http/Controllers/Suppliers/SupplierController.php:33
* @route '/suppliers'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::store
* @see app/Http/Controllers/Suppliers/SupplierController.php:33
* @route '/suppliers'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::store
* @see app/Http/Controllers/Suppliers/SupplierController.php:33
* @route '/suppliers'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::store
* @see app/Http/Controllers/Suppliers/SupplierController.php:33
* @route '/suppliers'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::edit
* @see app/Http/Controllers/Suppliers/SupplierController.php:42
* @route '/suppliers/{supplier}/edit'
*/
export const edit = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/suppliers/{supplier}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::edit
* @see app/Http/Controllers/Suppliers/SupplierController.php:42
* @route '/suppliers/{supplier}/edit'
*/
edit.url = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { supplier: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { supplier: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            supplier: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        supplier: typeof args.supplier === 'object'
        ? args.supplier.id
        : args.supplier,
    }

    return edit.definition.url
            .replace('{supplier}', parsedArgs.supplier.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::edit
* @see app/Http/Controllers/Suppliers/SupplierController.php:42
* @route '/suppliers/{supplier}/edit'
*/
edit.get = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::edit
* @see app/Http/Controllers/Suppliers/SupplierController.php:42
* @route '/suppliers/{supplier}/edit'
*/
edit.head = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::edit
* @see app/Http/Controllers/Suppliers/SupplierController.php:42
* @route '/suppliers/{supplier}/edit'
*/
const editForm = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::edit
* @see app/Http/Controllers/Suppliers/SupplierController.php:42
* @route '/suppliers/{supplier}/edit'
*/
editForm.get = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::edit
* @see app/Http/Controllers/Suppliers/SupplierController.php:42
* @route '/suppliers/{supplier}/edit'
*/
editForm.head = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

edit.form = editForm

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::update
* @see app/Http/Controllers/Suppliers/SupplierController.php:51
* @route '/suppliers/{supplier}'
*/
export const update = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/suppliers/{supplier}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::update
* @see app/Http/Controllers/Suppliers/SupplierController.php:51
* @route '/suppliers/{supplier}'
*/
update.url = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { supplier: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { supplier: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            supplier: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        supplier: typeof args.supplier === 'object'
        ? args.supplier.id
        : args.supplier,
    }

    return update.definition.url
            .replace('{supplier}', parsedArgs.supplier.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::update
* @see app/Http/Controllers/Suppliers/SupplierController.php:51
* @route '/suppliers/{supplier}'
*/
update.put = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::update
* @see app/Http/Controllers/Suppliers/SupplierController.php:51
* @route '/suppliers/{supplier}'
*/
update.patch = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::update
* @see app/Http/Controllers/Suppliers/SupplierController.php:51
* @route '/suppliers/{supplier}'
*/
const updateForm = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::update
* @see app/Http/Controllers/Suppliers/SupplierController.php:51
* @route '/suppliers/{supplier}'
*/
updateForm.put = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::update
* @see app/Http/Controllers/Suppliers/SupplierController.php:51
* @route '/suppliers/{supplier}'
*/
updateForm.patch = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Suppliers\SupplierController::destroy
* @see app/Http/Controllers/Suppliers/SupplierController.php:60
* @route '/suppliers/{supplier}'
*/
export const destroy = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/suppliers/{supplier}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::destroy
* @see app/Http/Controllers/Suppliers/SupplierController.php:60
* @route '/suppliers/{supplier}'
*/
destroy.url = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { supplier: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { supplier: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            supplier: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        supplier: typeof args.supplier === 'object'
        ? args.supplier.id
        : args.supplier,
    }

    return destroy.definition.url
            .replace('{supplier}', parsedArgs.supplier.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::destroy
* @see app/Http/Controllers/Suppliers/SupplierController.php:60
* @route '/suppliers/{supplier}'
*/
destroy.delete = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::destroy
* @see app/Http/Controllers/Suppliers/SupplierController.php:60
* @route '/suppliers/{supplier}'
*/
const destroyForm = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Suppliers\SupplierController::destroy
* @see app/Http/Controllers/Suppliers/SupplierController.php:60
* @route '/suppliers/{supplier}'
*/
destroyForm.delete = (args: { supplier: number | { id: number } } | [supplier: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const suppliers = {
    index: Object.assign(index, index),
    create: Object.assign(create, create),
    store: Object.assign(store, store),
    edit: Object.assign(edit, edit),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
}

export default suppliers