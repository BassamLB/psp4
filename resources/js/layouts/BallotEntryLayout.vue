<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLogoIcon from '@/components/AppLogoIcon.vue'
import { useAppearance } from '@/composables/useAppearance'

const { appearance, updateAppearance } = useAppearance()

const toggleTheme = () => {
    updateAppearance(appearance.value === 'dark' ? 'light' : 'dark')
}
</script>

<template>
    <div class="min-h-screen bg-gray-200 dark:bg-gray-900">
        <Head>
            <title>{{ $page.props.title }}</title>
        </Head>

        <!-- Header -->
        <header class="border-b border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 sticky top-0 z-30 shadow-md">
            <div class="mx-auto w-full px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    <!-- Logo -->
                    <div class="flex items-center gap-3">
                        <AppLogoIcon class="size-11 text-blue-600 dark:text-blue-400" />
                        <div>
                            <h1 class="text-lg font-bold text-gray-900 dark:text-white">
حزب التقدمي الاشتراكي                            </h1>
                            <p class="text-base text-gray-500 dark:text-gray-400">
لـــــــــجــــــــنـــــــة الـــــــمــــــــعـــــــلـــــــومــــــاتــــــيــــــة                            </p>
                        </div>
                    </div>

                    <!-- Theme Toggle -->
                    <button
                        @click="toggleTheme"
                        type="button"
                        class="rounded-lg p-2.5 text-sm text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-4 focus:ring-gray-200 dark:text-gray-400 dark:hover:bg-gray-700 dark:focus:ring-gray-700"
                    >
                        <svg
                            v-if="appearance === 'dark'"
                            class="size-5"
                            fill="currentColor"
                            viewBox="0 0 20 20"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"
                            />
                        </svg>
                        <svg
                            v-else
                            class="size-5"
                            fill="currentColor"
                            viewBox="0 0 20 20"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
                        </svg>
                        <span class="sr-only">تبديل الوضع</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main>
            <slot />
        </main>
    </div>
</template>
