import Api from './Api'
import IvrController from './IvrController'
import UraInboundController from './UraInboundController'
import MetaWebhookController from './MetaWebhookController'
import AgentController from './AgentController'
import VersionController from './VersionController'
import HomeRedirectController from './HomeRedirectController'
import InvitationController from './InvitationController'
import DashboardController from './DashboardController'
import SearchController from './SearchController'
import ConversasController from './ConversasController'
import LeadManagementController from './LeadManagementController'
import LeadFollowUpController from './LeadFollowUpController'
import LeadStatusController from './LeadStatusController'
import ContactController from './ContactController'
import TagController from './TagController'
import LeadTagController from './LeadTagController'
import LeadAutoTagController from './LeadAutoTagController'
import PipelineController from './PipelineController'
import ServiceTicketController from './ServiceTicketController'
import WhatsAppInstanceController from './WhatsAppInstanceController'
import MetaEmbeddedSignupController from './MetaEmbeddedSignupController'
import AgenteConfigController from './AgenteConfigController'
import AgentsController from './AgentsController'
import AgentConfigController from './AgentConfigController'
import AgentFollowUpController from './AgentFollowUpController'
import RegrasOperacionaisController from './RegrasOperacionaisController'
import ConfiguracoesController from './ConfiguracoesController'
import StatusPipelineController from './StatusPipelineController'
import LaboratoryController from './LaboratoryController'
import StressTestController from './StressTestController'
import WhatsappTemplateController from './WhatsappTemplateController'
import CampaignController from './CampaignController'
import ContactListController from './ContactListController'
import ContactListEntryController from './ContactListEntryController'
import VoiceInstanceController from './VoiceInstanceController'
import VoiceCampaignController from './VoiceCampaignController'
import UraApiKeyController from './UraApiKeyController'
import VoicePreviewController from './VoicePreviewController'
import PlaygroundController from './PlaygroundController'
import Settings from './Settings'
import Backoffice from './Backoffice'
import OnboardingController from './OnboardingController'
const Controllers = {
    Api: Object.assign(Api, Api),
IvrController: Object.assign(IvrController, IvrController),
UraInboundController: Object.assign(UraInboundController, UraInboundController),
MetaWebhookController: Object.assign(MetaWebhookController, MetaWebhookController),
AgentController: Object.assign(AgentController, AgentController),
VersionController: Object.assign(VersionController, VersionController),
HomeRedirectController: Object.assign(HomeRedirectController, HomeRedirectController),
InvitationController: Object.assign(InvitationController, InvitationController),
DashboardController: Object.assign(DashboardController, DashboardController),
SearchController: Object.assign(SearchController, SearchController),
ConversasController: Object.assign(ConversasController, ConversasController),
LeadManagementController: Object.assign(LeadManagementController, LeadManagementController),
LeadFollowUpController: Object.assign(LeadFollowUpController, LeadFollowUpController),
LeadStatusController: Object.assign(LeadStatusController, LeadStatusController),
ContactController: Object.assign(ContactController, ContactController),
TagController: Object.assign(TagController, TagController),
LeadTagController: Object.assign(LeadTagController, LeadTagController),
LeadAutoTagController: Object.assign(LeadAutoTagController, LeadAutoTagController),
PipelineController: Object.assign(PipelineController, PipelineController),
ServiceTicketController: Object.assign(ServiceTicketController, ServiceTicketController),
WhatsAppInstanceController: Object.assign(WhatsAppInstanceController, WhatsAppInstanceController),
MetaEmbeddedSignupController: Object.assign(MetaEmbeddedSignupController, MetaEmbeddedSignupController),
AgenteConfigController: Object.assign(AgenteConfigController, AgenteConfigController),
AgentsController: Object.assign(AgentsController, AgentsController),
AgentConfigController: Object.assign(AgentConfigController, AgentConfigController),
AgentFollowUpController: Object.assign(AgentFollowUpController, AgentFollowUpController),
RegrasOperacionaisController: Object.assign(RegrasOperacionaisController, RegrasOperacionaisController),
ConfiguracoesController: Object.assign(ConfiguracoesController, ConfiguracoesController),
StatusPipelineController: Object.assign(StatusPipelineController, StatusPipelineController),
LaboratoryController: Object.assign(LaboratoryController, LaboratoryController),
StressTestController: Object.assign(StressTestController, StressTestController),
WhatsappTemplateController: Object.assign(WhatsappTemplateController, WhatsappTemplateController),
CampaignController: Object.assign(CampaignController, CampaignController),
ContactListController: Object.assign(ContactListController, ContactListController),
ContactListEntryController: Object.assign(ContactListEntryController, ContactListEntryController),
VoiceInstanceController: Object.assign(VoiceInstanceController, VoiceInstanceController),
VoiceCampaignController: Object.assign(VoiceCampaignController, VoiceCampaignController),
UraApiKeyController: Object.assign(UraApiKeyController, UraApiKeyController),
VoicePreviewController: Object.assign(VoicePreviewController, VoicePreviewController),
PlaygroundController: Object.assign(PlaygroundController, PlaygroundController),
Settings: Object.assign(Settings, Settings),
Backoffice: Object.assign(Backoffice, Backoffice),
OnboardingController: Object.assign(OnboardingController, OnboardingController),
}

export default Controllers