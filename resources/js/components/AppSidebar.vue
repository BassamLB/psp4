<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import AdminSidebar from '@/components/sidebars/AdminSidebar.vue';
import DefaultSidebar from '@/components/sidebars/DefaultSidebar.vue';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { computed } from 'vue';
import { BookOpen, Folder, LayoutGrid, Users, Mail, Monitor } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const isAdmin = computed(() => (user.value as any)?.isAdmin === true);

const mainNavItems = computed<NavItem[]>(() => {
    const baseItems: NavItem[] = [
        {
            title: 'لوحة التحكم',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    // Add admin-specific menu items
    if (isAdmin.value) {
        baseItems.push(
            {
                title: 'إدارة المستخدمين',
                href: '/admin/users',
                icon: Users,
            },
            {
                title: 'الدعوات',
                href: '/admin/invitations',
                icon: Mail,
            },
            {
                title: 'الأجهزة',
                href: '/admin/devices',
                icon: Monitor,
            }
        );
    }

    return baseItems;
});

const footerNavItems: NavItem[] = [
    {
        title: 'Github Repo',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];

const side = computed(() => (typeof document !== 'undefined' && document.documentElement?.dir === 'rtl' ? 'right' : 'left'));
</script>

<template>
    <component :is="isAdmin ? AdminSidebar : DefaultSidebar" />
    <slot />
</template>
