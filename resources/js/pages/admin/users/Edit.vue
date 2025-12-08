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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuCheckboxItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { ChevronDown } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';

interface Role {
    id: number;
    name: string;
}

interface Town {
    id: number;
    name: string;
    district: string;
}

interface User {
    id: number;
    name: string;
    email: string;
    mobile_number: string | null;
    role_id: number | null;
    region_ids: number[] | null;
    is_active: boolean;
    is_allowed: boolean;
    is_blocked: boolean;
    role?: Role;
}

const props = defineProps<{
    user: User;
    roles: Role[];
    towns: Town[];
}>();

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
    role_id: props.user.role_id?.toString() || '',
    mobile_number: props.user.mobile_number || '',
    region_ids: props.user.region_ids ? [...props.user.region_ids] : [] as number[],
    is_active: props.user.is_active,
    is_allowed: props.user.is_allowed,
    is_blocked: props.user.is_blocked,
});

const submit = () => {
    form.put(`/admin/users/${props.user.id}`);
};
</script>

<template>
    <AdminLayout>
        <Head :title="`تعديل: ${user.name}`" />

        <div class="container mx-auto max-w-2xl space-y-6 p-6">
            <div>
                <h2 class="text-3xl font-bold tracking-tight">تعديل المستخدم</h2>
                <p class="text-muted-foreground">تحديث بيانات {{ user.name }}</p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>معلومات المستخدم</CardTitle>
                    <CardDescription>
                        قم بتعديل البيانات المطلوبة
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

                        <!-- Mobile -->
                        <div class="space-y-2">
                            <Label for="mobile_number">رقم الجوال (اختياري)</Label>
                            <Input
                                id="mobile_number"
                                v-model="form.mobile_number"
                                type="tel"
                                placeholder="05xxxxxxxx"
                            />
                            <InputError :message="form.errors.mobile_number" />
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

                        <!-- Regions -->
                        <div class="space-y-2">
                            <Label>المناطق المسموحة (اختياري)</Label>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline" class="w-full justify-between">
                                        <span>
                                            {{ form.region_ids.length > 0 
                                                ? `تم اختيار ${form.region_ids.length} منطقة` 
                                                : 'اختر المناطق' 
                                            }}
                                        </span>
                                        <ChevronDown class="h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent class="w-full max-h-60 overflow-y-auto">
                                    <DropdownMenuLabel>اختر المناطق</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuCheckboxItem
                                        v-for="town in towns"
                                        :key="town.id"
                                        :checked="form.region_ids.includes(town.id)"
                                        @click="() => {
                                            if (form.region_ids.includes(town.id)) {
                                                form.region_ids = form.region_ids.filter(id => id !== town.id);
                                            } else {
                                                form.region_ids.push(town.id);
                                            }
                                        }"
                                    >
                                        {{ town.name }} - {{ town.district }}
                                    </DropdownMenuCheckboxItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <div v-if="form.region_ids.length > 0" class="flex flex-wrap gap-1">
                                <Badge
                                    v-for="regionId in form.region_ids"
                                    :key="regionId"
                                    variant="secondary"
                                    class="text-xs"
                                >
                                    {{ towns.find(t => t.id === regionId)?.name }}
                                </Badge>
                            </div>
                            <InputError :message="form.errors.region_ids" />
                        </div>

                        <!-- Password (Optional) -->
                        <div class="space-y-2">
                            <Label for="password">كلمة المرور الجديدة (اختياري)</Label>
                            <Input
                                id="password"
                                v-model="form.password"
                                type="password"
                                placeholder="اتركه فارغاً إذا لم ترغب بتغييره"
                            />
                            <InputError :message="form.errors.password" />
                        </div>

                        <!-- Password Confirmation -->
                        <div v-if="form.password" class="space-y-2">
                            <Label for="password_confirmation">تأكيد كلمة المرور</Label>
                            <Input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                placeholder="تأكيد كلمة المرور"
                            />
                        </div>

                        <!-- Status Switches -->
                        <div class="space-y-4 rounded-lg border p-4">
                            <h3 class="font-medium">حالة المستخدم</h3>

                            <div class="flex items-center justify-between">
                                <Label for="is_active" class="cursor-pointer">نشط</Label>
                                <Switch id="is_active" v-model:checked="form.is_active" />
                            </div>

                            <div class="flex items-center justify-between">
                                <Label for="is_allowed" class="cursor-pointer">
                                    مسموح بالدخول
                                </Label>
                                <Switch id="is_allowed" v-model:checked="form.is_allowed" />
                            </div>

                            <div class="flex items-center justify-between">
                                <Label for="is_blocked" class="cursor-pointer">محظور</Label>
                                <Switch id="is_blocked" v-model:checked="form.is_blocked" />
                            </div>
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
                                حفظ التعديلات
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                @click="$inertia.visit('/admin/users')"
                            >
                                إلغاء
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
