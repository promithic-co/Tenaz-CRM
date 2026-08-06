export type WhatsappTemplateMediaState =
    | 'not_applicable'
    | 'missing'
    | 'valid'
    | 'expired'
    | 'unsupported';

export type WhatsappTemplateMedia = {
    format: string | null;
    state: WhatsappTemplateMediaState;
    requires_image: boolean;
    supported: boolean;
    sendable: boolean;
    unavailable_reason: string | null;
    preview_url: string | null;
    filename: string | null;
    size_bytes: number | null;
    uploaded_at: string | null;
    expires_at: string | null;
};
