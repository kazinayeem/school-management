import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export type BrandingRole = 'admin' | 'staff' | 'student' | 'parent';

export interface DemoAccount {
    username: string;
    password: string;
    email: string;
    name: string;
    role: string;
}

export interface Language {
    code: string;
    name: string;
    flag: string;
    is_rtl: boolean;
    is_default: boolean;
}

export interface Branding {
    name: string;
    logo: string;
    logoWhite: string;
    favicon: string;
    theme: { primary: string };
    demo: Record<BrandingRole, DemoAccount>;
    languages: Language[];
}

export interface SharedData {
    menu: { title: string; url: string; icon: string }[];
    name: string;
    branding: Branding;
    quote: { message: string; author: string };
    auth: Auth;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    role: 'admin' | 'staff' | 'student' | 'parent';
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
