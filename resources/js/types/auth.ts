export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type TenantRoleValue = 'owner' | 'administrator' | 'user';

export type Auth = {
    user: User;
    currentRole?: TenantRoleValue | null;
    is_super_admin?: boolean;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
