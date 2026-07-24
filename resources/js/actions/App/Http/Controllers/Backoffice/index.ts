import BackofficeController from './BackofficeController'
import BackofficeActiveTenantController from './BackofficeActiveTenantController'
import BackofficeAgentController from './BackofficeAgentController'
import BackofficeAgentModelController from './BackofficeAgentModelController'
import BackofficeAgentToolController from './BackofficeAgentToolController'
import BackofficeAgentPromptController from './BackofficeAgentPromptController'
import BackofficeTemplateController from './BackofficeTemplateController'
import BackofficeNicheTemplateController from './BackofficeNicheTemplateController'
import BackofficeTenantController from './BackofficeTenantController'
const Backoffice = {
    BackofficeController: Object.assign(BackofficeController, BackofficeController),
BackofficeActiveTenantController: Object.assign(BackofficeActiveTenantController, BackofficeActiveTenantController),
BackofficeAgentController: Object.assign(BackofficeAgentController, BackofficeAgentController),
BackofficeAgentModelController: Object.assign(BackofficeAgentModelController, BackofficeAgentModelController),
BackofficeAgentToolController: Object.assign(BackofficeAgentToolController, BackofficeAgentToolController),
BackofficeAgentPromptController: Object.assign(BackofficeAgentPromptController, BackofficeAgentPromptController),
BackofficeTemplateController: Object.assign(BackofficeTemplateController, BackofficeTemplateController),
BackofficeNicheTemplateController: Object.assign(BackofficeNicheTemplateController, BackofficeNicheTemplateController),
BackofficeTenantController: Object.assign(BackofficeTenantController, BackofficeTenantController),
}

export default Backoffice