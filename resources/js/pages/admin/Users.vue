<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Pencil,
    Plus,
    RotateCcw,
    ShieldCheck,
    Trash2,
    UserMinus,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AdminNav from '@/components/admin/AdminNav.vue';
import AlertError from '@/components/AlertError.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Role = {
    id: number;
    name: string;
    slug: string;
    grants_all_modules?: boolean;
};

type StaffUser = {
    id: number;
    name: string;
    email: string;
    username: string | null;
    roles: Role[];
    role_ids: number[];
    created_at: string | null;
    deactivated_at: string | null;
    is_active: boolean;
    /** False once the account is referenced by any facility record. */
    can_be_deleted: boolean;
};

type Paginated<T> = {
    data: T[];
    from: number | null;
    to: number | null;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    users: Paginated<StaffUser>;
    roles: Role[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administration', href: '/admin/users' },
            { title: 'Staff accounts', href: '/admin/users' },
        ],
    },
});

const page = usePage();
const currentUserId = computed(
    () => (page.props.auth as { user: { id: number } | null }).user?.id ?? null,
);

// The UI hides refused actions, so this only surfaces if a guard fires anyway.
const lifecycleError = computed(
    () => (page.props.errors as Record<string, string> | undefined)?.delete,
);

const addOpen = ref(false);
const addForm = useForm<{
    name: string;
    username: string;
    email: string;
    password: string;
    password_confirmation: string;
    roles: number[];
}>({
    name: '',
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [],
});

function add() {
    addForm.post('/admin/users', {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset();
            addOpen.value = false;
        },
    });
}

const editing = ref<StaffUser | null>(null);
const editForm = useForm<{
    name: string;
    username: string;
    email: string;
    roles: number[];
}>({
    name: '',
    username: '',
    email: '',
    roles: [],
});

function openEdit(user: StaffUser) {
    editing.value = user;
    editForm.name = user.name;
    editForm.username = user.username ?? '';
    editForm.email = user.email;
    editForm.roles = [...user.role_ids];
    editForm.clearErrors();
}

function saveEdit() {
    if (!editing.value) {
        return;
    }

    editForm.patch(`/admin/users/${editing.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    });
}

/** Toggle a role id within a form's `roles` array. */
function toggleRole(form: { roles: number[] }, roleId: number, on: boolean) {
    form.roles = on
        ? [...form.roles, roleId]
        : form.roles.filter((id) => id !== roleId);
}

const deactivating = ref<StaffUser | null>(null);
const deleting = ref<StaffUser | null>(null);

function confirmDeactivate() {
    if (!deactivating.value) {
        return;
    }

    router.post(
        `/admin/users/${deactivating.value.id}/deactivate`,
        {},
        {
            preserveScroll: true,
            onFinish: () => (deactivating.value = null),
        },
    );
}

function reactivate(user: StaffUser) {
    router.post(
        `/admin/users/${user.id}/reactivate`,
        {},
        { preserveScroll: true },
    );
}

/**
 * Paginator labels arrive with HTML entities ("&laquo; Previous"). Decoding
 * them to text keeps the links plain — no v-html on a component.
 */
function pageLabel(label: string): string {
    return label
        .replace(/&laquo;/g, '«')
        .replace(/&raquo;/g, '»')
        .trim();
}

function confirmDelete() {
    if (!deleting.value) {
        return;
    }

    router.delete(`/admin/users/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <Head title="Staff accounts" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Staff accounts
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Create accounts for facility staff and assign the roles that
                    decide which modules they can reach.
                </p>
            </div>
            <Button type="button" @click="addOpen = true">
                <Plus class="size-4" />
                Add user
            </Button>
        </div>

        <AdminNav current="users" />

        <AlertError
            v-if="lifecycleError"
            :errors="[lifecycleError]"
            title="That account was not changed."
        />

        <div class="overflow-x-auto rounded-xl border border-border bg-card">
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b border-border text-left text-xs text-muted-foreground"
                    >
                        <th class="px-4 py-2.5 font-medium">Staff</th>
                        <th class="px-4 py-2.5 font-medium">Email</th>
                        <th class="px-4 py-2.5 font-medium">Roles</th>
                        <th class="px-4 py-2.5 font-medium">Status</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr
                        v-for="u in props.users.data"
                        :key="u.id"
                        :class="u.is_active ? '' : 'bg-muted/30'"
                    >
                        <td class="px-4 py-2.5">
                            <div
                                class="font-medium"
                                :class="
                                    u.is_active ? '' : 'text-muted-foreground'
                                "
                            >
                                {{ u.name }}
                                <span
                                    v-if="u.id === currentUserId"
                                    class="ml-1 text-xs font-normal text-muted-foreground"
                                    >(you)</span
                                >
                            </div>
                            <div
                                v-if="u.username"
                                class="font-mono text-xs text-muted-foreground"
                            >
                                {{ u.username }}
                            </div>
                        </td>
                        <td class="px-4 py-2.5 text-muted-foreground">
                            {{ u.email }}
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="flex flex-wrap gap-1">
                                <Badge
                                    v-for="r in u.roles"
                                    :key="r.id"
                                    variant="secondary"
                                >
                                    <ShieldCheck
                                        v-if="r.slug === 'super-administrator'"
                                    />
                                    {{ r.name }}
                                </Badge>
                                <span
                                    v-if="!u.roles.length"
                                    class="text-xs text-muted-foreground"
                                    >No roles — no access</span
                                >
                            </div>
                        </td>
                        <td class="px-4 py-2.5">
                            <span
                                v-if="u.is_active"
                                class="text-xs font-medium text-green-700 dark:text-green-400"
                                >Active</span
                            >
                            <span
                                v-else
                                class="text-xs text-muted-foreground"
                                :title="`Deactivated ${u.deactivated_at}`"
                                >Deactivated</span
                            >
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center justify-end gap-1">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-muted-foreground hover:text-foreground"
                                    @click="openEdit(u)"
                                >
                                    <Pencil class="size-4" />
                                    Edit
                                </Button>

                                <Button
                                    v-if="u.is_active && u.id !== currentUserId"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-muted-foreground hover:text-foreground"
                                    @click="deactivating = u"
                                >
                                    <UserMinus class="size-4" />
                                    Deactivate
                                </Button>

                                <Button
                                    v-if="!u.is_active"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-muted-foreground hover:text-foreground"
                                    @click="reactivate(u)"
                                >
                                    <RotateCcw class="size-4" />
                                    Reactivate
                                </Button>

                                <!-- Only offered for accounts no record refers
                                     to; anything else must be deactivated. -->
                                <Button
                                    v-if="
                                        u.can_be_deleted &&
                                        u.id !== currentUserId
                                    "
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-muted-foreground hover:text-destructive"
                                    @click="deleting = u"
                                >
                                    <Trash2 class="size-4" />
                                    Delete
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!props.users.data.length">
                        <td
                            colspan="5"
                            class="px-4 py-12 text-center text-sm text-muted-foreground"
                        >
                            No staff accounts yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="props.users.links.length > 3"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <p class="text-xs text-muted-foreground">
                Showing {{ props.users.from ?? 0 }}–{{ props.users.to ?? 0 }} of
                {{ props.users.total }}
            </p>
            <div class="flex flex-wrap gap-1">
                <template v-for="(link, i) in props.users.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        preserve-state
                        :aria-current="link.active ? 'page' : undefined"
                        class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-sm transition-colors"
                        :class="
                            link.active
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border hover:bg-muted'
                        "
                    >
                        {{ pageLabel(link.label) }}
                    </Link>
                    <span
                        v-else
                        class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border border-border px-2 text-sm text-muted-foreground/50"
                    >
                        {{ pageLabel(link.label) }}
                    </span>
                </template>
            </div>
        </div>

        <!-- Add dialog -->
        <Dialog v-model:open="addOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add user</DialogTitle>
                    <DialogDescription>
                        The account is usable straight away. Ask the member of
                        staff to change their password after first sign-in.
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="add">
                    <div class="grid gap-1.5">
                        <Label>Full name *</Label>
                        <Input
                            v-model="addForm.name"
                            placeholder="e.g. Dr. Amaka Obi"
                        />
                        <InputError :message="addForm.errors.name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Username *</Label>
                        <Input v-model="addForm.username" placeholder="a.obi" />
                        <InputError :message="addForm.errors.username" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label>Email *</Label>
                        <Input
                            v-model="addForm.email"
                            type="email"
                            placeholder="name@facility.test"
                        />
                        <InputError :message="addForm.errors.email" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Initial password *</Label>
                        <PasswordInput v-model="addForm.password" />
                        <InputError :message="addForm.errors.password" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Confirm password *</Label>
                        <PasswordInput
                            v-model="addForm.password_confirmation"
                        />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <Label>Roles *</Label>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label
                                v-for="r in roles"
                                :key="r.id"
                                class="flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm hover:bg-accent"
                            >
                                <Checkbox
                                    :model-value="addForm.roles.includes(r.id)"
                                    @update:model-value="
                                        (v: boolean | 'indeterminate') =>
                                            toggleRole(
                                                addForm,
                                                r.id,
                                                v === true,
                                            )
                                    "
                                />
                                <span>{{ r.name }}</span>
                            </label>
                        </div>
                        <InputError :message="addForm.errors.roles" />
                    </div>

                    <div class="flex items-end sm:col-span-2">
                        <Button type="submit" :disabled="addForm.processing">
                            <Plus class="size-4" />
                            Create account
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Edit dialog -->
        <Dialog
            :open="editing !== null"
            @update:open="
                (v: boolean) => {
                    if (!v) editing = null;
                }
            "
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit {{ editing?.name }}</DialogTitle>
                    <DialogDescription>
                        Passwords are not editable here — staff change their own
                        password under Settings.
                    </DialogDescription>
                </DialogHeader>
                <form
                    class="grid gap-3 sm:grid-cols-2"
                    @submit.prevent="saveEdit"
                >
                    <div class="grid gap-1.5">
                        <Label>Full name *</Label>
                        <Input v-model="editForm.name" />
                        <InputError :message="editForm.errors.name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Username *</Label>
                        <Input v-model="editForm.username" />
                        <InputError :message="editForm.errors.username" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label>Email *</Label>
                        <Input v-model="editForm.email" type="email" />
                        <InputError :message="editForm.errors.email" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <Label>Roles *</Label>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label
                                v-for="r in roles"
                                :key="r.id"
                                class="flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm hover:bg-accent"
                            >
                                <Checkbox
                                    :model-value="editForm.roles.includes(r.id)"
                                    @update:model-value="
                                        (v: boolean | 'indeterminate') =>
                                            toggleRole(
                                                editForm,
                                                r.id,
                                                v === true,
                                            )
                                    "
                                />
                                <span>{{ r.name }}</span>
                            </label>
                        </div>
                        <InputError :message="editForm.errors.roles" />
                    </div>

                    <div class="flex items-end sm:col-span-2">
                        <Button type="submit" :disabled="editForm.processing">
                            Save changes
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Deactivate confirmation -->
        <Dialog
            :open="deactivating !== null"
            @update:open="
                (v: boolean) => {
                    if (!v) deactivating = null;
                }
            "
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle
                        >Deactivate {{ deactivating?.name }}?</DialogTitle
                    >
                    <DialogDescription>
                        They will be signed out and unable to sign in again, and
                        will no longer appear as a provider or assignee. Every
                        record they entered stays exactly as it is, still
                        attributed to them. You can reactivate them at any time.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="deactivating = null"
                    >
                        Cancel
                    </Button>
                    <Button type="button" @click="confirmDeactivate">
                        <UserMinus class="size-4" />
                        Deactivate
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Delete confirmation -->
        <Dialog
            :open="deleting !== null"
            @update:open="
                (v: boolean) => {
                    if (!v) deleting = null;
                }
            "
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete {{ deleting?.name }}?</DialogTitle>
                    <DialogDescription>
                        This permanently removes the account. It is offered
                        because no facility record refers to it — nothing will
                        lose its history. This cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="deleting = null"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        @click="confirmDelete"
                    >
                        <Trash2 class="size-4" />
                        Delete permanently
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
