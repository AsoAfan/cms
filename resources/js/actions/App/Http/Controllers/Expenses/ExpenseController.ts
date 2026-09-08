import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Expenses\ExpenseController::index
* @see app/Http/Controllers/Expenses/ExpenseController.php:23
* @route '/expenses'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/expenses',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Expenses\ExpenseController::index
* @see app/Http/Controllers/Expenses/ExpenseController.php:23
* @route '/expenses'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Expenses\ExpenseController::index
* @see app/Http/Controllers/Expenses/ExpenseController.php:23
* @route '/expenses'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Expenses\ExpenseController::index
* @see app/Http/Controllers/Expenses/ExpenseController.php:23
* @route '/expenses'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Expenses\ExpenseController::index
* @see app/Http/Controllers/Expenses/ExpenseController.php:23
* @route '/expenses'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Expenses\ExpenseController::index
* @see app/Http/Controllers/Expenses/ExpenseController.php:23
* @route '/expenses'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Expenses\ExpenseController::index
* @see app/Http/Controllers/Expenses/ExpenseController.php:23
* @route '/expenses'
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
* @see \App\Http\Controllers\Expenses\ExpenseController::store
* @see app/Http/Controllers/Expenses/ExpenseController.php:75
* @route '/expenses'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/expenses',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Expenses\ExpenseController::store
* @see app/Http/Controllers/Expenses/ExpenseController.php:75
* @route '/expenses'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Expenses\ExpenseController::store
* @see app/Http/Controllers/Expenses/ExpenseController.php:75
* @route '/expenses'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Expenses\ExpenseController::store
* @see app/Http/Controllers/Expenses/ExpenseController.php:75
* @route '/expenses'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Expenses\ExpenseController::store
* @see app/Http/Controllers/Expenses/ExpenseController.php:75
* @route '/expenses'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Expenses\ExpenseController::update
* @see app/Http/Controllers/Expenses/ExpenseController.php:84
* @route '/expenses/{expense}'
*/
export const update = (args: { expense: number | { id: number } } | [expense: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/expenses/{expense}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Expenses\ExpenseController::update
* @see app/Http/Controllers/Expenses/ExpenseController.php:84
* @route '/expenses/{expense}'
*/
update.url = (args: { expense: number | { id: number } } | [expense: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { expense: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { expense: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            expense: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        expense: typeof args.expense === 'object'
        ? args.expense.id
        : args.expense,
    }

    return update.definition.url
            .replace('{expense}', parsedArgs.expense.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Expenses\ExpenseController::update
* @see app/Http/Controllers/Expenses/ExpenseController.php:84
* @route '/expenses/{expense}'
*/
update.put = (args: { expense: number | { id: number } } | [expense: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Expenses\ExpenseController::update
* @see app/Http/Controllers/Expenses/ExpenseController.php:84
* @route '/expenses/{expense}'
*/
update.patch = (args: { expense: number | { id: number } } | [expense: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Expenses\ExpenseController::update
* @see app/Http/Controllers/Expenses/ExpenseController.php:84
* @route '/expenses/{expense}'
*/
const updateForm = (args: { expense: number | { id: number } } | [expense: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Expenses\ExpenseController::update
* @see app/Http/Controllers/Expenses/ExpenseController.php:84
* @route '/expenses/{expense}'
*/
updateForm.put = (args: { expense: number | { id: number } } | [expense: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Expenses\ExpenseController::update
* @see app/Http/Controllers/Expenses/ExpenseController.php:84
* @route '/expenses/{expense}'
*/
updateForm.patch = (args: { expense: number | { id: number } } | [expense: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Expenses\ExpenseController::destroy
* @see app/Http/Controllers/Expenses/ExpenseController.php:93
* @route '/expenses/{expense}'
*/
export const destroy = (args: { expense: number | { id: number } } | [expense: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/expenses/{expense}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Expenses\ExpenseController::destroy
* @see app/Http/Controllers/Expenses/ExpenseController.php:93
* @route '/expenses/{expense}'
*/
destroy.url = (args: { expense: number | { id: number } } | [expense: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { expense: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { expense: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            expense: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        expense: typeof args.expense === 'object'
        ? args.expense.id
        : args.expense,
    }

    return destroy.definition.url
            .replace('{expense}', parsedArgs.expense.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Expenses\ExpenseController::destroy
* @see app/Http/Controllers/Expenses/ExpenseController.php:93
* @route '/expenses/{expense}'
*/
destroy.delete = (args: { expense: number | { id: number } } | [expense: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Expenses\ExpenseController::destroy
* @see app/Http/Controllers/Expenses/ExpenseController.php:93
* @route '/expenses/{expense}'
*/
const destroyForm = (args: { expense: number | { id: number } } | [expense: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Expenses\ExpenseController::destroy
* @see app/Http/Controllers/Expenses/ExpenseController.php:93
* @route '/expenses/{expense}'
*/
destroyForm.delete = (args: { expense: number | { id: number } } | [expense: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const ExpenseController = { index, store, update, destroy }

export default ExpenseController