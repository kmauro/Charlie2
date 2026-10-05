import type {
    AppFlashData,
    AuthenticatedUser,
} from '@/shared/types/inertia';
import '@inertiajs/core';

declare module '@inertiajs/core' {
    interface InertiaConfig {
        errorValueType: string;
        flashDataType: AppFlashData;
        sharedPageProps: {
            appName: string;
            auth: {
                user: AuthenticatedUser | null;
            };
            organizationContext: {
                entities: Array<{
                    id: number;
                    name: string;
                    locations: Array<{ id: number; name: string }>;
                }>;
                isLocked: boolean;
                selectedLocationId: number | null;
            };
            interfaceMode?: boolean;
            [key: string]: unknown;
        };
    }
}
