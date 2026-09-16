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
            interfaceMode?: boolean;
            [key: string]: unknown;
        };
    }
}
