import BackofficeController from './BackofficeController'
import BackofficeTemplateController from './BackofficeTemplateController'
import BackofficeTenantController from './BackofficeTenantController'
const Backoffice = {
    BackofficeController: Object.assign(BackofficeController, BackofficeController),
BackofficeTemplateController: Object.assign(BackofficeTemplateController, BackofficeTemplateController),
BackofficeTenantController: Object.assign(BackofficeTenantController, BackofficeTenantController),
}

export default Backoffice