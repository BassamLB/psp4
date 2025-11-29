<script setup lang="ts">
import { dashboard, login, register } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Vote, Users, ChartBar, Shield, CheckCircle, LayoutGrid } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const features = [
    {
        icon: Vote,
        title: 'إدارة الناخبين',
        description: 'نظام متطور لإدارة قاعدة بيانات الناخبين وتتبع المشاركة الانتخابية',
    },
    {
        icon: Users,
        title: 'فرق العمل',
        description: 'تنظيم وإدارة فرق العمل الميدانية وتوزيع المهام بكفاءة',
    },
    {
        icon: ChartBar,
        title: 'تحليلات فورية',
        description: 'تقارير وإحصائيات شاملة لمتابعة سير العملية الانتخابية',
    },
    {
        icon: Shield,
        title: 'أمان عالي',
        description: 'حماية متقدمة للبيانات مع صلاحيات محددة لكل مستخدم',
    },
];
</script>

<template>
    <Head title="مرحبًا بك">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-gray-50 via-gray-100 to-gray-200 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950 p-4">
        <!-- Main Content Card -->
        <div class="w-full max-w-lg ">
            <CardHeader class="text-center space-y-6 pb-6">
                <!-- Logo -->
                <div class="flex justify-center">
                    <div class="relative">
                        <div class="absolute inset-0 rounded-full bg-primary/20 blur-2xl"></div>
                        <div class="relative w-32 h-32 rounded-full bg-white dark:bg-gray-900 flex items-center justify-center shadow-xl">
                            <img 
                                src="/images/PSP_Lebanon_logo_394.png" 
                                alt="شعار الحزب التقدمي الاشتراكي"
                                class="object-contain"
                            />
                        </div>
                    </div>
                </div>

                <!-- Title and Badge -->
                <div class="space-y-3">
                    <Badge variant="secondary" class="text-xs font-semibold px-4 py-1">
                        الانتخابات النيابية ٢٠٢٦
                    </Badge>
                    <CardTitle class="text-3xl font-bold tracking-tight">
                        نظام إدارة الانتخابات
                    </CardTitle>
                    <CardDescription class="text-sm leading-relaxed px-4">
                        منصة متكاملة لإدارة وتنظيم العملية الانتخابية بكفاءة وشفافية عالية
                    </CardDescription>
                </div>
            </CardHeader>
            

            <CardContent class="space-y-6 pb-8">

                <!-- Action Buttons -->
                <div class="flex flex-col gap-2 pt-2">
                    <Button v-if="$page.props.auth.user" as-child size="lg" class="w-full">
                        <Link :href="dashboard()">
                            <LayoutGrid class="ml-2 h-4 w-4" />
                            لوحة التحكم
                        </Link>
                    </Button>
                    <template v-else>
                        <Button as-child size="lg" class="w-full">
                            <Link :href="login()">
                                تسجيل الدخول
                            </Link>
                        </Button>
                        <Button v-if="canRegister" as-child variant="outline" size="lg" class="w-full">
                            <Link :href="register()">
                                تفعيل حساب
                            </Link>
                        </Button>
                    </template>
                </div>

                <!-- Features Grid -->
                <div class="grid grid-cols-2 gap-4">
                    <Card v-for="feature in features" :key="feature.title" class="border hover:border-primary/50 transition-all hover:shadow-md">
                        <CardHeader class="space-y-3 p-4">
                            <div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center mx-auto">
                                <component :is="feature.icon" class="w-5 h-5 text-primary" />
                            </div>
                            <CardTitle class="text-sm text-center">{{ feature.title }}</CardTitle>
                            <CardDescription class="text-xs leading-relaxed text-center">
                                {{ feature.description }}
                            </CardDescription>
                        </CardHeader>
                    </Card>
                </div>

                <!-- Info Section -->
                <div class="flex flex-col items-center gap-3 pt-4 border-t">
                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                        <CheckCircle class="h-4 w-4 text-green-600 dark:text-green-500" />
                        <span>الحزب التقدمي الاشتراكي - لجنة المعلوماتية</span>
                    </div>
                    <p class="text-xs text-muted-foreground text-center px-4 leading-relaxed">
                        نظام آمن ومتطور لإدارة كافة جوانب العملية الانتخابية من التخطيط إلى التنفيذ والمتابعة
                    </p>
                </div>

                
            </CardContent>
        </div>
    </div>
</template>
