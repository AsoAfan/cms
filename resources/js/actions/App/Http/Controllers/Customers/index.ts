import CustomerController from './CustomerController'
import CustomerPaymentController from './CustomerPaymentController'

const Customers = {
    CustomerController: Object.assign(CustomerController, CustomerController),
    CustomerPaymentController: Object.assign(CustomerPaymentController, CustomerPaymentController),
}

export default Customers