<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { logout } from '@/routes';
import { send } from '@/routes/verification';
import { Form, Head } from '@inertiajs/vue3';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <AuthLayout
        title="تحقق من البريد الإلكتروني"
        description="يرجى التحقق من بريدك الإلكتروني بالنقر على الرابط الذي أرسلناه إليك."
    >
        <Head title="التحقق من البريد الإلكتروني" />

        <div
            v-if="status === 'verification-link-sent'"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            تم إرسال رابط تحقق جديد إلى عنوان البريد الإلكتروني الذي
            قدمته أثناء التسجيل.
        </div>

        <Form
            v-bind="send.form()"
            class="space-y-6 text-center"
            v-slot="{ processing }"
        >
            <Button :disabled="processing" variant="secondary">
                <Spinner v-if="processing" />
                إعادة إرسال رابط التحقق
            </Button>

            <TextLink
                :href="logout()"
                as="button"
                class="mx-auto block text-sm"
            >
                تسجيل الخروج
            </TextLink>
        </Form>
    </AuthLayout>
</template>
