import { Config } from 'ziggy-js';

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    is_admin: boolean;
    credits?: number;
    active_subscription?: {
        id: string;
        plan_type: 'developer' | 'agency';
        status: 'active' | 'trialing' | 'cancelled' | 'expired';
        daily_individual_scans_used: number;
        daily_sync_scans_used: number;
        monthly_pentests_used: number;
        ends_at: string | null;
        created_at: string;
    };
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    ziggy: Config & { location: string };
};
