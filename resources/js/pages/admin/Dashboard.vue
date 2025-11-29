<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

defineProps<{
    pendingUsers: number;
    pendingDevices: number;
}>();
</script>

<template>
    <AdminLayout>
        <Head title="لوحة التحكم - الإدارة" />

        <div class="container mx-auto space-y-6 p-6">
            <div>
                <h2 class="text-3xl font-bold tracking-tight">لوحة التحكم</h2>
                <p class="text-muted-foreground">مرحباً بك في لوحة التحكم الإدارية</p>
            </div>

            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">المستخدمين المعلقين</CardTitle>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            class="h-4 w-4 text-muted-foreground"
                        >
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ pendingUsers }}</div>
                        <p class="text-xs text-muted-foreground">
                            في انتظار الموافقة
                        </p>
                        <Link
                            href="/admin/users?filter_status=pending"
                            class="mt-4 inline-flex items-center text-sm font-medium text-primary hover:underline"
                        >
                            عرض الكل
                            <svg
                                class="mr-1 h-4 w-4"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </Link>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">الأجهزة المعلقة</CardTitle>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            class="h-4 w-4 text-muted-foreground"
                        >
                            <rect width="20" height="14" x="2" y="3" rx="2" />
                            <line x1="8" x2="16" y1="21" y2="21" />
                            <line x1="12" x2="12" y1="17" y2="21" />
                        </svg>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ pendingDevices }}</div>
                        <p class="text-xs text-muted-foreground">
                            أجهزة تحتاج موافقة
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">الوصول السريع</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="flex flex-col gap-2">
                            <Link href="/admin/users">
                                <Button variant="outline" class="w-full justify-start">
                                    <svg
                                        class="ml-2 h-4 w-4"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                    >
                                        <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" />
                                    </svg>
                                    إدارة المستخدمين
                                </Button>
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Quick Actions -->
            <Card>
                <CardHeader>
                    <CardTitle>الإجراءات السريعة</CardTitle>
                    <CardDescription>الإجراءات الشائعة للإدارة</CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-2">
                    <Link href="/admin/users/create">
                        <Button class="w-full">
                            <svg
                                class="ml-2 h-4 w-4"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z" />
                            </svg>
                            إضافة مستخدم جديد
                        </Button>
                    </Link>

                    <Link href="/admin/invitations">
                        <Button variant="outline" class="w-full">
                            <svg
                                class="ml-2 h-4 w-4"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                            </svg>
                            إرسال دعوة
                        </Button>
                    </Link>

                    <Link href="/admin/station-assignments">
                        <Button variant="outline" class="w-full">
                            <svg
                                class="ml-2 h-4 w-4"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                            </svg>
                            تعيينات الأقلام
                        </Button>
                    </Link>
                    <Link href="/admin/results">
                        <Button class="w-full">
                            <svg class="ml-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M3 13h4V3H3v10zm6 8h4V3h-4v18zm6 0h4V3h-4v18z" />
                            </svg>
                            عرض النتائج
                        </Button>
                    </Link>
                    <Link href="/admin/org-graph">
                        <Button variant="outline" class="w-full">
                            <svg class="ml-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M3 12a2 2 0 100-4 2 2 0 000 4zm6 8a2 2 0 100-4 2 2 0 000 4zM21 6a2 2 0 100-4 2 2 0 000 4zM7 10a2 2 0 100-4 2 2 0 000 4zm10 8a2 2 0 100-4 2 2 0 000 4zM17 8a2 2 0 100-4 2 2 0 000 4z" />
                            </svg>
                            الهيكلية التنظيمية
                        </Button>
                    </Link>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
