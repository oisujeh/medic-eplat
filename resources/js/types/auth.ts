export type User = {
    id: number;
    name: string;
    email: string;
    username: string | null;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type NavModule = {
    name: string;
    slug: string;
    icon: string | null;
    href: string;
};

export type Auth = {
    user: User;
    roles: string[];
    modules: NavModule[];
};

/** The facility profile captured by the first-run wizard. */
export type FacilityProfile = {
    name: string | null;
    state: string | null;
    lga: string | null;
    code: string | null;
    /** primary | secondary | tertiary */
    level: string | null;
    /** public | private | faith_based */
    ownership: string | null;
    /** phc | secondary | tertiary | private */
    tier_profile: string | null;
    /** The DHIS2 organisation unit UID for NHMIS exports. */
    dhis2_org_unit: string | null;
    /** A notice shown to every member of staff on the home screen. */
    notice: string | null;
    /** When false no charges post and no prices are shown. */
    billing_enabled: boolean;
    patient_number_prefix: string;
    patient_number_check_digit: boolean;
    completed_at: string | null;
};

export type SharedData = {
    name: string;
    auth: Auth;
    facility: FacilityProfile;
    sidebarOpen: boolean;
    [key: string]: unknown;
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
