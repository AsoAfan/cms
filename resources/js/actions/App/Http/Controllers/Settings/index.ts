import ExchangeRateController from './ExchangeRateController'
import BankController from './BankController'
import BankAdjustmentController from './BankAdjustmentController'
import BankTransferController from './BankTransferController'
import CurrencyController from './CurrencyController'
import BackupController from './BackupController'
import UpdateController from './UpdateController'

const Settings = {
    ExchangeRateController: Object.assign(ExchangeRateController, ExchangeRateController),
    BankController: Object.assign(BankController, BankController),
    BankAdjustmentController: Object.assign(BankAdjustmentController, BankAdjustmentController),
    BankTransferController: Object.assign(BankTransferController, BankTransferController),
    CurrencyController: Object.assign(CurrencyController, CurrencyController),
    BackupController: Object.assign(BackupController, BackupController),
    UpdateController: Object.assign(UpdateController, UpdateController),
}

export default Settings