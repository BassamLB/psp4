<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

interface Invitation {
    id: number;
    name: string;
    email: string;
    registration_code: string;
    created_at: string;
    role?: { name: string };
}

defineProps<{
    invitations: Invitation[];
}>();
</script>

<template>
    <AdminLayout>
        <Head title="الدعوات" />

        <div class="container mx-auto space-y-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-3xl font-bold tracking-tight">الدعوات</h2>
                    <p class="text-muted-foreground">إدارة دعوات المستخدمين الجدد</p>
                </div>
                <Link href="/admin/invitations/create">
                    <Button>
                        <svg
                            class="ml-2 h-4 w-4"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z" />
                        </svg>
                        إرسال دعوة جديدة
                    </Button>
                </Link>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>الدعوات المعلقة</CardTitle>
                    <CardDescription>
                        المستخدمين الذين تم دعوتهم ولم يكملوا التسجيل بعد
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="invitations.length === 0" class="py-12 text-center text-muted-foreground">
                        لا توجد دعوات معلقة
                    </div>
                    <div v-else class="rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>الاسم</TableHead>
                                    <TableHead>البريد الإلكتروني</TableHead>
                                    <TableHead>الدور</TableHead>
                                    <TableHead>رمز التسجيل</TableHead>
                                    <TableHead>تاريخ الإرسال</TableHead>
                                    <TableHead>الحالة</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="invitation in invitations" :key="invitation.id">
                                    <TableCell class="font-medium">{{ invitation.name }}</TableCell>
                                    <TableCell>{{ invitation.email }}</TableCell>
                                    <TableCell>
                                        <Badge variant="outline">
                                            {{ invitation.role?.name || 'لا يوجد' }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <code class="rounded bg-muted px-2 py-1 text-xs">
                                            {{ invitation.registration_code.substring(0, 12) }}
                                        </code>
                                    </TableCell>
                                    <TableCell>
                                        {{ new Date(invitation.created_at).toLocaleDateString('ar-LB') }}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">معلق</Badge>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
