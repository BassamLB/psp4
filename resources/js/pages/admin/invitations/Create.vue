<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import InputError from '@/components/InputError.vue';

interface Role {
    id: number;
    name: string;
}

defineProps<{
    roles: Role[];
}>();

const form = useForm({
    name: '',
    email: '',
    role_id: '',
});

const submit = () => {
    form.post('/admin/invitations', {
        onSuccess: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <AdminLayout>
        <Head title="إرسال دعوة جديدة" />

        <div class="container mx-auto max-w-2xl space-y-6 p-6">
            <div>
                <h2 class="text-3xl font-bold tracking-tight">إرسال دعوة جديدة</h2>
                <p class="text-muted-foreground">دعوة مستخدم جديد للتسجيل في النظام</p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>معلومات المستخدم</CardTitle>
                    <CardDescription>
                        سيتم إنشاء رمز تسجيل وإرساله إلى المستخدم
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Name -->
                        <div class="space-y-2">
                            <Label for="name">الاسم الكامل</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                type="text"
                                required
                                placeholder="أدخل الاسم الكامل"
                            />
                            <InputError :message="form.errors.name" />
                        </div>

                        <!-- Email -->
                        <div class="space-y-2">
                            <Label for="email">البريد الإلكتروني</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                placeholder="email@example.com"
                            />
                            <InputError :message="form.errors.email" />
                        </div>

                        <!-- Role -->
                        <div class="space-y-2">
                            <Label for="role_id">الدور</Label>
                            <Select v-model="form.role_id" required>
                                <SelectTrigger>
                                    <SelectValue placeholder="اختر الدور" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="role in roles"
                                        :key="role.id"
                                        :value="role.id.toString()"
                                    >
                                        {{ role.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.role_id" />
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-4">
                            <Button type="submit" :disabled="form.processing">
                                <svg
                                    v-if="form.processing"
                                    class="ml-2 h-4 w-4 animate-spin"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        class="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    ></circle>
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                    ></path>
                                </svg>
                                إرسال الدعوة
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                @click="$inertia.visit('/admin/invitations')"
                            >
                                إلغاء
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>ملاحظات</CardTitle>
                </CardHeader>
                <CardContent class="text-sm text-muted-foreground">
                    <ul class="list-inside list-disc space-y-2">
                        <li>سيتم إنشاء رمز تسجيل فريد للمستخدم تلقائياً</li>
                        <li>يجب على المستخدم استخدام هذا الرمز مع بريده الإلكتروني للتسجيل</li>
                        <li>يمكن للمستخدم التسجيل مرة واحدة فقط باستخدام الرمز</li>
                        <li>يحتاج المستخدم إلى موافقة المسؤول بعد التسجيل</li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
