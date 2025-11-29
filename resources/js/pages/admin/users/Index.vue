<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface User {
    id: number;
    name: string;
    email: string;
    role?: { name: string };
    is_active: boolean;
    is_allowed: boolean;
    is_blocked: boolean;
    created_at: string;
}

const props = defineProps<{
    users: {
        data: User[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        search?: string;
        filter_status?: string;
    };
}>();

const search = ref(props.filters.search || '');
const filterStatus = ref(props.filters.filter_status || '');

const searchUsers = () => {
    router.get(
        '/admin/users',
        { search: search.value, filter_status: filterStatus.value },
        { preserveState: true }
    );
};

const approveUser = (userId: number) => {
    router.post(`/admin/users/${userId}/approve`, {}, { preserveScroll: true });
};

const blockUser = (userId: number) => {
    if (confirm('هل أنت متأكد من حظر هذا المستخدم؟')) {
        router.post(`/admin/users/${userId}/block`, {}, { preserveScroll: true });
    }
};

const unblockUser = (userId: number) => {
    router.post(`/admin/users/${userId}/unblock`, {}, { preserveScroll: true });
};

const deleteUser = (userId: number) => {
    if (confirm('هل أنت متأكد من حذف هذا المستخدم؟ لا يمكن التراجع عن هذا الإجراء.')) {
        router.delete(`/admin/users/${userId}`, { preserveScroll: true });
    }
};

const getStatusBadge = (user: User) => {
    if (user.is_blocked) {
        return { text: 'محظور', variant: 'destructive' as const };
    }
    if (!user.is_allowed) {
        return { text: 'معلق', variant: 'secondary' as const };
    }
    if (user.is_active) {
        return { text: 'نشط', variant: 'default' as const };
    }
    return { text: 'غير نشط', variant: 'outline' as const };
};
</script>

<template>
    <AdminLayout>
        <Head title="إدارة المستخدمين" />

        <div class="container mx-auto space-y-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-3xl font-bold tracking-tight">المستخدمين</h2>
                    <p class="text-muted-foreground">إدارة جميع المستخدمين في النظام</p>
                </div>
                <Link href="/admin/users/create">
                    <Button>
                        <svg
                            class="ml-2 h-4 w-4"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path
                                d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z"
                            />
                        </svg>
                        إضافة مستخدم
                    </Button>
                </Link>
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-4 md:flex-row">
                <div class="flex-1">
                    <Input
                        v-model="search"
                        placeholder="البحث بالاسم أو البريد الإلكتروني..."
                        @keyup.enter="searchUsers"
                    />
                </div>
                <Select v-model="filterStatus" @update:model-value="searchUsers">
                    <SelectTrigger class="w-[200px]">
                        <SelectValue placeholder="الحالة" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="">الكل</SelectItem>
                        <SelectItem value="pending">معلق</SelectItem>
                        <SelectItem value="approved">موافق عليه</SelectItem>
                        <SelectItem value="blocked">محظور</SelectItem>
                    </SelectContent>
                </Select>
                <Button @click="searchUsers">بحث</Button>
            </div>

            <!-- Users Table -->
            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>الاسم</TableHead>
                            <TableHead>البريد الإلكتروني</TableHead>
                            <TableHead>الدور</TableHead>
                            <TableHead>الحالة</TableHead>
                            <TableHead>تاريخ التسجيل</TableHead>
                            <TableHead class="text-left">الإجراءات</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="user in users.data" :key="user.id">
                            <TableCell class="font-medium">{{ user.name }}</TableCell>
                            <TableCell>{{ user.email }}</TableCell>
                            <TableCell>
                                <Badge variant="outline">
                                    {{ user.role?.name || 'لا يوجد' }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <Badge :variant="getStatusBadge(user).variant">
                                    {{ getStatusBadge(user).text }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                {{ new Date(user.created_at).toLocaleDateString('ar-SA') }}
                            </TableCell>
                            <TableCell class="text-left">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="sm">
                                            <svg
                                                class="h-4 w-4"
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                            >
                                                <path
                                                    d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"
                                                />
                                            </svg>
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem as-child>
                                            <Link :href="`/admin/users/${user.id}/edit`">
                                                تعديل
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="!user.is_allowed && !user.is_blocked"
                                            @click="approveUser(user.id)"
                                        >
                                            الموافقة
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="!user.is_blocked"
                                            @click="blockUser(user.id)"
                                            class="text-destructive"
                                        >
                                            حظر
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="user.is_blocked"
                                            @click="unblockUser(user.id)"
                                        >
                                            إلغاء الحظر
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            @click="deleteUser(user.id)"
                                            class="text-destructive"
                                        >
                                            حذف
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="users.data.length === 0">
                            <TableCell colspan="6" class="text-center text-muted-foreground">
                                لا توجد نتائج
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- Pagination -->
            <div
                v-if="users.last_page > 1"
                class="flex items-center justify-between"
            >
                <p class="text-sm text-muted-foreground">
                    عرض {{ users.data.length }} من {{ users.total }} نتيجة
                </p>
                <div class="flex gap-2">
                    <Link
                        v-for="page in users.last_page"
                        :key="page"
                        :href="`/admin/users?page=${page}`"
                        preserve-state
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            :class="{ 'bg-primary text-primary-foreground': page === users.current_page }"
                        >
                            {{ page }}
                        </Button>
                    </Link>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
