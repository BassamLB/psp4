<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { Form, Head } from '@inertiajs/vue3';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <AuthBase
        title="تسجيل الدخول إلى حسابك"
        description="أدخل بريدك الإلكتروني وكلمة المرور لتسجيل الدخول"
    >
        <Head title="تسجيل الدخول" />

        <div
            v-if="status"
            class="mb-4 rounded-lg bg-green-50 p-3 text-center text-sm font-medium text-green-600 dark:bg-green-950/50 dark:text-green-400"
        >
            {{ status }}
        </div>

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="email">البريد الإلكتروني</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">كلمة المرور</Label>
                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="text-sm"
                            :tabindex="5"
                        >
                            نسيت كلمة المرور؟
                        </TextLink>
                    </div>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="••••••••"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center gap-2">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <Label
                        for="remember"
                        class="text-sm font-normal leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                    >
                        تذكّرني
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="w-full"
                    :tabindex="4"
                    :disabled="processing"
                    data-test="login-button"
                >
                    <Spinner v-if="processing" class="ml-2" />
                    تسجيل الدخول
                </Button>
            </div>

            <div
                v-if="canRegister"
                class="mt-4 text-center text-sm text-muted-foreground"
            >
                إذا كنت غير مسجل، يمكنك الاتصال بمدير الموقع لإنشاء حساب جديد.
            </div>
        </Form>
    </AuthBase>
</template>
