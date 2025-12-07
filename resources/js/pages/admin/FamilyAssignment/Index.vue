<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { Users, TrendingUp, AlertCircle, RefreshCw, PlayCircle } from 'lucide-vue-next'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'

interface Member {
    id: number
    first_name: string
    father_name: string
    family_name: string
    mother_full_name: string | null
    role?: 'father' | 'mother' | 'child'
    gender_name?: string | null
}

interface Family {
    id: number
    canonical_name: string
    sijil_number: number
    town_name?: string | null
    members_count: number
    members: Member[]
}

interface Stats {
    total_voters: number
    voters_with_family: number
    voters_without_family: number
    total_families: number
    percentage_assigned: number
}

const props = defineProps<{
    stats: Stats
    families: Family[]
}>()

console.log(props.families);

const assignIncremental = () => {
    if (confirm('هل تريد تعيين العائلات للناخبين الذين ليس لديهم عائلة؟')) {
        router.post('/admin/family-assignment/assign', {}, {
            preserveScroll: true,
        })
    }
}

const assignAll = () => {
    if (confirm('هل تريد إعادة تعيين العائلات لجميع الناخبين؟ قد تستغرق هذه العملية وقتاً طويلاً.')) {
        router.post('/admin/family-assignment/assign-all', {}, {
            preserveScroll: true,
        })
    }
}

const formatNumber = (num: number) => {
    return new Intl.NumberFormat('ar-EG').format(num)
}

const memberIcon = (member: Partial<Member>) => {
    if (member.role === 'father') {
        return { emoji: '👨', color: 'text-blue-600' }
    }

    if (member.role === 'mother') {
        return { emoji: '👩', color: 'text-pink-600' }
    }

    // children: decide based on gender_name if available
    const g = (member.gender_name || '').toLowerCase()
    if (g.includes('ذكور') || g.includes('male') || g === 'm') {
        return { emoji: '👦', color: 'text-blue-500' }
    }

    if (g.includes('اناث') || g.includes('female') || g === 'f') {
        return { emoji: '👧', color: 'text-pink-500' }
    }

    return { emoji: '👤', color: 'text-gray-500' }
}
</script>

<template>
    <AdminLayout>
        <Head title="تعيين العائلات - الإدارة" />

        <div class="container mx-auto p-6 max-w-7xl space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">تعيين العائلات</h1>
                    <p class="text-muted-foreground mt-1">عرض وإدارة تعيين العائلات للناخبين</p>
                </div>
                <div class="flex gap-3">
                    <Button @click="assignIncremental" variant="outline" class="gap-2">
                        <PlayCircle class="h-4 w-4" />
                        تعيين الناخبين الجدد
                    </Button>
                    <Button @click="assignAll" variant="default" class="gap-2">
                        <RefreshCw class="h-4 w-4" />
                        إعادة تعيين الكل
                    </Button>
                </div>
            </div>

            <!-- Info Alert -->
            <Alert variant="default" class="bg-blue-50 dark:bg-blue-950/30 border-blue-200 dark:border-blue-900">
                <AlertCircle class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                <AlertTitle class="text-blue-900 dark:text-blue-100">معلومات</AlertTitle>
                <AlertDescription class="text-blue-800 dark:text-blue-200">
                    يتم تعيين العائلات تلقائياً بعد استيراد الناخبين. يمكنك أيضاً تشغيل العملية يدوياً من هنا.
                    العملية تعمل في الخلفية ولا تحتاج للانتظار.
                </AlertDescription>
            </Alert>

            <!-- Statistics Cards -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">إجمالي الناخبين</CardTitle>
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ formatNumber(stats.total_voters) }}</div>
                        <p class="text-xs text-muted-foreground mt-1">عدد الناخبين في النظام</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">مع عائلة</CardTitle>
                        <TrendingUp class="h-4 w-4 text-green-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-green-600">{{ formatNumber(stats.voters_with_family) }}</div>
                        <p class="text-xs text-muted-foreground mt-1">
                            {{ stats.percentage_assigned }}% من المجموع
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">بدون عائلة</CardTitle>
                        <AlertCircle class="h-4 w-4 text-orange-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-orange-600">{{ formatNumber(stats.voters_without_family) }}</div>
                        <p class="text-xs text-muted-foreground mt-1">يحتاجون للتعيين</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">إجمالي العائلات</CardTitle>
                        <Users class="h-4 w-4 text-blue-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-blue-600">{{ formatNumber(stats.total_families) }}</div>
                        <p class="text-xs text-muted-foreground mt-1">عدد العائلات المسجلة</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Families -->
            <Card>
                <CardHeader>
                    <CardTitle>أكبر 20 عائلة</CardTitle>
                    <CardDescription>العائلات مع أعضائها مرتبة حسب عدد الأعضاء</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="families.length === 0" class="text-center py-12 text-muted-foreground">
                        <Users class="mx-auto h-12 w-12 mb-4 opacity-50" />
                        <p>لا توجد عائلات بعد</p>
                    </div>
                    <div v-else class="space-y-4">
                        <div v-for="family in families" :key="family.id" class="border rounded-lg p-4">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <h3 class="font-semibold text-lg">{{ family.canonical_name }}</h3>
                                    <div class="flex gap-2 mt-1">
                                        <Badge variant="outline">رقم السجل: {{ family.sijil_number }}</Badge>
                                        <Badge variant="outline">البلدة: {{ family.town_name || '-' }}</Badge>
                                        <Badge variant="secondary">{{ family.members_count }} عضو</Badge>
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="hover:bg-transparent">
                                            <TableHead class="text-right">الاسم الأول</TableHead>
                                            <TableHead class="text-right">اسم الأب</TableHead>
                                            <TableHead class="text-right">اسم العائلة</TableHead>
                                            <TableHead class="text-right">اسم الأم</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="member in family.members" :key="member.id">
                                            <TableCell class="flex items-center gap-2">
                                                <span :class="memberIcon(member).color + ' text-sm'">{{ memberIcon(member).emoji }}</span>
                                                <span>{{ member.first_name }}</span>
                                            </TableCell>
                                            <TableCell>{{ member.father_name }}</TableCell>
                                            <TableCell>{{ member.family_name }}</TableCell>
                                            <TableCell>{{ member.mother_full_name || '-' }}</TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
