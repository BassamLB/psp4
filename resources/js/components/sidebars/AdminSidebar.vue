<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { Users, Mail, Monitor, LayoutGrid, MapPin, ChevronDown, Globe, Building } from 'lucide-vue-next';
import Collapsible from '@/components/ui/collapsible/Collapsible.vue';
import CollapsibleTrigger from '@/components/ui/collapsible/CollapsibleTrigger.vue';
import AppLogo from '@/components/AppLogo.vue';
import NavUser from '@/components/NavUser.vue';
import { computed } from 'vue';
import { Download, Settings, Users as UsersIcon } from 'lucide-vue-next';

const side = computed(() => (typeof document !== 'undefined' && document.documentElement?.dir === 'rtl' ? 'right' : 'left'));

// Footer external links (kept inline in template)
</script>

<template>
    <Sidebar :side="side" collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu class="group-data-[collapsible=icon]:items-center">
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child tooltip="الرئيسية">
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link> 
                        <div class="text-center"> notifications  </div>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarMenu class="group-data-[collapsible=icon]:items-center">
                <SidebarMenuItem>
                        <SidebarMenuButton as-child tooltip="لوحة التحكم">
                        <Link :href="dashboard()">
                            <LayoutGrid class="size-4 mr-2 inline group-data-[collapsible=icon]:mr-0" />
                            <span class="group-data-[collapsible=icon]:hidden">لوحة التحكم</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
                <SidebarMenuItem>
                        <SidebarMenuButton as-child tooltip="إدارة المستخدمين">
                        <Link href="/admin/users">
                            <Users class="size-4 mr-2 inline group-data-[collapsible=icon]:mr-0" />
                            <span class="group-data-[collapsible=icon]:hidden">إدارة المستخدمين</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
                <SidebarMenuItem>
                    <Collapsible v-slot="{ open }">
                        <SidebarMenuButton as-child tooltip="محطات الاقتراع">
                                <CollapsibleTrigger as-child>
                                <button class="flex items-center justify-between w-full">
                                        <MapPin class="size-4 mr-2 inline group-data-[collapsible=icon]:mr-0" />
                                    <span class="flex-1 text-right">
                                        <span class="group-data-[collapsible=icon]:hidden">محطات الاقتراع</span>
                                    </span>
                                    <ChevronDown :class="[ 'transition-transform duration-200', open ? 'rotate-180' : '' ]" />
                                </button>
                            </CollapsibleTrigger>
                        </SidebarMenuButton>

                        <Transition
                            enter-from-class="opacity-0 max-h-0"
                            enter-active-class="transition-[max-height,opacity] duration-200 ease-linear"
                            enter-to-class="opacity-100 max-h-[36rem]"
                            leave-from-class="opacity-100 max-h-[36rem]"
                            leave-active-class="transition-[max-height,opacity] duration-200 ease-linear"
                            leave-to-class="opacity-0 max-h-0"
                        >
                            <div v-show="open" class="overflow-hidden">
                                <SidebarMenuSub>
                                <SidebarMenuSubItem>
                                    <SidebarMenuSubButton as-child tooltip="المحلية">
                                        <Link href="/admin/local-polling-stations">
                                            <Building class="size-4 mr-2 inline group-data-[collapsible=icon]:mr-0" />
                                           
                                                <span class="group-data-[collapsible=icon]:hidden">المحلية</span>

                                   
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                                <SidebarMenuSubItem>
                                        <SidebarMenuSubButton as-child tooltip="الخارجية">
                                        <Link href="/admin/extra-polling-stations">
                                            <Globe class="size-4 mr-2 inline group-data-[collapsible=icon]:mr-0" />
                                            <span class="group-data-[collapsible=icon]:hidden">الخارجية </span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                                </SidebarMenuSub>
                            </div>
                        </Transition>
                    </Collapsible>
                </SidebarMenuItem>
                <SidebarMenuItem>
                        <SidebarMenuButton as-child tooltip="الدعوات">
                        <Link href="/admin/invitations">
                            <Mail class="size-4 mr-2 inline group-data-[collapsible=icon]:mr-0" />
                            <span class="group-data-[collapsible=icon]:hidden">الدعوات</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
                <SidebarMenuItem>
                        <SidebarMenuButton as-child tooltip="الأجهزة">
                        <Link href="/admin/devices">
                            <Monitor class="size-4 mr-2 inline group-data-[collapsible=icon]:mr-0" />
                            <span class="group-data-[collapsible=icon]:hidden">الأجهزة</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarContent>

        <SidebarFooter>
            <SidebarGroup>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton as-child>
                                <Link href="/admin/import-voters">
                                    <Download class="size-4 mr-2 inline group-data-[collapsible=icon]:mr-0" />
                                    <span class="group-data-[collapsible=icon]:hidden">استيراد الناخبين</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <SidebarMenuButton as-child>
                                <Link href="/admin/family-assignment">
                                    <UsersIcon class="size-4 mr-2 inline group-data-[collapsible=icon]:mr-0" />
                                    <span class="group-data-[collapsible=icon]:hidden">تعيين العائلات</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <Collapsible v-slot="{ open }">
                                <SidebarMenuButton as-child>
                                    <CollapsibleTrigger as-child>
                                        <button class="flex items-center justify-between w-full">
                                            <Settings class="size-4 mr-2 inline group-data-[collapsible=icon]:mr-0" />
                                            <span class="flex-1 text-right">
                                                <span class="group-data-[collapsible=icon]:hidden">الإعدادات</span>
                                            </span>
                                            <ChevronDown :class="[ 'transition-transform duration-200', open ? 'rotate-180' : '' ]" />
                                        </button>
                                    </CollapsibleTrigger>
                                </SidebarMenuButton>

                                <Transition
                                    enter-from-class="opacity-0 max-h-0"
                                    enter-active-class="transition-[max-height,opacity] duration-200 ease-linear"
                                    enter-to-class="opacity-100 max-h-[36rem]"
                                    leave-from-class="opacity-100 max-h-[36rem]"
                                    leave-active-class="transition-[max-height,opacity] duration-200 ease-linear"
                                    leave-to-class="opacity-0 max-h-0"
                                >
                                    <div v-show="open" class="overflow-hidden">
                                        <SidebarMenuSub>
                                        <SidebarMenuSubItem>
                                            <SidebarMenuSubButton as-child>
                                                <Link href="/admin/settings/roles">الأدوار</Link>
                                            </SidebarMenuSubButton>
                                        </SidebarMenuSubItem>
                                        <SidebarMenuSubItem>
                                            <SidebarMenuSubButton as-child>
                                                <Link href="/admin/settings/belongs">الانتماءات</Link>
                                            </SidebarMenuSubButton>
                                        </SidebarMenuSubItem>
                                        <SidebarMenuSubItem>
                                            <SidebarMenuSubButton as-child>
                                                <Link href="/admin/settings/professions">المهن</Link>
                                            </SidebarMenuSubButton>
                                        </SidebarMenuSubItem>
                                        <SidebarMenuSubItem>
                                            <SidebarMenuSubButton as-child>
                                                <Link href="/admin/settings/genders">الجنس</Link>
                                            </SidebarMenuSubButton>
                                        </SidebarMenuSubItem>
                                        <SidebarMenuSubItem>
                                            <SidebarMenuSubButton as-child>
                                                <Link href="/admin/settings/sects">الطوائف</Link>
                                            </SidebarMenuSubButton>
                                        </SidebarMenuSubItem>
                                        </SidebarMenuSub>
                                    </div>
                                </Transition>
                            </Collapsible>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>