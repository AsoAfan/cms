import ExpenseController from './ExpenseController'
import ExpenseCategoryController from './ExpenseCategoryController'

const Expenses = {
    ExpenseController: Object.assign(ExpenseController, ExpenseController),
    ExpenseCategoryController: Object.assign(ExpenseCategoryController, ExpenseCategoryController),
}

export default Expenses