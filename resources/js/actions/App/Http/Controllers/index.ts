import DashboardController from './DashboardController'
import Catalog from './Catalog'
import Purchasing from './Purchasing'
import Sales from './Sales'
import Suppliers from './Suppliers'
import Customers from './Customers'
import Expenses from './Expenses'
import LoanController from './LoanController'
import Reports from './Reports'
import Settings from './Settings'
import Auth from './Auth'

const Controllers = {
    DashboardController: Object.assign(DashboardController, DashboardController),
    Catalog: Object.assign(Catalog, Catalog),
    Purchasing: Object.assign(Purchasing, Purchasing),
    Sales: Object.assign(Sales, Sales),
    Suppliers: Object.assign(Suppliers, Suppliers),
    Customers: Object.assign(Customers, Customers),
    Expenses: Object.assign(Expenses, Expenses),
    LoanController: Object.assign(LoanController, LoanController),
    Reports: Object.assign(Reports, Reports),
    Settings: Object.assign(Settings, Settings),
    Auth: Object.assign(Auth, Auth),
}

export default Controllers