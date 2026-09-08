import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Expenses\ExpenseCategoryController::store
* @see app/Http/Controllers/Expenses/ExpenseCategoryController.php:13
* @route '/expense-categories'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/expense-categories',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Expenses\ExpenseCategoryController::store
* @see app/Http/Controllers/Expenses/ExpenseCategoryController.php:13
* @route '/expense-categories'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Expenses\ExpenseCategoryController::store
* @see app/Http/Controllers/Expenses/ExpenseCategoryController.php:13
* @route '/expense-categories'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Expenses\ExpenseCategoryController::store
* @see app/Http/Controllers/Expenses/ExpenseCategoryController.php:13
* @route '/expense-categories'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Expenses\ExpenseCategoryController::store
* @see app/Http/Controllers/Expenses/ExpenseCategoryController.php:13
* @route '/expense-categories'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Expenses\ExpenseCategoryController::update
* @see app/Http/Controllers/Expenses/ExpenseCategoryController.php:22
* @route '/expense-categories/{category}'
*/
export const update = (args: { category: number | { id: number } } | [category: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/expense-categories/{category}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Expenses\ExpenseCategoryController::update
* @see app/Http/Controllers/Expenses/ExpenseCategoryController.php:22
* @route '/expense-categories/{category}'
*/
update.url = (args: { category: number | { id: number } } | [category: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { category: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { category: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            category: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        category: typeof args.category === 'object'
        ? args.category.id
        : args.category,
    }

    return update.definition.url
            .replace('{category}', parsedArgs.category.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Expenses\ExpenseCategoryController::update
* @see app/Http/Controllers/Expenses/ExpenseCategoryController.php:22
* @route '/expense-categories/{category}'
*/
update.put = (args: { category: number | { id: number } } | [category: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Expenses\ExpenseCategoryController::update
* @see app/Http/Controllers/Expenses/ExpenseCategoryController.php:22
* @route '/expense-categories/{category}'
*/
update.patch = (args: { category: number | { id: number } } | [category: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Expenses\ExpenseCategoryController::update
* @see app/Http/Controllers/Expenses/ExpenseCategoryController.php:22
* @route '/expense-categories/{category}'
*/
const updateForm = (args: { category: number | { id: number } } | [category: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Expenses\ExpenseCategoryController::update
* @see app/Http/Controllers/Expenses/ExpenseCategoryController.php:22
* @route '/expense-categories/{category}'
*/
updateForm.put = (args: { category: number | { id: number } } | [category: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Expenses\ExpenseCategoryController::update
* @see app/Http/Controllers/Expenses/ExpenseCategoryController.php:22
* @route '/expense-categories/{category}'
*/
updateForm.patch = (args: { category: number | { id: number } } | [category: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Expenses\ExpenseCategoryController::destroy
* @see app/Http/Controllers/Expenses/ExpenseCategoryController.php:31
* @route '/expense-categories/{category}'
*/
export const destroy = (args: { category: number | { id: number } } | [category: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/expense-categories/{category}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Expenses\ExpenseCategoryController::destroy
* @see app/Http/Controllers/Expenses/ExpenseCategoryController.php:31
* @route '/expense-categories/{category}'
*/
destroy.url = (args: { category: number | { id: number } } | [category: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { category: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { category: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            category: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        category: typeof args.category === 'object'
        ? args.category.id
        : args.category,
    }

    return destroy.definition.url
            .replace('{category}', parsedArgs.category.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Expenses\ExpenseCategoryController::destroy
* @see app/Http/Controllers/Expenses/ExpenseCategoryController.php:31
* @route '/expense-categories/{category}'
*/
destroy.delete = (args: { category: number | { id: number } } | [category: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Expenses\ExpenseCategoryController::destroy
* @see app/Http/Controllers/Expenses/ExpenseCategoryController.php:31
* @route '/expense-categories/{category}'
*/
const destroyForm = (args: { category: number | { id: number } } | [category: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Expenses\ExpenseCategoryController::destroy
* @see app/Http/Controllers/Expenses/ExpenseCategoryController.php:31
* @route '/expense-categories/{category}'
*/
destroyForm.delete = (args: { category: number | { id: number } } | [category: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const expenseCategories = {
    store: Object.assign(store, store),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
}

export default expenseCategories