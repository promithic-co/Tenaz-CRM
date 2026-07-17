import BackofficeController from './BackofficeController'
import BackofficeTemplateController from './BackofficeTemplateController'
import BackofficeNicheTemplateController from './BackofficeNicheTemplateController'
import BackofficeTenantController from './BackofficeTenantController'
const Backoffice = {
    BackofficeController: Object.assign(BackofficeController, BackofficeController),
BackofficeTemplateController: Object.assign(BackofficeTemplateController, BackofficeTemplateController),
BackofficeNicheTemplateController: Object.assign(BackofficeNicheTemplateController, BackofficeNicheTemplateController),
BackofficeTenantController: Object.assign(BackofficeTenantController, BackofficeTenantController),
}

export default Backoffice