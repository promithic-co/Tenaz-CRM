<?php

namespace App\Exceptions;

/**
 * A Meta failure that belongs to the WhatsApp Business Account, not to the recipient
 * (locked/restricted business, payment method problem). Extends the campaign
 * configuration rejection so it inherits the immediate pause-the-campaign handling
 * instead of being counted as one isolated per-message failure.
 */
class MetaAccountBlockedException extends MetaCampaignConfigurationException {}
