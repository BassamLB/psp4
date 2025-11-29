<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';
import { Form, Head } from '@inertiajs/vue3';
</script>

<template>
    <AuthBase
        title="إنشاء حساب"
        description="أدخل رمز التسجيل الخاص بك لإكمال إنشاء حسابك"
    >
        <Head title="إنشاء حساب" />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="registration_code">رمز التسجيل</Label>
                    <Input
                        id="registration_code"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        name="registration_code"
                        placeholder="أدخل رمز التسجيل المرسل إليك"
                    />
                    <InputError :message="errors.registration_code" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">البريد الإلكتروني</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        :tabindex="2"
                        autocomplete="email"
                        name="email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">كلمة المرور</Label>
                    <Input
                        id="password"
                        type="password"
                        required
                        :tabindex="3"
                        autocomplete="new-password"
                        name="password"
                        placeholder="كلمة المرور"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">تأكيد كلمة المرور</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="تأكيد كلمة المرور"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full"
                    tabindex="5"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" />
                    إنشاء حساب
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                هل لديك حساب بالفعل؟
                <TextLink
                    :href="login()"
                    class="underline underline-offset-4"
                    :tabindex="5"
                    >تسجيل الدخول</TextLink
                >
            </div>
        </Form>
    </AuthBase>
</template>
