import ProfileController from './ProfileController'
import PasswordController from './PasswordController'
import TwoFactorAuthenticationController from './TwoFactorAuthenticationController'
import AutoTagSettingsController from './AutoTagSettingsController'
import TeamController from './TeamController'
const Settings = {
    ProfileController: Object.assign(ProfileController, ProfileController),
PasswordController: Object.assign(PasswordController, PasswordController),
TwoFactorAuthenticationController: Object.assign(TwoFactorAuthenticationController, TwoFactorAuthenticationController),
AutoTagSettingsController: Object.assign(AutoTagSettingsController, AutoTagSettingsController),
TeamController: Object.assign(TeamController, TeamController),
}

export default Settings